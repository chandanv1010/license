<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Xu ly MOT giao dich tien vao: tim don, doi chieu so tien, cap license.
 *
 * Vi sao tach rieng khoi controller: cung mot giao dich co the den bang HAI
 * duong khac nhau -
 *
 *   1. Webhook, luc SePay goi sang (nhanh, nhung co the rot).
 *   2. Lenh doi soat, luc minh goi sang SePay hoi lai (cham, nhung chac).
 *
 * Hai duong do dung HAI bo ten truong khac nhau (`transferAmount` so voi
 * `amount_in`, `content` so voi `transaction_content`...). Neu moi duong tu
 * viet lay phan xu ly cua minh thi som muon hai ban se lech nhau, va cai it
 * duoc dung hon se am tham sai.
 */
class ThanhToanService
{
    public function __construct(private readonly OrderService $donHang)
    {
    }

    /**
     * @param  array{
     *     sepay_id: string, reference_code: ?string, gateway: ?string,
     *     account_number: ?string, amount: int, content: string,
     *     transferred_at: ?string, code: ?string, raw: array
     * }  $giaoDich
     * @return array{ket_qua: string, loi_nhan: string, order?: string, license?: string}
     */
    public function ghiNhan(array $giaoDich): array
    {
        $daCo = $this->daXuLy($giaoDich);

        if ($daCo) {
            return ['ket_qua' => 'da_co', 'loi_nhan' => 'Giao dich da duoc xu ly truoc do.'];
        }

        $thanhToan = Payment::create([
            'sepay_id' => $giaoDich['sepay_id'],
            'reference_code' => $giaoDich['reference_code'] ?: null,
            'gateway' => $giaoDich['gateway'],
            'account_number' => $giaoDich['account_number'],
            'amount' => $giaoDich['amount'],
            'content' => $giaoDich['content'],
            'transferred_at' => $this->thoiDiem($giaoDich['transferred_at']),
            'raw' => $giaoDich['raw'],
        ]);

        $ma = $this->maDon($giaoDich['code'], $giaoDich['content']);

        if ($ma === null) {
            $thanhToan->update(['note' => 'Khong tim thay ma don trong noi dung chuyen khoan.']);

            return ['ket_qua' => 'khong_khop', 'loi_nhan' => 'Khong khop don nao.'];
        }

        $don = Order::where('code', $ma)->first();

        if (!$don) {
            $thanhToan->update(['note' => "Ma {$ma} khong ung voi don nao."]);

            return ['ket_qua' => 'khong_khop', 'loi_nhan' => 'Khong khop don nao.'];
        }

        $thanhToan->update(['order_id' => $don->id]);

        // Chuyen THIEU thi khong cap license, nhung van ghi lai de con lien he
        // voi khach. Chuyen THUA thi chap nhan - nguoi ta go nham so, khong co
        // ly do gi bat ho cho.
        if ($giaoDich['amount'] < $don->amount) {
            $thanhToan->update([
                'note' => "Chuyen thieu: nhan {$giaoDich['amount']}d, can {$don->amount}d.",
            ]);

            return ['ket_qua' => 'thieu_tien', 'loi_nhan' => 'So tien chua du.', 'order' => $don->code];
        }

        $license = $this->donHang->ghiNhanThanhToan($don);

        $thanhToan->update(['note' => 'Da cap license.']);

        return [
            'ket_qua' => 'da_cap',
            'loi_nhan' => 'Da cap license.',
            'order' => $don->code,
            'license' => $license->key,
        ];
    }

    /**
     * Giao dich nay da vao bang chua?
     *
     * Kiem CA HAI khoa. Ly do: webhook va API cua SePay dung HAI dinh danh
     * khac nhau cho cung mot giao dich (webhook tra ve id dang so, API tra ve
     * id dang UUID). Chi kiem mot khoa thi mot giao dich da xu ly qua webhook
     * se bi lenh doi soat ghi lai lan nua.
     */
    private function daXuLy(array $giaoDich): bool
    {
        if (Payment::where('sepay_id', $giaoDich['sepay_id'])->exists()) {
            return true;
        }

        $thamChieu = $giaoDich['reference_code'] ?? null;

        return $thamChieu
            ? Payment::where('reference_code', $thamChieu)->exists()
            : false;
    }

    /**
     * Tim ma don trong noi dung chuyen khoan.
     *
     * Khong so sanh bang, cung khong lay chuoi dau tien gap: do TAT CA cac
     * manh dung dinh dang roi doi chieu voi CSDL, chi nhan khi dung mot manh
     * ung voi mot don that. Ngan hang chen rat nhieu ma giao dich vao truong
     * nay, ngau nhien trung dinh dang la chuyen co the xay ra.
     */
    private function maDon(?string $maCuaSePay, string $noiDung): ?string
    {
        if (is_string($maCuaSePay) && trim($maCuaSePay) !== '') {
            $ma = strtoupper(trim($maCuaSePay));

            if (Order::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        $cauHinh = config('license.code');
        $bangChu = preg_quote($cauHinh['alphabet'], '/');
        $mau = '/' . preg_quote($cauHinh['prefix'], '/') . '[' . $bangChu . ']{' . $cauHinh['length'] . '}/';

        if (!preg_match_all($mau, strtoupper($noiDung), $khop)) {
            return null;
        }

        $coThat = Order::whereIn('code', array_unique($khop[0]))->pluck('code');

        return $coThat->count() === 1 ? $coThat->first() : null;
    }

    private function thoiDiem(?string $chu): ?string
    {
        if (!$chu) {
            return null;
        }

        try {
            return (string) Carbon::parse($chu);
        } catch (\Throwable) {
            return null;
        }
    }
}

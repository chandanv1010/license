<?php

namespace App\Console\Commands;

use App\Services\ThanhToanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Doi soat: hoi thang SePay xem co giao dich nao chua duoc xu ly khong.
 *
 * VI SAO CAN, du da co webhook: webhook la mot cu goi HTTP qua Internet, va
 * no RỚT ĐƯỢC. Chinh viec do da xay ra trong lan thu dau tien - may chu tra
 * loi cham, SePay cho 30 giay roi bo cuoc, giao dich coi nhu bien mat.
 *
 * Voi mot trang web binh thuong thi mat mot thong bao khong sao. Voi tien thi
 * khac: khach da chuyen tien ma khong nhan duoc ma kich hoat, va ho se nghi
 * minh lua dao chu khong nghi "chac webhook rot".
 *
 * Nen dat lich chay moi 5-10 phut:
 *
 *     * / 5 * * * *  cd /duong/dan && php artisan license:doi-soat
 *
 * Chay lai bao nhieu lan cung duoc: giao dich da xu ly bi bo qua nho khoa
 * chong trung trong ThanhToanService.
 */
class DoiSoat extends Command
{
    protected $signature = 'license:doi-soat
        {--so=50 : So giao dich gan nhat can soi}
        {--thu : Chi xem, khong ghi gi}';

    protected $description = 'Hỏi SePay xem có giao dịch nào webhook bỏ sót không';

    public function handle(ThanhToanService $thanhToan): int
    {
        $goc = (string) config('license.sepay.api_base');
        $token = (string) config('license.sepay.api_token');

        if ($token === '') {
            $this->error('Chưa cấu hình SEPAY_API_TOKEN trong .env.');

            return self::FAILURE;
        }

        $this->line("Hỏi {$goc} …");

        try {
            $tra = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get($goc . '/v2/transactions', ['limit' => (int) $this->option('so')]);
        } catch (\Throwable $e) {
            $this->error('Không gọi được SePay: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!$tra->successful()) {
            $this->error('SePay trả về HTTP ' . $tra->status() . ': ' . $tra->body());

            return self::FAILURE;
        }

        $giaoDich = $tra->json('data') ?? [];

        if (!$giaoDich) {
            $this->info('Không có giao dịch nào.');

            return self::SUCCESS;
        }

        $dem = ['da_cap' => 0, 'da_co' => 0, 'khong_khop' => 0, 'thieu_tien' => 0, 'bo_qua' => 0];

        foreach ($giaoDich as $g) {
            // Chi quan tam tien VAO.
            if (($g['transfer_type'] ?? 'in') !== 'in') {
                $dem['bo_qua']++;
                continue;
            }

            if ($this->option('thu')) {
                $this->line(sprintf(
                    '  [xem] %s  %10s đ  webhook=%s  %s',
                    $g['transaction_date'] ?? '?',
                    number_format((int) ($g['amount_in'] ?? 0)),
                    ($g['webhook_success'] ?? 0) ? 'ok' : 'RỚT',
                    mb_strimwidth((string) ($g['transaction_content'] ?? ''), 0, 60, '…'),
                ));
                continue;
            }

            // Ten truong cua API KHAC ten truong cua webhook - doi ve ten
            // chung roi moi dua vao dich vu, de chi co MOT cho xu ly.
            $kq = $thanhToan->ghiNhan([
                'sepay_id' => (string) ($g['id'] ?? ''),
                'reference_code' => $g['reference_number'] ?? null,
                'gateway' => $g['bank_brand_name'] ?? null,
                'account_number' => $g['account_number'] ?? null,
                'amount' => (int) ($g['amount_in'] ?? 0),
                'content' => trim((string) ($g['transaction_content'] ?? '')),
                'transferred_at' => $g['transaction_date'] ?? null,
                'code' => $g['code'] ?? null,
                'raw' => $g,
            ]);

            $dem[$kq['ket_qua']] = ($dem[$kq['ket_qua']] ?? 0) + 1;

            // Chi keu len khi co viec that su xay ra. Doi soat chay 5 phut mot
            // lan, in het ra thi nhat ky cron thanh rac.
            if ($kq['ket_qua'] === 'da_cap') {
                $this->info("  ✓ {$kq['order']} → {$kq['license']}  (webhook đã bỏ sót giao dịch này)");
            } elseif ($kq['ket_qua'] === 'thieu_tien') {
                $this->warn("  ! {$kq['order']}: {$kq['loi_nhan']}");
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Soi %d giao dịch — cấp mới %d, đã có %d, không khớp %d, thiếu tiền %d.',
            count($giaoDich),
            $dem['da_cap'],
            $dem['da_co'],
            $dem['khong_khop'],
            $dem['thieu_tien'],
        ));

        if ($dem['da_cap'] > 0) {
            $this->newLine();
            $this->warn('Có giao dịch webhook bỏ sót. Kiểm tra lại URL webhook và tốc độ máy chủ.');
        }

        return self::SUCCESS;
    }
}

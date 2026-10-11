<?php

namespace App\Services;

use App\Mail\LicenseMail;
use App\Models\License;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Tao don hang, sinh ma VietQR, va cap license khi tien ve.
 */
class OrderService
{
    /**
     * Tao mot don moi cho san pham/goi da khai bao trong config/license.php.
     *
     * Truyen `$giaHan` thi don nay la don GIA HAN: tra tien xong se cong them
     * ngay vao chinh license do chu khong sinh ma moi.
     */
    public function taoDon(
        string $sanPham,
        string $goi,
        ?string $email = null,
        ?License $giaHan = null,
    ): Order {
        $cauHinh = $this->goi($sanPham, $goi);

        return Order::create([
            'code' => $this->maMoi(),
            'product' => $sanPham,
            'plan' => $goi,
            'renew_license_id' => $giaHan?->id,
            'amount' => $cauHinh['amount'],
            // Don gia han mac dinh lay email cua chinh license cu: nguoi gia
            // han khong phai go lai, va go nham la thu bay den dia chi la.
            'email' => $email ?: $giaHan?->email,
            'status' => Order::CHUA_TRA,
        ]);
    }

    /**
     * License co the gia han duoc bang ma nay khong?
     *
     * Het han roi VAN gia han duoc - do moi la luc nguoi ta can gia han nhat.
     * Chi ma bi thu hoi va ma cua san pham khac la khong.
     */
    public function licenseDeGiaHan(?string $key, string $sanPham): ?License
    {
        if (!$key || trim($key) === '') {
            return null;
        }

        return License::where('key', strtoupper(trim($key)))
            ->where('product', $sanPham)
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Danh dau don da tra va cap license.
     *
     * Chay lai duoc nhieu lan: don da tra roi thi tra ve dung license cu chu
     * khong cap them cai moi. SePay goi lai webhook khi lan dau timeout, va hai
     * giao dich cung luc cung co the cham vao mot don.
     */
    public function ghiNhanThanhToan(Order $don): License
    {
        [$license, $vuaCap] = $this->capLicense($don);

        // Gui mail SAU khi giao dich CSDL da commit. Gui trong giao dich ma
        // sau do cuon nguoc thi khach cam mot ma khong ton tai.
        if ($vuaCap) {
            $this->guiMail($license, $don->laGiaHan() ? 'gia-han' : 'moi', $don);
        }

        return $license;
    }

    /**
     * Gui ma cho nguoi mua.
     *
     * Hong thi GHI NHAT KY ROI THOI, khong nem loi: khach da tra tien va
     * license da nam trong CSDL. Lam vo ca request chi vi may chu mail chap
     * chon la bien mot phien ban thanh "chuyen tien xong khong thay gi".
     * Mat mail thi con trang /tra-cuu.
     */
    public function guiMail(License $license, string $kieu = 'moi', ?Order $don = null): bool
    {
        if (!$license->email) {
            return false;
        }

        try {
            Mail::to($license->email)->send(new LicenseMail($license, $kieu, $don));

            return true;
        } catch (\Throwable $e) {
            Log::error('Khong gui duoc mail license', [
                'license' => $license->id,
                'loi' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array{0: License, 1: bool} license, va co phai vua cap moi khong */
    private function capLicense(Order $don): array
    {
        return DB::transaction(function () use ($don) {
            // Khoa dong de hai webhook den cung luc khong cap hai license.
            $don = Order::whereKey($don->getKey())->lockForUpdate()->firstOrFail();

            // Chong lam hai lan. Voi don gia han dieu nay con quan trong hon:
            // cap trung chi la thua mot dong trong bang, con CONG TRUNG la
            // bieu khong mot nam.
            if ($don->daTra() && ($daCo = $don->licenseKetQua())) {
                return [$daCo, false];
            }

            $cauHinh = $this->goi($don->product, $don->plan);

            $don->update([
                'status' => Order::DA_TRA,
                'paid_at' => $don->paid_at ?? now(),
            ]);

            if ($cu = $don->licenseGiaHan) {
                return [$this->noiDaiHan($cu, $don, $cauHinh['days']), true];
            }

            $license = License::create([
                'order_id' => $don->id,
                'key' => $this->keyMoi($don->product),
                'product' => $don->product,
                'email' => $don->email,
                'starts_at' => now(),
                'expires_at' => now()->addDays($cauHinh['days']),
                'max_devices' => config('license.max_devices'),
            ]);

            return [$license, true];
        });
    }

    /**
     * Cong them ngay vao license dang co.
     *
     * Moc tinh la han CU neu han do con o tuong lai, khong phai hom nay. Gia
     * han som ba thang ma tinh tu hom nay la nguoi dung mat ba thang da tra
     * tien - va ho se khong bao gio gia han som nua.
     */
    private function noiDaiHan(License $cu, Order $don, int $soNgay): License
    {
        $moc = $cu->expires_at->isFuture() ? $cu->expires_at : now();

        $cu->forceFill([
            'expires_at' => $moc->copy()->addDays($soNgay),
            // Da gia han thi vong nhac nam sau phai chay lai tu dau.
            'reminded_at' => null,
            'email' => $don->email ?: $cu->email,
        ])->save();

        return $cu->fresh();
    }

    /**
     * Dia chi anh ma VietQR dong - dien san so tien va noi dung chuyen khoan.
     *
     * Dien san la bat buoc chu khong phai tien nghi: bat nguoi mua tu go
     * "LX7K2M" vao o noi dung la chac chan co nguoi go sai, va mot don go sai
     * la mot lan anh phai doi chieu bang tay.
     */
    public function anhQr(Order $don): string
    {
        $sePay = config('license.sepay');

        return $sePay['qr_base'] . '?' . http_build_query([
            'acc' => $sePay['account'],
            'bank' => $sePay['bank'],
            'amount' => $don->amount,
            'des' => $don->code,
            'template' => 'compact',
        ]);
    }

    /**
     * Ma don moi, chua ai dung.
     *
     * Vong lap vi ma sinh ngau nhien nen ve ly thuyet co the trung. Voi 32 ky
     * tu va do dai 6 thi kha nang trung la mot phan ty, nhung "mot phan ty"
     * khong phai "khong bao gio" - va cot `code` co rang buoc duy nhat nen
     * trung la do thang.
     */
    private function maMoi(): string
    {
        $cauHinh = config('license.code');

        for ($lan = 0; $lan < 20; $lan++) {
            $ma = $cauHinh['prefix'] . $this->chuoiNgauNhien($cauHinh['alphabet'], $cauHinh['length']);

            if (!Order::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new \RuntimeException('Khong sinh duoc ma don moi sau 20 lan thu.');
    }

    /**
     * Key giao cho nguoi mua, dang NOPAUSE-XXXX-XXXX-XXXX.
     *
     * Chia nhom bon ky tu de nguoi dung con chep tay duoc ma khong lac dong.
     */
    private function keyMoi(string $sanPham): string
    {
        $alphabet = config('license.code.alphabet');

        do {
            $nhom = [];

            for ($i = 0; $i < 3; $i++) {
                $nhom[] = $this->chuoiNgauNhien($alphabet, 4);
            }

            $key = strtoupper($sanPham) . '-' . implode('-', $nhom);
        } while (License::where('key', $key)->exists());

        return $key;
    }

    private function chuoiNgauNhien(string $alphabet, int $doDai): string
    {
        $ra = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $doDai; $i++) {
            $ra .= $alphabet[random_int(0, $max)];
        }

        return $ra;
    }

    /**
     * @return array{label: string, amount: int, days: int}
     */
    private function goi(string $sanPham, string $goi): array
    {
        $cauHinh = config("license.products.{$sanPham}.plans.{$goi}");

        if (!$cauHinh) {
            throw new InvalidArgumentException("Khong co goi {$goi} cua san pham {$sanPham}.");
        }

        return $cauHinh;
    }
}

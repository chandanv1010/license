<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Nhac nguoi mua gia han truoc khi het han.
 *
 * Goi nam ma khong nhac thi gan nhu 100% khach roi o thang thu 12 - sau mot
 * nam ho khong con nho minh da mua gi, va khong co gi dap vao mat de nho.
 * Mot la thu truoc bay ngay la chenh lech giua "ban mot lan" va "ban hang nam".
 *
 * Dat lich chay moi ngay mot lan.
 */
class NhacGiaHan extends Command
{
    protected $signature = 'license:nhac-gia-han
        {--truoc=7 : Nhac truoc bao nhieu ngay}
        {--thu : Chi xem, khong gui}';

    protected $description = 'Gửi thư nhắc gia hạn cho license sắp hết hạn';

    public function handle(OrderService $donHang): int
    {
        $truoc = (int) $this->option('truoc');

        $danhSach = License::query()
            ->whereNull('revoked_at')
            ->whereNull('reminded_at')
            ->whereNotNull('email')
            ->whereBetween('expires_at', [now(), now()->addDays($truoc)])
            ->get();

        if ($danhSach->isEmpty()) {
            $this->info("Không có license nào hết hạn trong {$truoc} ngày tới.");

            return self::SUCCESS;
        }

        $xong = 0;

        foreach ($danhSach as $license) {
            if ($this->option('thu')) {
                $this->line("  [xem] {$license->email} — còn {$license->soNgayConLai()} ngày");
                continue;
            }

            if ($donHang->guiMail($license, 'nhac')) {
                // Danh dau NGAY sau khi gui: chay lai lenh khong duoc gui lan
                // hai cho cung mot nguoi.
                $license->forceFill(['reminded_at' => now()])->save();
                $xong++;
            }
        }

        $this->info("Đã nhắc {$xong}/{$danhSach->count()} người.");

        return self::SUCCESS;
    }
}

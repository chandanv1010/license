<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Xoa don chua tra qua han.
 *
 * Moi lan ai do mo trang mua la sinh mot don. Phan lon khong tra tien. Khong
 * don thi bang phinh ra vo han, va kho ma don (6 ky tu) cu hep dan.
 *
 * Chi xoa don CHUA TRA. Don da tra giu mai mai - do la chung tu.
 */
class DonDonCu extends Command
{
    protected $signature = 'license:don-don {--thu : Chi dem, khong xoa}';

    protected $description = 'Xoá các đơn chưa thanh toán đã quá hạn';

    public function handle(): int
    {
        $phut = (int) config('license.order_ttl_minutes', 60);

        $truyVan = Order::where('status', Order::CHUA_TRA)
            ->where('created_at', '<', now()->subMinutes($phut))
            // Cho chac: don nao lo da co license thi khong dung toi.
            ->whereDoesntHave('license')
            // Va don nao DA CO GIAO DICH thi giu lai bang moi gia.
            //
            // Truong hop that: khach chuyen THIEU tien. Don van o trang thai
            // chua tra, nhung giao dich da duoc ghi va tro vao don nay. Xoa
            // don di la cat dut soi day duy nhat noi so tien do voi nguoi da
            // chuyen - con lai mot dong tien khong biet cua ai.
            ->whereDoesntHave('payments');

        $so = $truyVan->count();

        if ($this->option('thu')) {
            $this->line("Có {$so} đơn chưa trả quá {$phut} phút.");

            return self::SUCCESS;
        }

        $truyVan->delete();

        $this->info("Đã xoá {$so} đơn chưa thanh toán quá {$phut} phút.");

        return self::SUCCESS;
    }
}

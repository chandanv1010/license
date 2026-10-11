<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

/**
 * Bien nhan cho mot don da tra.
 *
 * Duong dan duoc KY (middleware `signed`): ma don chi co 8 ky tu, khong ky
 * thi do ma la xem duoc bien nhan cua nguoi khac - kem theo email cua ho.
 *
 * Khong dat han cho chu ky: bien nhan la thu nguoi ta luu lai de doi tien o
 * cong ty, co the vai thang sau moi dung den. Mot duong dan het han sau bay
 * ngay la mot duong dan vo dung.
 */
class BienNhanController extends Controller
{
    public function xem(string $ma): View
    {
        $don = Order::with(['license', 'licenseGiaHan', 'payments'])
            ->where('code', strtoupper($ma))
            ->firstOrFail();

        // Don chua tra thi chua co gi de bien nhan.
        abort_unless($don->daTra(), 404);

        return view('bien-nhan', [
            'don' => $don,
            'license' => $don->licenseKetQua(),
            'goi' => config("license.products.{$don->product}.plans.{$don->plan}"),
            'tenSanPham' => config("license.products.{$don->product}.name", $don->product),
            'ben' => config('license.seller'),
        ]);
    }
}

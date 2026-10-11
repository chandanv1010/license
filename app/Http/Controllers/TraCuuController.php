<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tra cuu lai ma kich hoat khi nguoi mua lam mat.
 *
 * KHONG hien ma ra trang. Ai cung go duoc mot dia chi email vao o do, nen hien
 * ma ra man hinh la cho khong san pham cho bat ky ai doan trung email cua
 * khach. Gui ve chinh hom thu do moi la bang chung ho so huu no.
 *
 * Va luon tra ve CUNG MOT cau tra loi du email co ton tai hay khong: tra loi
 * khac nhau la bien trang nay thanh cong cu do xem dia chi nao da tung mua
 * hang.
 */
class TraCuuController extends Controller
{
    public function __construct(private readonly OrderService $donHang)
    {
    }

    public function form(): View
    {
        return view('tra-cuu');
    }

    public function gui(Request $request): RedirectResponse
    {
        $duLieu = $request->validate(
            ['email' => 'required|email|max:191'],
            ['email.required' => 'Bạn chưa nhập email.', 'email.email' => 'Email không hợp lệ.'],
        );

        $license = License::where('email', $duLieu['email'])
            ->whereNull('revoked_at')
            ->orderByDesc('expires_at')
            ->first();

        if ($license) {
            $this->donHang->guiMail($license, 'tra-cuu');
        }

        return back()->with(
            'nx_success',
            'Nếu email này từng mua hàng, chúng tôi vừa gửi lại mã kích hoạt. '
            . 'Nhớ xem cả hộp thư rác.',
        );
    }
}

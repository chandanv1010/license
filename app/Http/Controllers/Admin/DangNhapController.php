<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Dang nhap trang quan tri.
 *
 * Viet tay thay vi cai goi scaffolding: ung dung nay chi co MOT tai khoan va
 * khong co dang ky, khong co quen mat khau, khong co xac thuc email. Keo ca
 * bo scaffolding vao la them vai chuc duong dan khong ai dung - moi duong dan
 * la mot cho phai nho ma khoa.
 */
class DangNhapController extends Controller
{
    public function form(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.tong-quan');
        }

        return view('admin.dang-nhap');
    }

    public function vao(Request $request): RedirectResponse
    {
        $duLieu = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Chan do mat khau. Khong co cho nay thi mot vong lap curl co the thu
        // hang trieu mat khau mot ngay vao dung cai trang cam chia khoa cua
        // toan bo doanh thu.
        $khoa = 'dang-nhap:' . $request->ip();

        if (RateLimiter::tooManyAttempts($khoa, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Sai quá nhiều lần. Thử lại sau ' . RateLimiter::availableIn($khoa) . ' giây.',
            ]);
        }

        if (!Auth::attempt($duLieu, $request->boolean('nho'))) {
            RateLimiter::hit($khoa, 300);

            // KHONG noi ro sai email hay sai mat khau: noi ro la bien o dang
            // nhap thanh cong cu do xem dia chi nao co tai khoan.
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        RateLimiter::clear($khoa);

        // Doi id phien sau khi dang nhap: chong session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.tong-quan'));
    }

    public function ra(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.dang-nhap');
    }
}

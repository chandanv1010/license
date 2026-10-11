<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Trang quan tri: xem don, xem giao dich, va xu ly cac ca phai dung tay.
 *
 * Phan lon viec o day la cho nhung truong hop KHONG tu dong duoc: khach
 * chuyen thieu tien, khach go sai noi dung chuyen khoan, khach doi may lan
 * thu tu, hoan tien. Khong co trang nay thi moi ca nhu vay la mot lan mo
 * CSDL ra sua bang tay - va sua bang tay luc 11 gio dem la luc de sai nhat.
 */
class QuanTriController extends Controller
{
    public function __construct(private readonly OrderService $donHang)
    {
    }

    public function tongQuan(): View
    {
        $dauThang = now()->startOfMonth();

        return view('admin.tong-quan', [
            'doanhThuThang' => Payment::whereNotNull('order_id')
                ->where('created_at', '>=', $dauThang)->sum('amount'),
            'donThang' => Order::where('status', Order::DA_TRA)
                ->where('paid_at', '>=', $dauThang)->count(),
            'dangDung' => License::whereNull('revoked_at')
                ->where('expires_at', '>', now())->count(),
            'sapHet' => License::whereNull('revoked_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])->count(),

            // Day la con so quan trong nhat tren trang: moi dong o day la mot
            // nguoi da chuyen tien ma chua nhan duoc hang.
            'chuaKhop' => Payment::whereNull('order_id')->count(),

            'donMoi' => Order::with(['license', 'licenseGiaHan'])
                ->latest('id')->take(10)->get(),
            'giaoDichLo' => Payment::whereNull('order_id')->latest('id')->take(10)->get(),
        ]);
    }

    public function donHang(Request $request): View
    {
        $truyVan = Order::with(['license', 'licenseGiaHan', 'payments'])->latest('id');

        if ($tim = trim((string) $request->query('tim'))) {
            $truyVan->where(function ($q) use ($tim) {
                $q->where('code', 'like', "%{$tim}%")->orWhere('email', 'like', "%{$tim}%");
            });
        }

        if ($trangThai = $request->query('trang_thai')) {
            $truyVan->where('status', $trangThai);
        }

        return view('admin.don-hang', [
            'danhSach' => $truyVan->paginate(30)->withQueryString(),
            'tim' => $tim,
            'trangThai' => $trangThai,
        ]);
    }

    public function giaoDich(Request $request): View
    {
        $truyVan = Payment::with('order')->latest('id');

        if ($request->query('loc') === 'chua-khop') {
            $truyVan->whereNull('order_id');
        }

        if ($tim = trim((string) $request->query('tim'))) {
            $truyVan->where('content', 'like', "%{$tim}%");
        }

        return view('admin.giao-dich', [
            'danhSach' => $truyVan->paginate(30)->withQueryString(),
            'loc' => $request->query('loc'),
            'tim' => $tim,
        ]);
    }

    public function license(Request $request): View
    {
        $truyVan = License::with(['order', 'devices'])->latest('id');

        if ($tim = trim((string) $request->query('tim'))) {
            $truyVan->where(function ($q) use ($tim) {
                $q->where('key', 'like', "%{$tim}%")->orWhere('email', 'like', "%{$tim}%");
            });
        }

        return view('admin.license', [
            'danhSach' => $truyVan->paginate(30)->withQueryString(),
            'tim' => $tim,
        ]);
    }

    // --- Nhung viec phai lam bang tay -----------------------------------------

    /**
     * Gan mot giao dich chua khop vao mot don, roi cap license.
     *
     * Dung khi khach go sai noi dung chuyen khoan. Day la viec thuong gap
     * nhat trong ca nam: ngan hang cho go tu do, va nguoi ta go tu do that.
     */
    public function ganGiaoDich(Request $request, Payment $giaoDich): RedirectResponse
    {
        $duLieu = $request->validate(['ma_don' => 'required|string|max:32']);

        if ($giaoDich->order_id) {
            return back()->with('nx_loi', 'Giao dịch này đã gắn vào đơn rồi.');
        }

        $don = Order::where('code', strtoupper(trim($duLieu['ma_don'])))->first();

        if (!$don) {
            return back()->with('nx_loi', 'Không có đơn nào mang mã đó.');
        }

        $giaoDich->update([
            'order_id' => $don->id,
            'note' => 'Gắn tay bởi ' . $request->user()->email . '.',
        ]);

        $license = $this->donHang->ghiNhanThanhToan($don);

        Log::warning('Gan giao dich bang tay', [
            'giao_dich' => $giaoDich->id,
            'don' => $don->code,
            'boi' => $request->user()->email,
        ]);

        return back()->with('nx_ok', "Đã gắn vào đơn {$don->code} và cấp mã {$license->key}.");
    }

    /** Thu hoi license - dung khi hoan tien hoac giao dich gian lan. */
    public function thuHoi(Request $request, License $license): RedirectResponse
    {
        $license->forceFill(['revoked_at' => now()])->save();

        Log::warning('Thu hoi license', [
            'license' => $license->id,
            'boi' => $request->user()->email,
        ]);

        return back()->with('nx_ok', "Đã thu hồi {$license->key}. Lần kiểm tiếp theo của họ sẽ bị từ chối.");
    }

    public function boThuHoi(Request $request, License $license): RedirectResponse
    {
        $license->forceFill(['revoked_at' => null])->save();

        Log::warning('Bo thu hoi license', [
            'license' => $license->id,
            'boi' => $request->user()->email,
        ]);

        return back()->with('nx_ok', "Đã khôi phục {$license->key}.");
    }

    /**
     * Go mot may khoi license.
     *
     * Khach doi may lan thu tu goi dien la dung cho nay. Khong co no thi phai
     * mo thang CSDL xoa dong trong `license_devices`.
     */
    public function goMay(Request $request, License $license, LicenseDevice $may): RedirectResponse
    {
        if ($may->license_id !== $license->id) {
            return back()->with('nx_loi', 'Máy này không thuộc mã đó.');
        }

        $may->delete();

        return back()->with('nx_ok', 'Đã gỡ một máy. Họ dán lại mã là vào được.');
    }

    public function goHetMay(Request $request, License $license): RedirectResponse
    {
        $so = $license->devices()->count();
        $license->devices()->delete();

        return back()->with('nx_ok', "Đã gỡ {$so} máy khỏi {$license->key}.");
    }

    /**
     * Cong them ngay bang tay.
     *
     * Dung de den bu khi may chu sap, hoac tang them cho khach kho tinh. So
     * ngay co the AM de tru lai neu lo go nham.
     */
    public function congNgay(Request $request, License $license): RedirectResponse
    {
        $duLieu = $request->validate(['so_ngay' => 'required|integer|min:-3650|max:3650']);

        $moc = $license->expires_at->isFuture() ? $license->expires_at : now();

        $license->forceFill([
            'expires_at' => $moc->copy()->addDays($duLieu['so_ngay']),
            'reminded_at' => null,
        ])->save();

        Log::warning('Cong ngay bang tay', [
            'license' => $license->id,
            'so_ngay' => $duLieu['so_ngay'],
            'boi' => $request->user()->email,
        ]);

        return back()->with('nx_ok', "Hạn mới của {$license->key}: {$license->fresh()->expires_at->format('d/m/Y')}.");
    }

    /** Gui lai ma cho nguoi mua - khi ho bao khong nhan duoc thu. */
    public function guiLaiMa(License $license): RedirectResponse
    {
        if (!$license->email) {
            return back()->with('nx_loi', 'Mã này không gắn với email nào.');
        }

        return $this->donHang->guiMail($license, 'tra-cuu')
            ? back()->with('nx_ok', "Đã gửi lại mã tới {$license->email}.")
            : back()->with('nx_loi', 'Gửi thất bại. Xem storage/logs/laravel.log.');
    }
}

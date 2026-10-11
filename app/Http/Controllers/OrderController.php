<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang mua hang va hai duong dan ajax di kem.
 */
class OrderController extends Controller
{
    /** Khoa giu ma don dang cho trong phien lam viec cua trinh duyet. */
    private const KHOA_PHIEN = 'don_dang_cho';

    public function __construct(private readonly OrderService $donHang)
    {
    }

    /** Trang hien ma QR cho nguoi mua quet. */
    public function trangMua(Request $request, string $sanPham = 'nopause'): View
    {
        abort_unless(config("license.products.{$sanPham}"), 404);

        return view('mua', [
            'maSanPham' => $sanPham,
            'sanPham' => config("license.products.{$sanPham}"),
            // Nut "Gia han ngay" trong thu nhac tro ve day kem ?key=... de
            // nguoi dung khong phai di tim lai ma cua chinh minh.
            'keyCu' => $request->query('key', ''),
        ]);
    }

    /**
     * Tao don moi, tra ve ma QR de trang hien len.
     *
     * Bam lai thi nhan lai DUNG don cu chu khong sinh don moi. Hai ly do:
     *
     *   1. Phan lon nguoi mo trang mua khong chuyen tien. Moi lan tai trang
     *      sinh mot don la bang `orders` phinh vo han bang rac.
     *   2. Nguoi mua da quet ma QR cu vao ung dung ngan hang roi moi bam lai -
     *      sinh ma moi la ho chuyen tien voi ma cu, con trang thi ngoi doi mot
     *      don khac.
     */
    public function tao(Request $request): JsonResponse
    {
        $duLieu = $request->validate([
            'product' => 'required|string|max:32',
            'plan' => 'required|string|max:32',
            'email' => 'nullable|email|max:191',
            'renew_key' => 'nullable|string|max:64',
        ]);

        abort_unless(
            config("license.products.{$duLieu['product']}.plans.{$duLieu['plan']}"),
            404,
        );

        $giaHan = $this->donHang->licenseDeGiaHan($duLieu['renew_key'] ?? null, $duLieu['product']);

        if (!empty($duLieu['renew_key']) && !$giaHan) {
            return response()->json([
                'message' => 'Mã kích hoạt cũ không đúng, hoặc không phải của sản phẩm này.',
            ], 422);
        }

        $don = $this->donDangCho($request, $duLieu, $giaHan);

        if ($don) {
            // Nguoi mua go them email roi bam lai: ghi nhan email, nhung van
            // la don cu - ma QR trong tay ho khong duoc doi.
            if (!empty($duLieu['email']) && $duLieu['email'] !== $don->email) {
                $don->update(['email' => $duLieu['email']]);
            }
        } else {
            $don = $this->donHang->taoDon(
                $duLieu['product'],
                $duLieu['plan'],
                $duLieu['email'] ?? null,
                $giaHan,
            );
            $request->session()->put(self::KHOA_PHIEN, $don->code);
        }

        return response()->json([
            'code' => $don->code,
            'amount' => $don->amount,
            'gia_han' => $don->laGiaHan(),
            'qr' => $this->donHang->anhQr($don),
            'account' => config('license.sepay.account'),
            'bank' => config('license.sepay.bank'),
            'account_name' => config('license.sepay.account_name'),
        ]);
    }

    /**
     * Don chua tra cua CHINH trinh duyet nay, neu con dung han.
     *
     * Khoa theo PHIEN chu khong theo IP. Ca mot van phong ra Internet chung
     * mot dia chi IP; khoa theo IP la hai nguoi khac nhau cung tro vao mot
     * don, va nguoi thu hai chuyen tien xong se nhan dung cai key cua nguoi
     * thu nhat.
     *
     * Doi san pham hoac doi goi thi khong dung lai don cu - so tien khac nhau.
     */
    private function donDangCho(Request $request, array $duLieu, ?License $giaHan): ?Order
    {
        $ma = $request->session()->get(self::KHOA_PHIEN);

        if (!$ma) {
            return null;
        }

        return Order::where('code', $ma)
            ->where('status', Order::CHUA_TRA)
            ->where('product', $duLieu['product'])
            ->where('plan', $duLieu['plan'])
            // Doi y giua "mua moi" va "gia han" thi phai la don khac: mot ben
            // sinh ma moi, mot ben cong ngay vao ma cu.
            ->where('renew_license_id', $giaHan?->id)
            // Qua han thi lenh don dep se xoa den noi - dung lai la tra ve mot
            // ma QR sap bien mat.
            ->where('created_at', '>=', now()->subMinutes((int) config('license.order_ttl_minutes', 60)))
            ->first();
    }

    /**
     * Trang mua goi lien tuc duong dan nay de biet tien ve chua.
     *
     * KHONG tra ve key khi don chua tra: ma don nam trong dia chi URL, ai cung
     * doan duoc. Chi don da tra moi lo key ra, va chi dung mot lan - nguoi mua
     * chep key xong la thoi.
     */
    public function trangThai(string $ma): JsonResponse
    {
        $don = Order::with(['license', 'licenseGiaHan'])->where('code', $ma)->firstOrFail();
        $license = $don->licenseKetQua();

        if (!$don->daTra() || !$license) {
            return response()->json(['status' => $don->status]);
        }

        return response()->json([
            'status' => 'paid',
            'key' => $license->key,
            'expires_at' => $license->expires_at->toDateString(),
            'gia_han' => $don->laGiaHan(),
        ]);
    }
}

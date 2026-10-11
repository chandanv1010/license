<?php

namespace Tests\Feature;

use App\Mail\LicenseMail;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Bien nhan cho mot don da tra, va trang chinh sach rieng tu.
 *
 * Bien nhan de khach doi tien o cong ty. Chinh sach rieng tu la thu Google
 * BAT BUOC phai co khi xin quyen <all_urls> - thieu la ho tra ho so ve.
 */
class BienNhanTest extends TestCase
{
    use RefreshDatabase;

    private function donDaTra(string $email = 'khach@example.test'): Order
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year', $email);
        app(OrderService::class)->ghiNhanThanhToan($don);

        return $don->fresh();
    }

    private function duong(Order $don): string
    {
        return URL::signedRoute('bien-nhan', ['ma' => $don->code]);
    }

    public function test_bien_nhan_mo_duoc_bang_duong_dan_da_ky(): void
    {
        $don = $this->donDaTra();

        $this->get($this->duong($don))
            ->assertOk()
            ->assertSee($don->code)
            ->assertSee('ĐÃ THANH TOÁN')
            ->assertSee('200.000đ');
    }

    /**
     * Khong ky thi khong xem duoc.
     *
     * Ma don chi 8 ky tu. Khong ky thi do ma la xem duoc bien nhan cua nguoi
     * khac - kem theo dia chi email cua ho.
     */
    public function test_khong_co_chu_ky_thi_tu_choi(): void
    {
        $don = $this->donDaTra();

        $this->get("/bien-nhan/{$don->code}")->assertForbidden();
    }

    public function test_sua_chu_ky_thi_tu_choi(): void
    {
        $don = $this->donDaTra();

        $this->get($this->duong($don) . 'abc')->assertForbidden();
    }

    /** Don chua tra thi chua co gi de bien nhan. */
    public function test_don_chua_tra_thi_khong_co_bien_nhan(): void
    {
        $don = app(OrderService::class)->taoDon('nopause', 'year', 'khach@example.test');

        $this->get($this->duong($don))->assertNotFound();
    }

    public function test_bien_nhan_co_ma_kich_hoat_va_han(): void
    {
        $don = $this->donDaTra();
        $license = $don->licenseKetQua();

        $this->get($this->duong($don))
            ->assertSee($license->key)
            ->assertSee($license->expires_at->format('d/m/Y'));
    }

    /**
     * Phai noi ro day KHONG phai hoa don do.
     *
     * Khach doanh nghiep cam cai nay di quyet toan thue roi bi tra lai thi do
     * la loi cua minh, khong phai cua ho.
     */
    public function test_bien_nhan_noi_ro_khong_phai_hoa_don_do(): void
    {
        $this->get($this->duong($this->donDaTra()))
            ->assertSee('không phải hoá đơn giá trị gia tăng');
    }

    public function test_bien_nhan_cua_don_gia_han_ghi_ro_la_gia_han(): void
    {
        Mail::fake();

        $cu = $this->donDaTra()->licenseKetQua();
        $don = app(OrderService::class)->taoDon('nopause', 'year', null, $cu);
        app(OrderService::class)->ghiNhanThanhToan($don);

        $this->get($this->duong($don->fresh()))
            ->assertOk()
            ->assertSee('Gia hạn');
    }

    public function test_thu_cap_ma_co_duong_dan_bien_nhan(): void
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year', 'khach@example.test');
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $html = (new LicenseMail($license, 'moi', $don->fresh()))->render();

        $this->assertStringContainsString('/bien-nhan/' . $don->code, $html);
        $this->assertStringContainsString('signature=', $html);
    }

    // --- Chinh sach rieng tu ---------------------------------------------------

    public function test_trang_chinh_sach_mo_duoc(): void
    {
        $this->get('/chinh-sach')
            ->assertOk()
            ->assertSee('Chính sách riêng tư');
    }

    /**
     * Google doc trang nay khi duyet quyen <all_urls>. Phai tra loi dung cau
     * ho hoi: vi sao can quyen rong den vay.
     */
    public function test_chinh_sach_giai_thich_vi_sao_xin_quyen_moi_trang(): void
    {
        $this->get('/chinh-sach')
            ->assertSee('Vì sao tiện ích xin quyền truy cập mọi trang web')
            ->assertSee('không thu thập lịch sử duyệt web', false);
    }
}

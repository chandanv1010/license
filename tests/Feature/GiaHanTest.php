<?php

namespace Tests\Feature;

use App\Mail\LicenseMail;
use App\Models\License;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Gia han phai CONG THEM ngay vao ma dang dung, khong sinh ma moi.
 *
 * Sinh ma moi thi nguoi dung phai go lai o tung may, ma cu thanh rac, va neu
 * ho quen doi thi dung ngay het han la extension tat - du da tra tien.
 */
class GiaHanTest extends TestCase
{
    use RefreshDatabase;

    private function licenseCu(?int $conLai = null, string $email = 'khach@example.test'): License
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year', $email);
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        if ($conLai !== null) {
            $license->forceFill(['expires_at' => now()->addDays($conLai)])->save();
        }

        Mail::fake();

        return $license->fresh();
    }

    private function donGiaHan(License $cu, ?string $email = null): Order
    {
        return app(OrderService::class)->taoDon('nopause', 'year', $email, $cu);
    }

    // --- Cong don han ---------------------------------------------------------

    public function test_gia_han_cong_them_ngay_vao_ma_cu(): void
    {
        $cu = $this->licenseCu(30);
        $hanCu = $cu->expires_at->copy();

        $moi = app(OrderService::class)->ghiNhanThanhToan($this->donGiaHan($cu));

        $this->assertSame($cu->key, $moi->key, 'Gia han lai sinh ma moi');
        $this->assertSame(1, License::count(), 'Co them mot license thua');
        $this->assertSame(
            $hanCu->addDays(365)->toDateString(),
            $moi->expires_at->toDateString(),
        );
    }

    /**
     * Gia han SOM thi moc tinh tu han cu, khong phai tu hom nay.
     *
     * Tinh tu hom nay la nguoi gia han som ba thang mat trang ba thang da tra
     * tien - va ho se khong bao gio gia han som nua.
     */
    public function test_gia_han_som_khong_mat_phan_con_lai(): void
    {
        $cu = $this->licenseCu(100);

        $moi = app(OrderService::class)->ghiNhanThanhToan($this->donGiaHan($cu));

        $this->assertSame(465, (int) round(now()->diffInDays($moi->expires_at)));
    }

    /** Het han roi van gia han duoc - do moi la luc nguoi ta can nhat. */
    public function test_gia_han_sau_khi_da_het_han_thi_tinh_tu_hom_nay(): void
    {
        $cu = $this->licenseCu(-40);

        $moi = app(OrderService::class)->ghiNhanThanhToan($this->donGiaHan($cu));

        $this->assertSame(365, (int) round(now()->diffInDays($moi->expires_at)));
    }

    /**
     * SePay goi lai webhook khi lan dau timeout, va lenh doi soat cung cham
     * vao cung mot don. Cap trung chi thua mot dong; CONG TRUNG la bieu khong
     * mot nam.
     */
    public function test_ghi_nhan_hai_lan_khong_cong_hai_lan(): void
    {
        $cu = $this->licenseCu(30);
        $don = $this->donGiaHan($cu);

        $lan1 = app(OrderService::class)->ghiNhanThanhToan($don);
        $lan2 = app(OrderService::class)->ghiNhanThanhToan($don->fresh());

        $this->assertSame($lan1->expires_at->toDateString(), $lan2->expires_at->toDateString());
    }

    /** Gia han xong thi vong nhac nam sau phai chay lai tu dau. */
    public function test_gia_han_xoa_dau_da_nhac(): void
    {
        $cu = $this->licenseCu(5);
        $cu->forceFill(['reminded_at' => now()])->save();

        app(OrderService::class)->ghiNhanThanhToan($this->donGiaHan($cu->fresh()));

        $this->assertNull($cu->fresh()->reminded_at);
    }

    public function test_gia_han_gui_thu_bao_da_gia_han(): void
    {
        $cu = $this->licenseCu(30);

        app(OrderService::class)->ghiNhanThanhToan($this->donGiaHan($cu));

        Mail::assertSent(LicenseMail::class, fn (LicenseMail $t) => $t->kieu === 'gia-han');
    }

    /** Khong go email thi lay email cua chinh ma cu. */
    public function test_don_gia_han_thua_ke_email_cua_ma_cu(): void
    {
        $cu = $this->licenseCu(30, 'nguoi-mua@example.test');

        $this->assertSame('nguoi-mua@example.test', $this->donGiaHan($cu)->email);
    }

    // --- Nhan ma cu tu trang mua ----------------------------------------------

    public function test_trang_mua_nhan_ma_cu_va_tao_don_gia_han(): void
    {
        $cu = $this->licenseCu(30);

        $ra = $this->postJson(route('order.create'), [
            'product' => 'nopause', 'plan' => 'year', 'renew_key' => $cu->key,
        ])->assertOk()->assertJsonPath('gia_han', true);

        $this->assertSame($cu->id, Order::where('code', $ra->json('code'))->first()->renew_license_id);
    }

    public function test_ma_cu_viet_thuong_van_nhan(): void
    {
        $cu = $this->licenseCu(30);

        $this->postJson(route('order.create'), [
            'product' => 'nopause', 'plan' => 'year', 'renew_key' => strtolower($cu->key),
        ])->assertOk()->assertJsonPath('gia_han', true);
    }

    /**
     * Ma sai thi phai BAO LOI chu khong duoc lang le tao don mua moi.
     *
     * Lang le la nguoi dung tra 200k tuong de gia han, nhan ve mot ma thu hai,
     * con ma dang dung tren ba may thi van het han nhu cu.
     */
    public function test_ma_cu_sai_thi_bao_loi_chu_khong_tao_don_mua_moi(): void
    {
        $this->postJson(route('order.create'), [
            'product' => 'nopause', 'plan' => 'year', 'renew_key' => 'NOPAUSE-XXXX-YYYY-ZZZZ',
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_ma_bi_thu_hoi_thi_khong_gia_han_duoc(): void
    {
        $cu = $this->licenseCu(30);
        $cu->forceFill(['revoked_at' => now()])->save();

        $this->postJson(route('order.create'), [
            'product' => 'nopause', 'plan' => 'year', 'renew_key' => $cu->key,
        ])->assertStatus(422);
    }

    /** Doi y giua "mua moi" va "gia han" thi phai la hai don khac nhau. */
    public function test_doi_y_sang_gia_han_thi_sinh_don_khac(): void
    {
        $cu = $this->licenseCu(30);

        $mua = $this->postJson(route('order.create'), ['product' => 'nopause', 'plan' => 'year'])->json('code');
        $gia = $this->postJson(route('order.create'), [
            'product' => 'nopause', 'plan' => 'year', 'renew_key' => $cu->key,
        ])->json('code');

        $this->assertNotSame($mua, $gia);
    }

    /**
     * Trang mua phai thay duoc ma cua don gia han.
     *
     * Don gia han KHONG co quan he `license()` - cot `licenses.order_id` van
     * tro ve don mua lan dau. Thieu cho nay thi nguoi da chuyen tien ngoi
     * nhin man hinh quay mai.
     */
    public function test_trang_mua_hien_duoc_ma_sau_khi_gia_han(): void
    {
        $cu = $this->licenseCu(30);
        $don = $this->donGiaHan($cu);

        app(OrderService::class)->ghiNhanThanhToan($don);

        $this->getJson(route('order.status', $don->code))
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('key', $cu->key)
            ->assertJsonPath('gia_han', true);
    }

    /** Nut "Gia han ngay" trong thu nhac phai mang san ma cu sang trang mua. */
    public function test_thu_nhac_co_duong_dan_kem_ma(): void
    {
        $cu = $this->licenseCu(5);

        $html = (new LicenseMail($cu, 'nhac'))->render();

        $this->assertStringContainsString('/mua/nopause?key=' . $cu->key, $html);
    }

    public function test_trang_mua_dien_san_ma_tu_duong_dan(): void
    {
        $this->get('/mua/nopause?key=NOPAUSE-AAAA-BBBB-CCCC')
            ->assertOk()
            ->assertSee('NOPAUSE-AAAA-BBBB-CCCC', false);
    }
}

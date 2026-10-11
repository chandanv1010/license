<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_tao_don_tra_ve_ma_qr_dien_san_so_tien_va_noi_dung(): void
    {
        config()->set('license.sepay.account', '0281119899999');
        config()->set('license.sepay.bank', 'MB');

        $tra = $this->postJson('/api/order', [
            'product' => 'nopause',
            'plan' => 'year',
        ])->assertOk();

        $ma = $tra->json('code');
        $qr = $tra->json('qr');

        $this->assertSame(200000, $tra->json('amount'));

        // Dien san so tien va noi dung la bat buoc: bat nguoi mua tu go ma vao
        // o noi dung thi chac chan co nguoi go sai, va moi lan go sai la mot
        // lan phai doi chieu bang tay.
        $this->assertStringContainsString('amount=200000', $qr);
        $this->assertStringContainsString('des=' . $ma, $qr);
        $this->assertStringContainsString('acc=0281119899999', $qr);
    }

    public function test_ma_don_mang_tien_to_va_khong_trung_nhau(): void
    {
        $dichVu = app(OrderService::class);
        $ma = [];

        for ($i = 0; $i < 50; $i++) {
            $ma[] = $dichVu->taoDon('nopause', 'year')->code;
        }

        $this->assertCount(50, array_unique($ma), 'Co ma don bi trung');

        foreach ($ma as $m) {
            $this->assertMatchesRegularExpression('/^LX[23456789A-HJ-NP-Z]{6}$/', $m);
        }
    }

    public function test_san_pham_hoac_goi_khong_co_thi_404(): void
    {
        $this->postJson('/api/order', ['product' => 'khong-co', 'plan' => 'year'])->assertNotFound();
        $this->postJson('/api/order', ['product' => 'nopause', 'plan' => 'thang'])->assertNotFound();
    }

    /**
     * Don chua tra thi KHONG duoc lo key.
     *
     * Ma don nam trong dia chi URL va chi co 6 ky tu - mot vong thu la doan ra.
     * Tra key cho don chua tra la cho khong san pham.
     */
    public function test_don_chua_tra_thi_khong_lo_key(): void
    {
        $don = app(OrderService::class)->taoDon('nopause', 'year');

        $this->getJson("/api/order/{$don->code}/status")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonMissingPath('key');
    }

    public function test_don_da_tra_thi_tra_ve_key(): void
    {
        $don = app(OrderService::class)->taoDon('nopause', 'year');
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $this->getJson("/api/order/{$don->code}/status")
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('key', $license->key);
    }

    /**
     * Ghi nhan thanh toan hai lan khong duoc cap hai license.
     *
     * Vua chong webhook goi lai, vua chong hai giao dich cung cham vao mot don.
     */
    public function test_ghi_nhan_thanh_toan_hai_lan_chi_mot_license(): void
    {
        $dichVu = app(OrderService::class);
        $don = $dichVu->taoDon('nopause', 'year');

        $mot = $dichVu->ghiNhanThanhToan($don);
        $hai = $dichVu->ghiNhanThanhToan($don->fresh());

        $this->assertSame($mot->id, $hai->id);
        $this->assertSame(1, $don->fresh()->license()->count());
    }

    public function test_trang_mua_mo_duoc(): void
    {
        $this->get('/mua/nopause')
            ->assertOk()
            ->assertSee('NoPause Playback')
            ->assertSee('200.000đ');
    }

    public function test_trang_mua_san_pham_la_thi_404(): void
    {
        $this->get('/mua/khong-ton-tai')->assertNotFound();
    }
}

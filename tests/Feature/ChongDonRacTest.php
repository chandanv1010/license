<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Chan don rac.
 *
 * Phan lon nguoi mo trang mua khong chuyen tien. Neu moi lan bam la mot dong
 * trong CSDL thi bang `orders` phinh ra bang rac, va chi can mot vong lap
 * curl la do day bang chi trong vai phut.
 *
 * Ba lop: dung lai don cu trong phien, chan dong theo IP, va don dep dinh ky.
 */
class ChongDonRacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bai kiem tra chan dong tu dat lai gioi han cua rieng no.
        RateLimiter::clear('');
    }

    private function dat(array $ghiDe = []): array
    {
        return array_merge(['product' => 'nopause', 'plan' => static::GOI], $ghiDe);
    }

    // --- Lop 1: dung lai don cu trong phien -----------------------------------

    public function test_bam_lai_thi_nhan_dung_don_cu(): void
    {
        $lan1 = $this->postJson(route('order.create'), $this->dat())->assertOk();
        $lan2 = $this->postJson(route('order.create'), $this->dat())->assertOk();

        $this->assertSame($lan1->json('code'), $lan2->json('code'));
        $this->assertSame(1, Order::count(), 'Bam hai lan sinh ra hai don');
    }

    public function test_bam_muoi_lan_van_chi_mot_don(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('order.create'), $this->dat())->assertOk();
        }

        $this->assertSame(1, Order::count());
    }

    /**
     * Trinh duyet KHAC phai duoc don RIENG.
     *
     * Day la ly do khoa theo phien chu khong theo IP: ca mot van phong ra
     * Internet chung mot dia chi. Khoa theo IP thi nguoi thu hai chuyen tien
     * xong se nhan dung cai key cua nguoi thu nhat.
     */
    public function test_nguoi_khac_duoc_don_rieng(): void
    {
        $a = $this->postJson(route('order.create'), $this->dat())->json('code');

        $this->flushSession();

        $b = $this->postJson(route('order.create'), $this->dat())->json('code');

        $this->assertNotSame($a, $b);
        $this->assertSame(2, Order::count());
    }

    public function test_don_da_tra_roi_thi_bam_lai_duoc_don_moi(): void
    {
        Mail::fake();

        $ma = $this->postJson(route('order.create'), $this->dat())->json('code');
        app(OrderService::class)->ghiNhanThanhToan(Order::where('code', $ma)->first());

        $moi = $this->postJson(route('order.create'), $this->dat())->json('code');

        $this->assertNotSame($ma, $moi, 'Tra tien xong van tro vao don cu');
    }

    /** Doi goi thi so tien khac - khong duoc dung lai don cu. */
    public function test_doi_goi_thi_sinh_don_moi(): void
    {
        config()->set('license.products.nopause.plans.thang', [
            'label' => 'Thang', 'amount' => 30000, 'days' => 30,
        ]);

        $nam = $this->postJson(route('order.create'), $this->dat())->json('code');
        $thang = $this->postJson(route('order.create'), $this->dat(['plan' => 'thang']))->json('code');

        $this->assertNotSame($nam, $thang);
    }

    /** Don sap bi lenh don dep xoa thi khong duoc tra lai cho nguoi mua. */
    public function test_don_qua_han_thi_sinh_don_moi(): void
    {
        $cu = $this->postJson(route('order.create'), $this->dat())->json('code');

        Order::where('code', $cu)->first()
            ->forceFill(['created_at' => now()->subMinutes(90)])->save();

        $moi = $this->postJson(route('order.create'), $this->dat())->json('code');

        $this->assertNotSame($cu, $moi);
    }

    /**
     * Go email roi bam lai: ghi nhan email, nhung GIU NGUYEN ma QR.
     *
     * Nguoi mua co the da quet ma cu vao ung dung ngan hang roi. Doi ma luc
     * nay la ho chuyen tien voi mot dang, con trang thi ngoi doi mot dang
     * khac.
     */
    public function test_them_email_thi_cap_nhat_don_cu_chu_khong_doi_ma(): void
    {
        $ma = $this->postJson(route('order.create'), $this->dat())->json('code');

        $sau = $this->postJson(route('order.create'), $this->dat(['email' => 'khach@example.test']))
            ->assertOk()->json('code');

        $this->assertSame($ma, $sau);
        $this->assertSame('khach@example.test', Order::where('code', $ma)->first()->email);
    }

    // --- Lop 2: chan dong theo IP ---------------------------------------------

    public function test_tao_don_bi_chan_khi_goi_qua_nhieu(): void
    {
        // Moi lan phai la mot phien moi, neu khong thi lan thu hai tro di chi
        // tra ve don cu va khong the hien duoc gioi han.
        for ($i = 0; $i < 10; $i++) {
            $this->flushSession();
            $this->postJson(route('order.create'), $this->dat())->assertOk();
        }

        $this->flushSession();
        $this->postJson(route('order.create'), $this->dat())->assertStatus(429);
    }

    /**
     * Duong hoi trang thai phai RONG hon nhieu: trang hoi 3 giay mot lan, tuc
     * 20 lan/phut cho MOT tab. Chan chat tay o day la nguoi da chuyen tien
     * ngoi nhin man hinh khong bao gio doi.
     */
    public function test_hoi_trang_thai_khong_bi_chan_o_nhip_cua_trang(): void
    {
        $ma = $this->postJson(route('order.create'), $this->dat())->json('code');

        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('order.status', $ma))->assertOk();
        }
    }

    // --- Lop 3: don dep dinh ky ------------------------------------------------

    /**
     * Don CHUA TRA nhung DA CO GIAO DICH thi khong duoc xoa.
     *
     * Truong hop that: khach chuyen THIEU tien. Don van "chua tra", nhung giao
     * dich da duoc ghi va tro vao don nay. Xoa don di la cat dut soi day duy
     * nhat noi so tien do voi nguoi da chuyen.
     */
    public function test_khong_xoa_don_da_co_giao_dich_du_chua_tra(): void
    {
        $don = app(OrderService::class)->taoDon('nopause', static::GOI, 'khach@example.test');
        $don->forceFill(['created_at' => now()->subHours(5)])->save();

        Payment::create([
            'sepay_id' => '12345',
            'reference_code' => 'SBTHIEUTIEN',
            'amount' => $this->gia() - 1000,
            'content' => $don->code,
            'order_id' => $don->id,
            'note' => 'Chuyen thieu: chua du tien.',
        ]);

        $this->artisan('license:don-don')->assertSuccessful();

        $this->assertNotNull(Order::find($don->id), 'Don co giao dich bi xoa mat');
    }

    public function test_van_xoa_don_qua_han_khong_co_giao_dich(): void
    {
        $don = app(OrderService::class)->taoDon('nopause', static::GOI);
        $don->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->artisan('license:don-don')->assertSuccessful();

        $this->assertNull(Order::find($don->id));
        $this->assertSame(0, License::count());
    }
}

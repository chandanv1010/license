<?php

namespace Tests\Feature;

use App\Mail\LicenseMail;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Trang quan tri.
 *
 * Day la cho cam chia khoa cua toan bo doanh thu: xem duoc moi don, thu hoi
 * duoc license, cong duoc ngay. Moi duong dan o day deu phai dong voi nguoi
 * chua dang nhap - va bai kiem tra phai chung minh dieu do cho TUNG duong
 * dan, khong phai cho mot cai dai dien.
 */
class QuanTriTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Quản trị',
            'email' => 'admin@example.test',
            'password' => 'mat-khau-du-dai-10',
        ]);
    }

    private function license(?int $conLai = null): License
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year', 'khach@example.test');
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        if ($conLai !== null) {
            $license->forceFill(['expires_at' => now()->addDays($conLai)])->save();
        }

        Mail::fake();

        return $license->fresh();
    }

    // --- Khoa cua ------------------------------------------------------------

    /**
     * TUNG duong dan phai tu khoa lay minh.
     *
     * Kiem mot cai dai dien la khong du: chi can mot dong dat nham ben ngoai
     * nhom `auth` thi ca bang license mo ra cho Internet, ma bai kiem tra van
     * xanh.
     */
    public static function duongDanCanDangNhap(): array
    {
        return [
            'tong quan' => ['GET', '/quan-tri'],
            'don hang' => ['GET', '/quan-tri/don-hang'],
            'giao dich' => ['GET', '/quan-tri/giao-dich'],
            'license' => ['GET', '/quan-tri/license'],
            'thu hoi' => ['POST', '/quan-tri/license/1/thu-hoi'],
            'bo thu hoi' => ['POST', '/quan-tri/license/1/bo-thu-hoi'],
            'cong ngay' => ['POST', '/quan-tri/license/1/cong-ngay'],
            'go het may' => ['POST', '/quan-tri/license/1/go-het-may'],
            'gui lai ma' => ['POST', '/quan-tri/license/1/gui-lai'],
            'gan giao dich' => ['POST', '/quan-tri/giao-dich/1/gan'],
        ];
    }

    #[DataProvider('duongDanCanDangNhap')]
    public function test_chua_dang_nhap_thi_bi_day_ve_trang_dang_nhap(string $cach, string $duong): void
    {
        $this->call($cach, $duong)->assertRedirect('/quan-tri/dang-nhap');
    }

    public function test_dang_nhap_dung_thi_vao_duoc(): void
    {
        $this->admin();

        $this->post('/quan-tri/dang-nhap', [
            'email' => 'admin@example.test',
            'password' => 'mat-khau-du-dai-10',
        ])->assertRedirect(route('admin.tong-quan'));

        $this->assertAuthenticated();
    }

    public function test_sai_mat_khau_thi_khong_vao_duoc(): void
    {
        $this->admin();

        $this->post('/quan-tri/dang-nhap', [
            'email' => 'admin@example.test',
            'password' => 'sai-be-bet',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Cau tra loi phai GIONG NHAU du email co that hay khong.
     *
     * Khac nhau la bien o dang nhap thanh cong cu do xem dia chi nao co tai
     * khoan quan tri.
     */
    public function test_email_khong_ton_tai_bao_loi_giong_het_sai_mat_khau(): void
    {
        $this->admin();

        $co = $this->followingRedirects()
            ->post('/quan-tri/dang-nhap', ['email' => 'admin@example.test', 'password' => 'sai'])
            ->getContent();

        $khong = $this->followingRedirects()
            ->post('/quan-tri/dang-nhap', ['email' => 'nguoi-la@example.test', 'password' => 'sai'])
            ->getContent();

        $this->assertSame($co, $khong, 'Trang bao loi khac nhau giua email co that va khong');
    }

    /** Khong chan thi mot vong lap curl do duoc mat khau. */
    public function test_sai_nhieu_lan_thi_bi_chan(): void
    {
        RateLimiter::clear('dang-nhap:127.0.0.1');
        $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/quan-tri/dang-nhap', ['email' => 'admin@example.test', 'password' => 'sai']);
        }

        // Lan thu sau: dung mat khau cung khong vao duoc nua.
        $this->post('/quan-tri/dang-nhap', [
            'email' => 'admin@example.test',
            'password' => 'mat-khau-du-dai-10',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dang_xuat_duoc(): void
    {
        $this->actingAs($this->admin())
            ->post('/quan-tri/dang-xuat')
            ->assertRedirect(route('admin.dang-nhap'));

        $this->assertGuest();
    }

    // --- Cac trang -----------------------------------------------------------

    public function test_tong_quan_dem_dung_giao_dich_chua_khop(): void
    {
        $this->license();

        Payment::create([
            'sepay_id' => '999', 'reference_code' => 'SBLAC',
            'amount' => 100000, 'content' => 'chuyen tien linh tinh',
            'note' => 'Khong tim thay ma don trong noi dung chuyen khoan.',
        ]);

        $this->actingAs($this->admin())
            ->get('/quan-tri')
            ->assertOk()
            ->assertSee('Giao dịch chưa khớp')
            ->assertSee('chuyen tien linh tinh');
    }

    public function test_tim_don_theo_email(): void
    {
        $this->license();

        $this->actingAs($this->admin())
            ->get('/quan-tri/don-hang?tim=khach@example.test')
            ->assertOk()
            ->assertSee('khach@example.test');
    }

    public function test_tim_license_theo_ma(): void
    {
        $license = $this->license();

        $this->actingAs($this->admin())
            ->get('/quan-tri/license?tim=' . $license->key)
            ->assertOk()
            ->assertSee($license->key);
    }

    // --- Viec lam bang tay ----------------------------------------------------

    /**
     * Gan giao dich lac vao dung don, roi cap license.
     *
     * Day la viec thuong gap nhat: ngan hang cho go noi dung tu do, va nguoi
     * ta go tu do that.
     */
    public function test_gan_giao_dich_lac_vao_don_thi_cap_license(): void
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year', 'khach@example.test');

        $gd = Payment::create([
            'sepay_id' => '555', 'reference_code' => 'SBLAC2',
            'amount' => 200000, 'content' => 'toi chuyen tien mua phan mem',
            'note' => 'Khong tim thay ma don trong noi dung chuyen khoan.',
        ]);

        $this->actingAs($this->admin())
            ->post("/quan-tri/giao-dich/{$gd->id}/gan", ['ma_don' => $don->code])
            ->assertRedirect();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
        $this->assertSame($don->id, $gd->fresh()->order_id);
        $this->assertSame(1, License::count());
    }

    public function test_gan_vao_ma_don_khong_co_thi_bao_loi(): void
    {
        $gd = Payment::create([
            'sepay_id' => '556', 'amount' => 200000, 'content' => 'x',
        ]);

        $this->actingAs($this->admin())
            ->post("/quan-tri/giao-dich/{$gd->id}/gan", ['ma_don' => 'LXKHONGCO'])
            ->assertSessionHas('nx_loi');

        $this->assertNull($gd->fresh()->order_id);
    }

    /** Giao dich da gan roi thi khong duoc gan lan nua. */
    public function test_khong_gan_lai_giao_dich_da_gan(): void
    {
        Mail::fake();

        $don = app(OrderService::class)->taoDon('nopause', 'year');
        $donKhac = app(OrderService::class)->taoDon('nopause', 'year');

        $gd = Payment::create([
            'sepay_id' => '557', 'amount' => 200000, 'content' => 'x', 'order_id' => $don->id,
        ]);

        $this->actingAs($this->admin())
            ->post("/quan-tri/giao-dich/{$gd->id}/gan", ['ma_don' => $donKhac->code])
            ->assertSessionHas('nx_loi');

        $this->assertSame($don->id, $gd->fresh()->order_id);
    }

    public function test_thu_hoi_va_khoi_phuc_license(): void
    {
        $license = $this->license();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/quan-tri/license/{$license->id}/thu-hoi");
        $this->assertNotNull($license->fresh()->revoked_at);

        // Va extension phai bi tu choi ngay lan hoi ke tiep.
        $this->postJson('/api/license/verify', ['key' => $license->key, 'device_id' => 'may-1'])
            ->assertJsonPath('status', 'revoked');

        $this->actingAs($admin)->post("/quan-tri/license/{$license->id}/bo-thu-hoi");
        $this->assertNull($license->fresh()->revoked_at);
    }

    /**
     * Go may bang tay.
     *
     * Khach doi may lan thu tu goi dien la dung cho nay - khong doi duoc 14
     * ngay de may cu tu nha suat.
     */
    public function test_go_mot_may_khoi_license(): void
    {
        $license = $this->license();

        $this->postJson('/api/license/verify', ['key' => $license->key, 'device_id' => 'may-1']);
        $may = $license->devices()->first();

        $this->actingAs($this->admin())
            ->post("/quan-tri/license/{$license->id}/may/{$may->id}/go")
            ->assertRedirect();

        $this->assertSame(0, $license->devices()->count());
    }

    /** Khong duoc go may cua license KHAC chi bang cach doi so trong dia chi. */
    public function test_khong_go_duoc_may_cua_license_khac(): void
    {
        $a = $this->license();
        $b = $this->license();

        $this->postJson('/api/license/verify', ['key' => $b->key, 'device_id' => 'may-cua-b']);
        $mayCuaB = $b->devices()->first();

        $this->actingAs($this->admin())
            ->post("/quan-tri/license/{$a->id}/may/{$mayCuaB->id}/go")
            ->assertSessionHas('nx_loi');

        $this->assertSame(1, $b->devices()->count());
    }

    public function test_go_het_may(): void
    {
        $license = $this->license();

        foreach (['m1', 'm2', 'm3'] as $may) {
            $this->postJson('/api/license/verify', ['key' => $license->key, 'device_id' => $may]);
        }

        $this->actingAs($this->admin())->post("/quan-tri/license/{$license->id}/go-het-may");

        $this->assertSame(0, $license->devices()->count());
    }

    public function test_cong_them_ngay_bang_tay(): void
    {
        $license = $this->license(30);
        $han = $license->expires_at->copy();

        $this->actingAs($this->admin())
            ->post("/quan-tri/license/{$license->id}/cong-ngay", ['so_ngay' => 15]);

        $this->assertSame(
            $han->addDays(15)->toDateString(),
            $license->fresh()->expires_at->toDateString(),
        );
    }

    /** So ngay am de tru lai khi lo go nham. */
    public function test_cong_so_ngay_am_thi_tru_han(): void
    {
        $license = $this->license(30);
        $han = $license->expires_at->copy();

        $this->actingAs($this->admin())
            ->post("/quan-tri/license/{$license->id}/cong-ngay", ['so_ngay' => -10]);

        $this->assertSame(
            $han->subDays(10)->toDateString(),
            $license->fresh()->expires_at->toDateString(),
        );
    }

    public function test_gui_lai_ma_cho_khach(): void
    {
        Mail::fake();

        $license = $this->license();

        $this->actingAs($this->admin())
            ->post("/quan-tri/license/{$license->id}/gui-lai")
            ->assertSessionHas('nx_ok');

        Mail::assertSent(LicenseMail::class, fn (LicenseMail $t) => $t->kieu === 'tra-cuu');
    }
}

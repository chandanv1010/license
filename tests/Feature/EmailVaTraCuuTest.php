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
 * Gui ma kich hoat qua email, va tra cuu lai khi mat.
 *
 * Thieu hai thu nay thi ma chi ton tai tren mot tab trinh duyet: dong tab la
 * mat, va nguoi da tra tien khong co duong nao lay lai.
 */
class EmailVaTraCuuTest extends TestCase
{
    use RefreshDatabase;

    private function don(?string $email = 'khach@example.test'): Order
    {
        return app(OrderService::class)->taoDon('nopause', static::GOI, $email);
    }

    // --- Gui luc cap moi ------------------------------------------------------

    public function test_cap_license_thi_gui_mail_cho_nguoi_mua(): void
    {
        Mail::fake();

        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        Mail::assertSent(LicenseMail::class, function (LicenseMail $thu) use ($license) {
            return $thu->hasTo('khach@example.test')
                && $thu->license->is($license)
                && $thu->kieu === 'moi';
        });
    }

    public function test_khong_co_email_thi_khong_gui_gi(): void
    {
        Mail::fake();

        app(OrderService::class)->ghiNhanThanhToan($this->don(null));

        Mail::assertNothingSent();
    }

    /**
     * Ghi nhan thanh toan hai lan chi duoc gui MOT thu.
     *
     * SePay gui lai webhook khi timeout, va lenh doi soat cung cham vao cung
     * mot don. Khong chan thi khach nhan mot chuoi thu giong het nhau.
     */
    public function test_ghi_nhan_hai_lan_chi_gui_mot_thu(): void
    {
        Mail::fake();

        $dichVu = app(OrderService::class);
        $don = $this->don();

        $dichVu->ghiNhanThanhToan($don);
        $dichVu->ghiNhanThanhToan($don->fresh());

        Mail::assertSentCount(1);
    }

    /**
     * May chu mail hong KHONG duoc lam hong viec cap license.
     *
     * Khach da tra tien. Nem loi o day la bien mot phien ban thanh "chuyen
     * tien xong khong thay gi", trong khi license van nam trong CSDL va chi
     * can vao trang tra cuu la lay duoc.
     */
    public function test_mail_hong_thi_license_van_duoc_cap(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP chet'));

        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $this->assertNotNull($license->key);
        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    // --- Trang tra cuu --------------------------------------------------------

    public function test_trang_tra_cuu_mo_duoc(): void
    {
        $this->get('/tra-cuu')->assertOk()->assertSee('Tra cứu mã kích hoạt');
    }

    public function test_tra_cuu_dung_email_thi_gui_lai_ma(): void
    {
        Mail::fake();

        $don = $this->don();
        app(OrderService::class)->ghiNhanThanhToan($don);

        $this->post('/tra-cuu', ['email' => 'khach@example.test'])->assertRedirect();

        Mail::assertSent(LicenseMail::class, fn (LicenseMail $thu) => $thu->kieu === 'tra-cuu');
    }

    /**
     * KHONG duoc hien ma ra trang.
     *
     * Ai cung go duoc mot dia chi email vao o do. Hien ma ra man hinh la cho
     * khong san pham cho bat ky ai doan trung email cua khach.
     */
    public function test_khong_hien_ma_ra_trang(): void
    {
        Mail::fake();

        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $html = $this->followingRedirects()
            ->post('/tra-cuu', ['email' => 'khach@example.test'])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($license->key, $html, 'Mã kích hoạt bị lộ ra trang');
    }

    /**
     * Email khong ton tai phai nhan CUNG MOT cau tra loi.
     *
     * Tra loi khac nhau la bien trang nay thanh cong cu do xem dia chi nao da
     * tung mua hang.
     */
    public function test_email_la_cung_nhan_mot_cau_tra_loi(): void
    {
        Mail::fake();

        $don = $this->don();
        app(OrderService::class)->ghiNhanThanhToan($don);

        $co = $this->post('/tra-cuu', ['email' => 'khach@example.test'])->getSession()->get('nx_success');
        $khong = $this->post('/tra-cuu', ['email' => 'nguoi-la@example.test'])->getSession()->get('nx_success');

        $this->assertSame($co, $khong);

        // Dem rieng thu tra-cuu. Luc cap license da gui mot thu "moi" roi,
        // dem tong la luon ra 2 va phep thu mat y nghia.
        Mail::assertSent(LicenseMail::class, fn (LicenseMail $t) => $t->kieu === 'tra-cuu');
        Mail::assertNotSent(LicenseMail::class, fn (LicenseMail $t) => $t->kieu === 'tra-cuu' && $t->hasTo('nguoi-la@example.test'));
    }

    public function test_license_bi_thu_hoi_thi_khong_gui_lai(): void
    {
        Mail::fake();

        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);
        $license->forceFill(['revoked_at' => now()])->save();

        $this->post('/tra-cuu', ['email' => 'khach@example.test']);

        Mail::assertNotSent(LicenseMail::class, fn (LicenseMail $thu) => $thu->kieu === 'tra-cuu');
    }

    public function test_email_sai_dinh_dang_thi_bao_loi(): void
    {
        $this->post('/tra-cuu', ['email' => 'khong-phai-email'])
            ->assertSessionHasErrors('email');
    }

    // --- Noi dung thu ---------------------------------------------------------

    public function test_thu_co_ma_kich_hoat_va_han_dung(): void
    {
        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $html = (new LicenseMail($license))->render();

        $this->assertStringContainsString($license->key, $html);
        $this->assertStringContainsString($license->expires_at->format('d/m/Y'), $html);
        $this->assertStringContainsString('NoPause Playback', $html);
    }

    public function test_thu_nhac_gia_han_co_nut_gia_han(): void
    {
        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        $html = (new LicenseMail($license, 'nhac'))->render();

        $this->assertStringContainsString('Gia hạn ngay', $html);
        $this->assertStringContainsString('/mua/nopause', $html);
    }
}

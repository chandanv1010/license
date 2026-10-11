<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Duong nhan tien tu SePay.
 *
 * Day la cho duy nhat trong ca he thong dung giua "khach da tra tien" va
 * "khach co hang". Sai o day thi hoac khach tra tien ma khong nhan duoc gi,
 * hoac nguoi khong tra tien van lay duoc license.
 */
class SePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-thu-nghiem';

    protected function setUp(): void
    {
        parent::setUp();

        // Noi ro bai kiem tra nay thu kieu API key, khong de .env quyet dinh.
        config()->set('license.sepay.auth', 'apikey');
        config()->set('license.sepay.token', self::TOKEN);
        config()->set('license.sepay.secret', null);
        config()->set('license.sepay.allowed_ips', []);
    }

    private function don(int $soTien = 200000): Order
    {
        return app(OrderService::class)->taoDon('nopause', 'year', 'khach@example.test');
    }

    private function goiTin(array $ghiDe = []): array
    {
        return array_merge([
            'id' => 92704,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-11 10:13:00',
            'accountNumber' => '0281119899999',
            'code' => null,
            'content' => 'chuyen tien',
            'transferType' => 'in',
            'transferAmount' => 200000,
            'referenceCode' => 'MBVCB.16475489034',
        ], $ghiDe);
    }

    private function gui(array $goiTin, ?string $token = self::TOKEN)
    {
        return $this->postJson(
            '/api/sepay/webhook',
            $goiTin,
            $token === null ? [] : ['Authorization' => 'Apikey ' . $token],
        );
    }

    // --- Xac thuc -------------------------------------------------------------

    public function test_khong_co_token_thi_tu_choi(): void
    {
        $this->gui($this->goiTin(), null)->assertStatus(401);

        $this->assertSame(0, Payment::count(), 'Da ghi nhan giao dich du chua xac thuc');
    }

    public function test_sai_token_thi_tu_choi(): void
    {
        $this->gui($this->goiTin(), 'token-bay')->assertStatus(401);
    }

    /**
     * Chua cau hinh token thi phai TU CHOI het, khong phai cho qua het.
     *
     * Day la loi hay gap khi trien khai: quen dien bien moi truong tren may chu,
     * ma ma nguon lai viet kieu "chua cau hinh thi bo qua kiem tra" - thanh ra
     * duong webhook mo toang cho ca Internet.
     */
    public function test_chua_cau_hinh_token_thi_tu_choi_het(): void
    {
        config()->set('license.sepay.auth', 'any');
        config()->set('license.sepay.token', '');
        config()->set('license.sepay.secret', '');

        $this->gui($this->goiTin())->assertStatus(401);
    }

    // --- Do ma don trong noi dung chuyen khoan ---------------------------------

    /**
     * Chuoi nay la noi dung THAT cua giao dich thu nghiem tren MB, chi thay ma
     * don vao. Nguoi gui chi go may chu; tat ca phan con lai do ngan hang chen.
     *
     * Day la ly do khong bao gio duoc so sanh bang noi dung chuyen khoan.
     */
    public function test_do_duoc_ma_don_lan_trong_rac_cua_ngan_hang(): void
    {
        $don = $this->don();

        $noiDung = 'MBVCB.16475489034.172405.NGUY THI PHUONG ' . $don->code
            . '.CT tu 1965763389 NGUYEN THI PHUONG toi 0281119899999 '
            . 'NGUYEN CONG TUAN tai MB- Ma GD ACS';

        $this->gui($this->goiTin(['content' => $noiDung]))->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
        $this->assertNotNull($don->fresh()->license, 'Khong cap license');
    }

    public function test_ma_viet_thuong_van_khop(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin(['content' => 'thanh toan ' . strtolower($don->code)]))->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    /**
     * SePay tu tach ma ra truong `code` khi dung mau QR cua ho. Tin truong do
     * truoc vi no chac chan hon viec tu do chuoi.
     */
    public function test_uu_tien_truong_code_cua_sepay(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin([
            'code' => $don->code,
            'content' => 'khong co ma trong day',
        ]))->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    public function test_khong_co_ma_thi_ghi_lai_chu_khong_bo_qua(): void
    {
        $this->don();

        $this->gui($this->goiTin(['content' => 'ba gui con tien an sang']))->assertOk();

        $thanhToan = Payment::sole();

        $this->assertNull($thanhToan->order_id);
        $this->assertStringContainsString('Khong tim thay ma don', $thanhToan->note);
        $this->assertSame(0, License::count());
    }

    /**
     * Hai ma hop le trong cung mot noi dung thi khong doan bua.
     *
     * Ngan hang chen rat nhieu ma giao dich vao truong nay; ngau nhien co chuoi
     * dung dinh dang la chuyen xay ra duoc. Cap license cho don doan mo la cap
     * nham hang cho nguoi khac.
     */
    public function test_hai_ma_cung_luc_thi_khong_cap_license(): void
    {
        $a = $this->don();
        $b = $this->don();

        $this->gui($this->goiTin(['content' => "{$a->code} va {$b->code}"]))->assertOk();

        $this->assertSame(0, License::count());
        $this->assertSame(Order::CHUA_TRA, $a->fresh()->status);
        $this->assertSame(Order::CHUA_TRA, $b->fresh()->status);
    }

    // --- So tien --------------------------------------------------------------

    public function test_chuyen_thieu_thi_khong_cap_license(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin([
            'content' => $don->code,
            'transferAmount' => 150000,
        ]))->assertOk();

        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
        $this->assertSame(0, License::count());
        $this->assertStringContainsString('Chuyen thieu', Payment::sole()->note);
    }

    /** Go nham so thi cho qua - khong co ly do gi bat khach cho. */
    public function test_chuyen_thua_van_cap_license(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin([
            'content' => $don->code,
            'transferAmount' => 500000,
        ]))->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    // --- Chong lap ------------------------------------------------------------

    /**
     * SePay gui lai webhook khi lan dau khong nhan duoc tra loi. Khong chong
     * lap thi mot lan chuyen tien cap hai license.
     */
    public function test_cung_mot_giao_dich_goi_hai_lan_chi_cap_mot_license(): void
    {
        $don = $this->don();
        $goiTin = $this->goiTin(['content' => $don->code]);

        $this->gui($goiTin)->assertOk();
        $this->gui($goiTin)->assertOk();

        $this->assertSame(1, License::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_bo_qua_giao_dich_tien_ra(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin([
            'content' => $don->code,
            'transferType' => 'out',
        ]))->assertOk();

        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    // --- Noi dung license cap ra ----------------------------------------------

    public function test_license_co_han_dung_365_ngay_va_gan_voi_don(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin(['content' => $don->code]))->assertOk();

        $license = License::sole();

        $this->assertSame($don->id, $license->order_id);
        $this->assertSame('nopause', $license->product);
        $this->assertSame('khach@example.test', $license->email);
        $this->assertSame(365, (int) round($license->starts_at->diffInDays($license->expires_at)));
        $this->assertStringStartsWith('NOPAUSE-', $license->key);
    }

    /**
     * Giao dich khong khop don nao VAN phai luu lai.
     *
     * Khach chuyen sai noi dung la chuyen xay ra hang ngay. Khong luu thi luc
     * ho keu "toi chuyen roi" se khong co cho nao tra.
     */
    public function test_moi_giao_dich_deu_duoc_luu_lai_kem_goi_tin_goc(): void
    {
        $this->gui($this->goiTin(['content' => 'khong lien quan']))->assertOk();

        $thanhToan = Payment::sole();

        $this->assertSame('92704', $thanhToan->sepay_id);
        $this->assertSame('MBBank', $thanhToan->gateway);
        $this->assertSame(200000, $thanhToan->amount);
        $this->assertSame('MBVCB.16475489034', $thanhToan->raw['referenceCode']);
    }
}

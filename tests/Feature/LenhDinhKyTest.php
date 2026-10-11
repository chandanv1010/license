<?php

namespace Tests\Feature;

use App\Mail\LicenseMail;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Ba lenh chay theo lich: doi soat, nhac gia han, don don cu.
 */
class LenhDinhKyTest extends TestCase
{
    use RefreshDatabase;

    private function don(?string $email = 'khach@example.test'): Order
    {
        return app(OrderService::class)->taoDon('nopause', 'year', $email);
    }

    // --- license:doi-soat -----------------------------------------------------

    /**
     * Ten truong cua API KHAC han ten truong cua webhook: `amount_in` so voi
     * `transferAmount`, `transaction_content` so voi `content`... Doi sai mot
     * truong la lenh doi soat im lang khong lam gi, ma khong ai biet.
     */
    private function giaoDichApi(string $noiDung, int $soTien = 200000, array $ghiDe = []): array
    {
        return array_merge([
            'id' => 'a520ea96-c528-11f1-b21a-a6006ab65aca',
            'transaction_date' => '2026-10-11 11:02:58',
            'account_number' => '0000000001',
            'transfer_type' => 'in',
            'amount_in' => $soTien,
            'amount_out' => 0,
            'transaction_content' => $noiDung,
            'reference_number' => 'SBE203F6EF854B',
            'code' => '',
            'bank_brand_name' => 'MBBank',
            'webhook_success' => 0,
        ], $ghiDe);
    }

    private function giaVo(array $danhSach): void
    {
        config()->set('license.sepay.api_token', 'token-thu');
        config()->set('license.sepay.api_base', 'https://userapi-sandbox.sepay.vn');

        Http::fake([
            '*/v2/transactions*' => Http::response(['status' => 'success', 'data' => $danhSach]),
        ]);
    }

    public function test_doi_soat_cap_license_cho_giao_dich_webhook_bo_sot(): void
    {
        Mail::fake();

        $don = $this->don();
        $this->giaVo([$this->giaoDichApi("thanh toan {$don->code}")]);

        $this->artisan('license:doi-soat')->assertSuccessful();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
        $this->assertSame(1, License::count());
    }

    /**
     * Webhook va API dung HAI dinh danh khac nhau cho CUNG MOT giao dich
     * (webhook tra id dang so, API tra UUID). Chong trung chi theo `sepay_id`
     * thi doi soat se ghi lai lan nua giao dich webhook da xu ly.
     *
     * Ma tham chieu cua ngan hang giong nhau o ca hai duong - do la khoa that.
     */
    public function test_giao_dich_webhook_da_xu_ly_thi_doi_soat_khong_lam_lai(): void
    {
        Mail::fake();

        $don = $this->don();

        // Webhook da xu ly, luu voi id dang so.
        Payment::create([
            'sepay_id' => '92704',
            'reference_code' => 'SBE203F6EF854B',
            'amount' => 200000,
            'content' => $don->code,
            'order_id' => $don->id,
            'note' => 'Da cap license.',
        ]);
        app(OrderService::class)->ghiNhanThanhToan($don);

        // Doi soat gap lai chinh no, nhung mang id dang UUID.
        $this->giaVo([$this->giaoDichApi($don->code)]);
        $this->artisan('license:doi-soat')->assertSuccessful();

        $this->assertSame(1, Payment::count(), 'Giao dich bi ghi lai lan hai');
        $this->assertSame(1, License::count());
    }

    public function test_doi_soat_bo_qua_giao_dich_tien_ra(): void
    {
        $don = $this->don();
        $this->giaVo([$this->giaoDichApi($don->code, 200000, ['transfer_type' => 'out'])]);

        $this->artisan('license:doi-soat')->assertSuccessful();

        $this->assertSame(0, Payment::count());
        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    public function test_doi_soat_che_do_xem_thi_khong_ghi_gi(): void
    {
        $don = $this->don();
        $this->giaVo([$this->giaoDichApi($don->code)]);

        $this->artisan('license:doi-soat', ['--thu' => true])->assertSuccessful();

        $this->assertSame(0, Payment::count());
        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    public function test_doi_soat_thieu_token_thi_bao_loi(): void
    {
        config()->set('license.sepay.api_token', '');

        $this->artisan('license:doi-soat')->assertFailed();
    }

    public function test_doi_soat_sepay_tra_loi_thi_bao_loi_chu_khong_vo(): void
    {
        config()->set('license.sepay.api_token', 'token-thu');
        Http::fake(['*/v2/transactions*' => Http::response('Loi may chu', 500)]);

        $this->artisan('license:doi-soat')->assertFailed();
    }

    // --- license:nhac-gia-han -------------------------------------------------

    public function test_nhac_gia_han_cho_license_sap_het(): void
    {
        Mail::fake();

        $license = $this->licenseHetSau(3);

        $this->artisan('license:nhac-gia-han')->assertSuccessful();

        Mail::assertSent(LicenseMail::class, fn (LicenseMail $thu) => $thu->kieu === 'nhac');
        $this->assertNotNull($license->fresh()->reminded_at);
    }

    public function test_khong_nhac_license_con_dai(): void
    {
        Mail::fake();

        $this->licenseHetSau(200);

        $this->artisan('license:nhac-gia-han')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_khong_nhac_license_da_het_han(): void
    {
        Mail::fake();

        $this->licenseHetSau(-5);

        $this->artisan('license:nhac-gia-han')->assertSuccessful();

        Mail::assertNothingSent();
    }

    /** Chay lai lenh khong duoc nhac lan hai cho cung mot nguoi. */
    public function test_khong_nhac_hai_lan(): void
    {
        Mail::fake();

        $this->licenseHetSau(3);

        $this->artisan('license:nhac-gia-han');
        $this->artisan('license:nhac-gia-han');

        Mail::assertSentCount(1);
    }

    // --- license:don-don ------------------------------------------------------

    public function test_don_don_xoa_don_chua_tra_qua_han(): void
    {
        $cu = $this->don();
        $cu->forceFill(['created_at' => now()->subHours(3)])->save();

        $moi = $this->don();

        $this->artisan('license:don-don')->assertSuccessful();

        $this->assertNull(Order::find($cu->id));
        $this->assertNotNull(Order::find($moi->id));
    }

    /** Don DA TRA la chung tu - khong bao gio duoc xoa. */
    public function test_don_don_khong_dong_toi_don_da_tra(): void
    {
        Mail::fake();

        $don = $this->don();
        app(OrderService::class)->ghiNhanThanhToan($don);
        $don->forceFill(['created_at' => now()->subYear()])->save();

        $this->artisan('license:don-don')->assertSuccessful();

        $this->assertNotNull(Order::find($don->id));
        $this->assertSame(1, License::count());
    }

    private function licenseHetSau(int $ngay): License
    {
        Mail::fake();

        $don = $this->don();
        $license = app(OrderService::class)->ghiNhanThanhToan($don);
        $license->forceFill(['expires_at' => now()->addDays($ngay)])->save();

        // Dat lai Mail::fake(): viec cap license vua gui mot thu "moi", de
        // nguyen thi moi phep dem cua bai kiem tra deu lech mot.
        Mail::fake();

        return $license->fresh();
    }
}

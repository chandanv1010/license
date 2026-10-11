<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Duong extension goi toi de hoi "key nay con dung duoc khong".
 *
 * Nguyen tac xuyen suot: moi cau tra loi la 200 kem `ok: true|false`, khong
 * dung ma loi HTTP de phan biet. Phia extension chi can nhin `ok`; gap 4xx/5xx
 * thi hieu la "may chu co van de" va giu nguyen trang thai cu, khong khoa
 * nham nguoi da tra tien.
 */
class LicenseVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function license(array $ghiDe = []): License
    {
        $don = app(OrderService::class)->taoDon('nopause', 'year');
        $license = app(OrderService::class)->ghiNhanThanhToan($don);

        if ($ghiDe) {
            $license->forceFill($ghiDe)->save();
        }

        return $license->fresh();
    }

    private function hoi(string $key, string $may = 'may-cua-toi', array $them = [])
    {
        return $this->postJson('/api/license/verify', array_merge([
            'key' => $key,
            'device_id' => $may,
        ], $them));
    }

    public function test_key_hop_le_thi_bao_con_han(): void
    {
        $license = $this->license();

        $this->hoi($license->key)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('product', 'nopause')
            ->assertJsonPath('devices', 1);
    }

    public function test_key_khong_ton_tai(): void
    {
        $this->hoi('NOPAUSE-AAAA-BBBB-CCCC')
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'not_found');
    }

    public function test_key_het_han(): void
    {
        $license = $this->license(['expires_at' => now()->subDay()]);

        $this->hoi($license->key)
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'expired');
    }

    public function test_key_bi_thu_hoi(): void
    {
        $license = $this->license(['revoked_at' => now()]);

        $this->hoi($license->key)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'revoked');
    }

    public function test_key_cua_san_pham_khac(): void
    {
        $license = $this->license();

        $this->hoi($license->key, 'may-cua-toi', ['product' => 'san-pham-khac'])
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'wrong_product');
    }

    public function test_key_viet_thuong_va_thua_khoang_trang_van_dung(): void
    {
        $license = $this->license();

        $this->hoi('  ' . strtolower($license->key) . '  ')
            ->assertJsonPath('ok', true);
    }

    // --- Gioi han so may -------------------------------------------------------

    /**
     * Khong gioi han so may thi mot key dan len dien dan la ca the gioi dung
     * chung. Day la hang rao duy nhat giua san pham co phi va san pham mien phi.
     */
    public function test_qua_so_may_cho_phep_thi_tu_choi(): void
    {
        $license = $this->license();

        foreach (['may-1', 'may-2', 'may-3'] as $may) {
            $this->hoi($license->key, $may)->assertJsonPath('ok', true);
        }

        $this->hoi($license->key, 'may-4')
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'device_limit');
    }

    /**
     * May da lau khong dung thi nhuong suat cho may moi.
     *
     * Id may nam trong chrome.storage. Cai lai tien ich, doi may, hay don dep
     * trinh duyet la sinh id MOI - may cu bien mat vinh vien nhung van giu
     * suat. Khong nha thi nguoi mua THAT bi khoa o lan doi may thu tu.
     */
    public function test_may_lau_khong_dung_thi_nhuong_suat(): void
    {
        $license = $this->license();

        foreach (['may-1', 'may-2', 'may-3'] as $may) {
            $this->hoi($license->key, $may)->assertJsonPath('ok', true);
        }

        // Ca ba deu bien mat da hai thang.
        $license->devices()->update(['last_seen_at' => now()->subDays(60)]);

        $this->hoi($license->key, 'may-moi')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('devices', 1);
    }

    /**
     * May DANG dung thi khong duoc nha.
     *
     * Extension hoi lai may chu moi 24 gio, nen may that su dang dung luon co
     * `last_seen_at` moi. Nha nham la ke chia se key cu the day nhau ra vao
     * vo han, va gioi han ba may thanh vo nghia.
     */
    public function test_may_dang_dung_thi_khong_bi_nha_suat(): void
    {
        $license = $this->license();

        foreach (['may-1', 'may-2', 'may-3'] as $may) {
            $this->hoi($license->key, $may)->assertJsonPath('ok', true);
        }

        // Vua hoi hom qua - van dang dung.
        $license->devices()->update(['last_seen_at' => now()->subDay()]);

        $this->hoi($license->key, 'may-4')
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'device_limit')
            ->assertJsonPath('max_devices', 3);
    }

    /** Chi nha may qua han, may con moi van giu nguyen suat. */
    public function test_chi_nha_dung_may_qua_han(): void
    {
        $license = $this->license();

        foreach (['may-1', 'may-2', 'may-3'] as $may) {
            $this->hoi($license->key, $may)->assertJsonPath('ok', true);
        }

        $license->devices()->update(['last_seen_at' => now()->subDay()]);
        $license->devices()->limit(1)->update(['last_seen_at' => now()->subDays(60)]);

        // Mot suat duoc nha, may moi vao duoc -> lai du ba.
        $this->hoi($license->key, 'may-moi')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('devices', 3);

        // Va may thu tu van bi tu choi.
        $this->hoi($license->key, 'may-nua')->assertJsonPath('status', 'device_limit');
    }

    /** De 0 la tat han viec nha suat. */
    public function test_dat_khong_ngay_thi_khong_nha_suat_nao(): void
    {
        config()->set('license.device_idle_days', 0);

        $license = $this->license();

        foreach (['may-1', 'may-2', 'may-3'] as $may) {
            $this->hoi($license->key, $may)->assertJsonPath('ok', true);
        }

        $license->devices()->update(['last_seen_at' => now()->subYears(5)]);

        $this->hoi($license->key, 'may-moi')->assertJsonPath('status', 'device_limit');
    }

    /** May da dang ky roi thi hoi lai bao nhieu lan cung duoc. */
    public function test_may_cu_hoi_lai_khong_ton_them_suat(): void
    {
        $license = $this->license();

        for ($i = 0; $i < 5; $i++) {
            $this->hoi($license->key, 'may-1')->assertJsonPath('ok', true);
        }

        $this->assertSame(1, $license->devices()->count());
    }

    /**
     * CSDL chi duoc giu BAM cua id may, khong giu id goc.
     *
     * Id may do extension sinh ra va la du lieu cua nguoi dung. Khong co ly do
     * gi may chu phai giu no o dang doc duoc.
     */
    public function test_chi_luu_bam_cua_id_may(): void
    {
        $license = $this->license();

        $this->hoi($license->key, 'id-may-rat-rieng-tu');

        $luu = $license->devices()->sole()->device_hash;

        $this->assertNotSame('id-may-rat-rieng-tu', $luu);
        $this->assertSame(hash('sha256', 'id-may-rat-rieng-tu'), $luu);
    }

    public function test_thieu_tham_so_thi_bao_loi_kiem_tra(): void
    {
        $this->postJson('/api/license/verify', ['key' => 'ABC'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_id');
    }

    // --- So ngay con lai -------------------------------------------------------

    /**
     * Con non mot ngay thi phai bao 1, khong phai 0: nguoi dung van dung duoc
     * hom nay, bao 0 la sai su that va lam ho tuong da mat tien.
     */
    public function test_con_non_mot_ngay_thi_bao_mot_ngay(): void
    {
        $license = $this->license(['expires_at' => now()->addHours(10)]);

        $this->hoi($license->key)->assertJsonPath('days_left', 1);
    }
}

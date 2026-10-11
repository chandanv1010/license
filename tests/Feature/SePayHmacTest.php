<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Xac thuc webhook bang HMAC-SHA256 - cach SePay khuyen dung.
 *
 * Hon API key o hai cho:
 *   - Khoa bi mat KHONG di tren duong truyen, chi co chu ky di.
 *   - Moi request mang mot dau thoi gian, nen ke bat duoc mot goi tin hop le
 *     khong phat lai duoc no sau nam phut.
 *
 * Dac ta cua SePay:
 *   chuoi ky = "{timestamp}.{raw_body}"
 *   chu ky   = "sha256=" . hash_hmac('sha256', chuoi_ky, secret)
 *   headers  = X-SePay-Signature, X-SePay-Timestamp (giay)
 */
class SePayHmacTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_chi_dung_trong_bai_kiem_tra';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('license.sepay.secret', self::SECRET);
        config()->set('license.sepay.token', null);
        config()->set('license.sepay.auth', 'hmac');
        config()->set('license.sepay.allowed_ips', []);
    }

    private function don(): Order
    {
        return app(OrderService::class)->taoDon('nopause', static::GOI);
    }

    private function goiTin(string $noiDung, ?int $soTien = null): array
    {
        $soTien ??= $this->gia();

        return [
            'id' => 551234,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-11 10:13:00',
            'accountNumber' => '0281119899999',
            'code' => null,
            'content' => $noiDung,
            'transferType' => 'in',
            'transferAmount' => $soTien,
            'referenceCode' => 'TEST.0001',
        ];
    }

    /**
     * Gui y het cach SePay gui: than la chuoi JSON THO, chu ky tinh tren
     * chinh chuoi do.
     *
     * KHONG dung postJson() roi tu tinh chu ky tren mot chuoi khac - Laravel
     * co the sinh ra chuoi JSON khong giong het, va bai kiem tra se xanh trong
     * khi thuc te hong.
     */
    private function gui(array $goiTin, array $ghiDe = [])
    {
        $than = json_encode($goiTin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $moc = (string) ($ghiDe['moc'] ?? time());
        $secret = $ghiDe['secret'] ?? self::SECRET;

        $chuKy = $ghiDe['chuKy'] ?? ('sha256=' . hash_hmac('sha256', $moc . '.' . $than, $secret));

        $header = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if (!array_key_exists('bo_chu_ky', $ghiDe)) {
            $header['HTTP_X_SEPAY_SIGNATURE'] = $chuKy;
        }

        if (!array_key_exists('bo_moc', $ghiDe)) {
            $header['HTTP_X_SEPAY_TIMESTAMP'] = $moc;
        }

        return $this->call('POST', '/api/sepay/webhook', [], [], [], $header, $than);
    }

    // --- Duong di dung --------------------------------------------------------

    public function test_chu_ky_dung_thi_cap_license(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin("thanh toan {$don->code}"))->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
        $this->assertSame(1, License::count());
    }

    // --- Duong di sai ---------------------------------------------------------

    public function test_sai_khoa_bi_mat_thi_tu_choi(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin($don->code), ['secret' => 'khoa-bay'])->assertStatus(401);

        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    public function test_thieu_chu_ky_thi_tu_choi(): void
    {
        $this->gui($this->goiTin('abc'), ['bo_chu_ky' => true])->assertStatus(401);
    }

    public function test_thieu_moc_gio_thi_tu_choi(): void
    {
        $this->gui($this->goiTin('abc'), ['bo_moc' => true])->assertStatus(401);
    }

    public function test_moc_gio_khong_phai_so_thi_tu_choi(): void
    {
        $this->gui($this->goiTin('abc'), ['moc' => 'hom-qua'])->assertStatus(401);
    }

    /**
     * Doi mot ky tu trong than ma chu ky giu nguyen thi phai hong.
     *
     * Day moi la diem cua chu ky: no khong chi chung minh "dung nguoi gui" ma
     * con chung minh "noi dung chua bi sua". Thieu phep thu nay thi mot loi
     * kieu "ky tren chuoi da phan tich lai" se khong ai phat hien.
     */
    public function test_than_bi_sua_thi_chu_ky_khong_con_dung(): void
    {
        $don = $this->don();
        $goiTin = $this->goiTin($don->code);

        $than = json_encode($goiTin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $moc = (string) time();
        $chuKy = 'sha256=' . hash_hmac('sha256', $moc . '.' . $than, self::SECRET);

        // Ky tren goi tin dung gia, nhung gui di goi tin 1.000.000d.
        $goiTin['transferAmount'] = 1000000;
        $thanGia = json_encode($goiTin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->call('POST', '/api/sepay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => $chuKy,
            'HTTP_X_SEPAY_TIMESTAMP' => $moc,
        ], $thanGia)->assertStatus(401);

        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    // --- Chong phat lai -------------------------------------------------------

    /**
     * Goi tin cu, chu ky VAN DUNG, nhung qua han thi khong duoc nhan.
     *
     * Khong co buoc nay thi ai bat duoc mot goi tin hop le co the gui lai mai
     * mai - va moi lan gui lai la mot license.
     */
    public function test_goi_tin_qua_cu_thi_tu_choi(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin($don->code), ['moc' => (string) (time() - 600)])
            ->assertStatus(401);

        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    public function test_goi_tin_trong_han_nam_phut_van_duoc_nhan(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin($don->code), ['moc' => (string) (time() - 120)])->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    /** Dong ho may chu chay nhanh hon SePay mot chut cung phai chap nhan. */
    public function test_moc_gio_som_hon_mot_chut_van_duoc_nhan(): void
    {
        $don = $this->don();

        $this->gui($this->goiTin($don->code), ['moc' => (string) (time() + 60)])->assertOk();

        $this->assertSame(Order::DA_TRA, $don->fresh()->status);
    }

    // --- Chon kieu xac thuc ---------------------------------------------------

    /**
     * Dat SEPAY_WEBHOOK_AUTH=hmac thi API key KHONG duoc chap nhan nua.
     *
     * Neu khong thi chon kieu chat che chang co y nghia gi: ke tan cong chi
     * can bo header chu ky di va quay sang dung token.
     */
    public function test_che_do_hmac_thi_tu_choi_ca_api_key_dung(): void
    {
        config()->set('license.sepay.token', 'token-dung');

        $don = $this->don();

        $this->postJson('/api/sepay/webhook', $this->goiTin($don->code), [
            'Authorization' => 'Apikey token-dung',
        ])->assertStatus(401);
    }

    /** Che do 'any' thi ca hai kieu deu qua duoc. */
    public function test_che_do_any_thi_nhan_ca_hai_kieu(): void
    {
        config()->set('license.sepay.auth', 'any');
        config()->set('license.sepay.token', 'token-dung');

        $a = $this->don();
        $this->gui($this->goiTin($a->code))->assertOk();
        $this->assertSame(Order::DA_TRA, $a->fresh()->status);

        // Phai doi CA `id` LAN `referenceCode`: chong trung kiem ca hai khoa,
        // vi webhook va API cua SePay danh so giao dich theo hai kieu khac nhau.
        $b = $this->don();
        $this->postJson('/api/sepay/webhook', array_merge($this->goiTin($b->code), [
            'id' => 551235,
            'referenceCode' => 'TEST.0002',
        ]), [
            'Authorization' => 'Apikey token-dung',
        ])->assertOk();
        $this->assertSame(Order::DA_TRA, $b->fresh()->status);
    }

    // --- Loc theo IP ----------------------------------------------------------

    /**
     * Khai bao danh sach IP thi IP la bi chan NGAY, truoc ca khi kiem chu ky.
     */
    public function test_ip_ngoai_danh_sach_thi_chan_ngay(): void
    {
        config()->set('license.sepay.allowed_ips', ['172.236.138.20']);

        $don = $this->don();

        $this->gui($this->goiTin($don->code))->assertStatus(401);
        $this->assertSame(Order::CHUA_TRA, $don->fresh()->status);
    }

    public function test_de_trong_danh_sach_ip_thi_khong_loc(): void
    {
        config()->set('license.sepay.allowed_ips', []);

        $don = $this->don();

        $this->gui($this->goiTin($don->code))->assertOk();
    }
}

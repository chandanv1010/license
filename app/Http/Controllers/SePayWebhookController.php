<?php

namespace App\Http\Controllers;

use App\Services\ThanhToanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Nhan thong bao tien ve tu SePay.
 *
 * Tep nay chi lo HAI viec: xac thuc goi tin, va doi ten truong cua SePay sang
 * ten chung. Phan tim don - doi tien - cap license nam o ThanhToanService, vi
 * lenh doi soat (license:doi-soat) cung dung lai chinh no.
 *
 * BA DIEU PHAI DUNG, thieu mot la mat tien hoac mat khach:
 *
 *  1. XAC THUC. Thieu kiem tra token thi bat ky ai biet dia chi nay deu tu cap
 *     license cho minh - chi can doan ma don la xong.
 *  2. DO MA TRONG CHUOI, khong so sanh bang. Ngan hang chen rat nhieu chu vao
 *     noi dung chuyen khoan; xem chu thich o config/license.php.
 *  3. CHONG LAP. SePay gui lai webhook khi lan dau khong nhan duoc tra loi.
 *     Khong chong lap thi mot lan chuyen tien cap hai license.
 *
 * Luon tra ve 200 cho moi goi tin hop le ve xac thuc, ke ca khi khong khop don
 * nao: tra loi khac 200 se khien SePay gui lai mai mot giao dich khong bao gio
 * khop duoc.
 */
class SePayWebhookController extends Controller
{
    public function __construct(private readonly ThanhToanService $thanhToan)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $loi = $this->tuChoiVi($request);

        if ($loi !== null) {
            // Ghi du de chan doan lan tich hop dau tien ma KHONG lam lo bi mat:
            // chi ghi header nao CO MAT va 12 ky tu dau cua chu ky, khong ghi
            // token, khong ghi khoa.
            Log::warning('SePay webhook bi tu choi', [
                'ly_do' => $loi,
                'ip' => $request->ip(),
                'co_chu_ky' => $request->hasHeader('X-SePay-Signature'),
                'co_moc_gio' => $request->hasHeader('X-SePay-Timestamp'),
                'co_authorization' => $request->hasHeader('Authorization'),
                'chu_ky_dau' => substr((string) $request->header('X-SePay-Signature', ''), 0, 12),
                'moc_gio' => $request->header('X-SePay-Timestamp'),
                'gio_may_chu' => time(),
                'do_dai_than' => strlen($request->getContent()),
            ]);

            return response()->json(['success' => false, 'message' => $loi], 401);
        }

        $goiTin = $request->all();
        $sePayId = (string) ($goiTin['id'] ?? '');

        if ($sePayId === '') {
            return response()->json(['success' => false, 'message' => 'Thieu id giao dich.'], 422);
        }

        // Chi quan tam tien VAO. SePay bao ca giao dich ra neu tai khoan co
        // phat sinh chuyen di.
        if (($goiTin['transferType'] ?? 'in') !== 'in') {
            return response()->json(['success' => true, 'message' => 'Bo qua giao dich ra.']);
        }

        $kq = $this->thanhToan->ghiNhan([
            'sepay_id' => $sePayId,
            'reference_code' => $goiTin['referenceCode'] ?? null,
            'gateway' => $goiTin['gateway'] ?? null,
            'account_number' => $goiTin['accountNumber'] ?? null,
            'amount' => (int) ($goiTin['transferAmount'] ?? 0),
            'content' => trim((string) ($goiTin['content'] ?? '')),
            'transferred_at' => $goiTin['transactionDate'] ?? null,
            // SePay tu tach ma ra truong `code` khi dung mau QR cua ho.
            'code' => $goiTin['code'] ?? null,
            'raw' => $goiTin,
        ]);

        return response()->json(array_filter([
            'success' => true,
            'message' => $kq['loi_nhan'],
            'order' => $kq['order'] ?? null,
            'license' => $kq['license'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Ly do tu choi, hoac null khi goi tin hop le.
     *
     * Ba lop, theo dung thu tu re tien den dat tien: IP truoc, roi chu ky.
     */
    private function tuChoiVi(Request $request): ?string
    {
        if (!$this->dungIp($request)) {
            return 'IP khong nam trong danh sach cua SePay.';
        }

        $cach = config('license.sepay.auth', 'any');
        $coHmac = (string) config('license.sepay.secret') !== '';
        $coToken = (string) config('license.sepay.token') !== '';

        // Chua cau hinh gi ca thi TU CHOI het, khong phai cho qua het. Day la
        // loi trien khai kinh dien: quen dien bien moi truong tren may chu, va
        // duong webhook mo toang cho ca Internet.
        if (!$coHmac && !$coToken) {
            return 'May chu chua cau hinh xac thuc webhook.';
        }

        if ($cach === 'hmac') {
            return $this->dungChuKy($request) ? null : 'Chu ky khong hop le.';
        }

        if ($cach === 'apikey') {
            return $this->dungToken($request) ? null : 'Sai token.';
        }

        // 'any': cai nao duoc cau hinh thi thu cai do.
        if ($coHmac && $this->dungChuKy($request)) {
            return null;
        }

        if ($coToken && $this->dungToken($request)) {
            return null;
        }

        return 'Xac thuc khong hop le.';
    }

    private function dungIp(Request $request): bool
    {
        $danhSach = (array) config('license.sepay.allowed_ips', []);

        return $danhSach === [] || in_array($request->ip(), $danhSach, true);
    }

    /**
     * HMAC-SHA256 theo dung dac ta cua SePay.
     *
     *   chuoi ky  = "{timestamp}.{raw_body}"
     *   chu ky    = "sha256=" . hash_hmac('sha256', chuoi_ky, secret)
     *   headers   = X-SePay-Signature, X-SePay-Timestamp (giay)
     *
     * PHAI dung THAN GOC cua request. Lay $request->all() roi json_encode lai
     * se ra chuoi khac (thu tu khoa, khoang trang, cach thoat ky tu), va chu
     * ky khong bao gio khop.
     */
    private function dungChuKy(Request $request): bool
    {
        $secret = (string) config('license.sepay.secret');

        if ($secret === '') {
            return false;
        }

        $chuKy = trim((string) $request->header('X-SePay-Signature', ''));
        $moc = trim((string) $request->header('X-SePay-Timestamp', ''));

        if ($chuKy === '' || $moc === '' || !ctype_digit($moc)) {
            return false;
        }

        // Chong phat lai: ke bat duoc mot goi tin hop le khong dung lai duoc no
        // sau nam phut. Khong co buoc nay thi chu ky dung mai mai.
        $lech = abs(time() - (int) $moc);

        if ($lech > (int) config('license.sepay.skew', 300)) {
            return false;
        }

        $mong = 'sha256=' . hash_hmac('sha256', $moc . '.' . $request->getContent(), $secret);

        return hash_equals($mong, $chuKy);
    }

    /**
     * SePay gui token o header Authorization dang "Apikey <token>".
     */
    private function dungToken(Request $request): bool
    {
        $mong = (string) config('license.sepay.token');

        if ($mong === '') {
            return false;
        }

        $nhan = (string) $request->header('Authorization', '');
        $nhan = preg_replace('/^Apikey\s+/i', '', trim($nhan));

        return hash_equals($mong, (string) $nhan);
    }


}

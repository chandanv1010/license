<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\LicenseDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Duong dan extension goi toi de hoi "key nay con dung duoc khong".
 *
 * Extension PHAI nho ket qua va chap nhan song offline vai ngay: nguoi dung
 * mat mang, hay may chu cua anh sap, khong phai ly do de ho mat thu da tra
 * tien. Phan do nam o phia extension, xem background.js.
 */
class LicenseController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $duLieu = $request->validate([
            'key' => 'required|string|max:64',
            'device_id' => 'required|string|max:64',
            'product' => 'nullable|string|max:32',
        ]);

        $license = License::where('key', strtoupper(trim($duLieu['key'])))->first();

        if (!$license) {
            return $this->tuChoi('not_found', 'Mã kích hoạt không tồn tại.');
        }

        if (isset($duLieu['product']) && $license->product !== $duLieu['product']) {
            return $this->tuChoi('wrong_product', 'Mã này của sản phẩm khác.');
        }

        if ($license->biThuHoi()) {
            return $this->tuChoi('revoked', 'Mã kích hoạt đã bị thu hồi.');
        }

        if (!$license->conHan()) {
            return $this->tuChoi('expired', 'Mã kích hoạt đã hết hạn.', [
                'expires_at' => $license->expires_at->toIso8601String(),
            ]);
        }

        // Bam id may truoc khi luu. Id do extension sinh ra va la du lieu cua
        // nguoi dung; bam de CSDL cua anh khong giu thu khong can giu.
        $mayNay = hash('sha256', $duLieu['device_id']);
        $daCo = $license->devices()->where('device_hash', $mayNay)->first();

        if (!$daCo && $license->devices()->count() >= $license->max_devices) {
            $this->nhuongCho($license);
        }

        if (!$daCo && $license->devices()->count() >= $license->max_devices) {
            return $this->tuChoi('device_limit', "Mã này đang dùng trên {$license->max_devices} máy. Hãy liên hệ để gỡ bớt một máy.", [
                'devices' => $license->devices()->count(),
                'max_devices' => $license->max_devices,
            ]);
        }

        LicenseDevice::updateOrCreate(
            ['license_id' => $license->id, 'device_hash' => $mayNay],
            ['last_seen_at' => now()],
        );

        return response()->json([
            'ok' => true,
            'status' => 'active',
            'product' => $license->product,
            'expires_at' => $license->expires_at->toIso8601String(),
            'days_left' => $license->soNgayConLai(),
            'devices' => $license->devices()->count(),
            'max_devices' => $license->max_devices,
        ]);
    }

    /**
     * Nha suat cua nhung may da lau khong thay mat.
     *
     * Id may do extension sinh ra va nam trong chrome.storage: cai lai tien
     * ich, doi may, hay don dep trinh duyet la sinh id MOI. May cu bien mat
     * vinh vien nhung van giu suat.
     *
     * Khong nha thi nguoi mua THAT bi khoa o lan doi may thu tu - con ke chia
     * se key thi khong anh huong gi, ho chi can ba nguoi la day cho. Nguoi
     * dung that bi khoa la mat khach; ke chia se key von da khong tra tien.
     *
     * May dang dung thi KHONG bi nha: extension hoi lai may chu moi 24 gio
     * nen `last_seen_at` luon moi.
     */
    private function nhuongCho(License $license): void
    {
        $ngay = (int) config('license.device_idle_days', 14);

        if ($ngay <= 0) {
            return;
        }

        $moc = now()->subDays($ngay);

        $cu = $license->devices()
            ->where(function ($truyVan) use ($moc) {
                $truyVan->where('last_seen_at', '<', $moc)->orWhereNull('last_seen_at');
            })
            ->pluck('id');

        if ($cu->isEmpty()) {
            return;
        }

        LicenseDevice::whereIn('id', $cu)->delete();

        Log::info('Nha suat may da lau khong dung', [
            'license' => $license->id,
            'so_may' => $cu->count(),
            'khong_dung_qua' => $ngay . ' ngay',
        ]);
    }

    private function tuChoi(string $trangThai, string $loiNhan, array $them = []): JsonResponse
    {
        // 200 chu khong phai 4xx: day la cau tra loi hop le cho mot cau hoi hop
        // le. Tra 4xx thi phia extension phai phan biet "may chu hong" voi "key
        // sai" bang cach doan ma loi - va doan sai thi khoa nham nguoi da tra
        // tien.
        return response()->json(array_merge([
            'ok' => false,
            'status' => $trangThai,
            'message' => $loiNhan,
        ], $them));
    }
}

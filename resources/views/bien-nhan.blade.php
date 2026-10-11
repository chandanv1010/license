<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Biên nhận {{ $don->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 28px 16px 60px;
            font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9; color: #13224a;
        }
        .to {
            max-width: 580px; margin: 0 auto; padding: 32px 30px;
            background: #fff; border-radius: 14px;
            box-shadow: 0 10px 34px rgba(11, 40, 80, .10);
        }
        .dinh { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        h1 { margin: 0 0 2px; font-size: 20px; }
        .nhe { color: #8e929e; font-size: 13px; }
        .dau {
            padding: 4px 12px; border-radius: 20px;
            background: #eafaf0; color: #14803c; font-size: 12.5px; font-weight: 700;
            white-space: nowrap;
        }
        table { width: 100%; margin-top: 24px; border-collapse: collapse; font-size: 14.5px; }
        td { padding: 9px 0; border-bottom: 1px solid #eef1f4; vertical-align: top; }
        td:first-child { color: #6e7991; width: 42%; }
        td:last-child { text-align: right; font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        .tong td { padding-top: 16px; border-top: 2px solid #13224a; border-bottom: 0; font-size: 17px; }
        code { font: 14px/1.4 "Courier New", monospace; }
        .chan {
            margin: 26px 0 0; padding-top: 16px; border-top: 1px solid #eef1f4;
            color: #8e929e; font-size: 12.5px; line-height: 1.65;
        }
        .in {
            display: block; max-width: 580px; margin: 0 auto 14px; text-align: right;
        }
        .in button {
            padding: 9px 16px; font: inherit; font-size: 13.5px;
            border: 1px solid #d8dee6; border-radius: 8px;
            background: #fff; color: #13224a; cursor: pointer;
        }
        .in button:hover { border-color: #0a78f5; color: #0a78f5; }

        /* In ra giay thi bo nut va bo nen - muc in dat hon nguoi ta tuong. */
        @media print {
            body { padding: 0; background: #fff; }
            .to { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            .in { display: none; }
        }
    </style>
</head>
<body>

<div class="in">
    <button type="button" onclick="window.print()">In / Lưu PDF</button>
</div>

<div class="to">
    <div class="dinh">
        <div>
            <h1>Biên nhận thanh toán</h1>
            <p class="nhe" style="margin:0">Số <code>{{ $don->code }}</code></p>
        </div>
        <span class="dau">ĐÃ THANH TOÁN</span>
    </div>

    <table>
        <tr>
            <td>Bên bán</td>
            <td>
                {{ $ben['name'] }}
                @if($ben['tax_code'])
                    <br><span class="nhe">MST {{ $ben['tax_code'] }}</span>
                @endif
                @if($ben['email'])
                    <br><span class="nhe">{{ $ben['email'] }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Bên mua</td>
            <td>{{ $don->email ?: 'Không cung cấp' }}</td>
        </tr>
        <tr>
            <td>Ngày thanh toán</td>
            <td>{{ $don->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td>Hình thức</td>
            <td>Chuyển khoản ngân hàng</td>
        </tr>
        <tr>
            <td>Nội dung</td>
            <td>
                {{ $tenSanPham }} — {{ $goi['label'] ?? $don->plan }}
                @if($don->laGiaHan())
                    <br><span class="nhe">Gia hạn, cộng thêm {{ $goi['days'] ?? 0 }} ngày</span>
                @endif
            </td>
        </tr>
        @if($license)
            <tr>
                <td>Mã kích hoạt</td>
                <td><code>{{ $license->key }}</code></td>
            </tr>
            <tr>
                <td>Hạn dùng đến</td>
                <td>{{ $license->expires_at->format('d/m/Y') }}</td>
            </tr>
        @endif
        <tr class="tong">
            <td>Tổng cộng</td>
            <td>{{ number_format($don->amount, 0, ',', '.') }}đ</td>
        </tr>
    </table>

    {{--
        Noi ro day KHONG phai hoa don do. Khach doanh nghiep cam cai nay di
        quyet toan thue roi bi tra lai thi do la loi cua minh, khong phai cua ho.
    --}}
    <p class="chan">
        Đây là biên nhận do bên bán tự phát hành, <b>không phải hoá đơn giá trị gia tăng</b>.
        Cần hoá đơn đỏ thì liên hệ{{ $ben['email'] ? ' ' . $ben['email'] : ' chúng tôi' }}.
        @if($ben['site'])
            <br>{{ $ben['site'] }}
        @endif
    </p>
</div>

</body>
</html>

<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"></head>
{{--
    Thu dien tu khong dung duoc CSS ngoai: Gmail, Outlook deu cat the <style>.
    Nen moi thuoc tinh phai viet thang vao the, du nhin xau trong ma nguon.
--}}
<body style="margin:0;padding:24px;background:#f4f6f9;font:15px/1.6 Arial,Helvetica,sans-serif;color:#13224a">
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:28px 26px">

    <h1 style="margin:0 0 16px;font-size:20px">
        @if($kieu === 'nhac')
            Mã kích hoạt sắp hết hạn
        @elseif($kieu === 'tra-cuu')
            Mã kích hoạt của bạn
        @elseif($kieu === 'gia-han')
            Đã gia hạn xong
        @else
            Cảm ơn bạn đã mua {{ $tenSanPham }}
        @endif
    </h1>

    <p style="margin:0 0 18px;color:#4b5563">
        @if($kieu === 'nhac')
            Mã kích hoạt {{ $tenSanPham }} của bạn còn <b>{{ $soNgay }} ngày</b> là hết hạn.
            Gia hạn để không bị gián đoạn.
        @elseif($kieu === 'tra-cuu')
            Đây là mã kích hoạt {{ $tenSanPham }} gắn với địa chỉ email này.
        @elseif($kieu === 'gia-han')
            Mã của bạn <b>vẫn giữ nguyên</b> — chúng tôi chỉ cộng thêm hạn.
            Không phải nhập lại ở máy nào cả.
        @else
            Dán mã dưới đây vào ô <b>Mã kích hoạt</b> trong extension là dùng được ngay.
        @endif
    </p>

    <div style="padding:16px;border:1px solid #bfe8cd;border-radius:9px;background:#f3fbf6;text-align:center">
        <code style="font:700 18px/1.4 'Courier New',monospace;letter-spacing:1px;color:#14803c">{{ $license->key }}</code>
    </div>

    <table style="width:100%;margin-top:18px;border-collapse:collapse;font-size:14px">
        <tr>
            <td style="padding:7px 0;color:#6e7991;border-bottom:1px solid #eef1f4">Sản phẩm</td>
            <td style="padding:7px 0;text-align:right;font-weight:600;border-bottom:1px solid #eef1f4">{{ $tenSanPham }}</td>
        </tr>
        <tr>
            <td style="padding:7px 0;color:#6e7991;border-bottom:1px solid #eef1f4">Hạn dùng</td>
            <td style="padding:7px 0;text-align:right;font-weight:600;border-bottom:1px solid #eef1f4">{{ $license->expires_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td style="padding:7px 0;color:#6e7991">Số máy tối đa</td>
            <td style="padding:7px 0;text-align:right;font-weight:600">{{ $license->max_devices }} máy</td>
        </tr>
    </table>

    @if($kieu === 'nhac')
        <p style="margin:20px 0 0;text-align:center">
            <a href="{{ url('/mua/' . $license->product) . '?key=' . $license->key }}"
               style="display:inline-block;padding:12px 22px;border-radius:9px;background:#0a78f5;color:#fff;font-weight:700;text-decoration:none">
                Gia hạn ngay
            </a>
        </p>
    @endif

    @if($bienNhan)
        <p style="margin:18px 0 0;text-align:center">
            <a href="{{ $bienNhan }}" style="color:#0a78f5;font-size:13.5px">Xem / in biên nhận</a>
        </p>
    @endif

    {{-- Noi truoc de khach khoi hoang khi doi may. --}}
    <p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #eef1f4;color:#8e929e;font-size:12.5px;line-height:1.6">
        Giữ lại thư này. Mất mã thì vào
        <a href="{{ url('/tra-cuu') }}" style="color:#0a78f5">trang tra cứu</a>
        nhập đúng email này, chúng tôi gửi lại.
    </p>
</div>
</body>
</html>

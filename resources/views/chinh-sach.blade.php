<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chính sách riêng tư — {{ config('license.products.nopause.name', 'NoPause Playback') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; padding: 32px 16px 70px;
            font: 16px/1.72 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9; color: #13224a;
        }
        .to {
            max-width: 680px; margin: 0 auto; padding: 36px 34px;
            background: #fff; border-radius: 14px;
            box-shadow: 0 10px 34px rgba(11, 40, 80, .10);
        }
        h1 { margin: 0 0 6px; font-size: 25px; }
        h2 { margin: 30px 0 10px; font-size: 18px; }
        .nhe { color: #8e929e; font-size: 13.5px; }
        ul { padding-left: 22px; }
        li { margin-bottom: 7px; }
        a { color: #0a78f5; }
        .hop {
            margin: 18px 0; padding: 14px 16px;
            border-left: 4px solid #0a78f5; border-radius: 0 9px 9px 0;
            background: #f2f8ff;
        }
    </style>
</head>
<body>
<div class="to">
    <h1>Chính sách riêng tư</h1>
    <p class="nhe">{{ config('license.products.nopause.name', 'NoPause Playback') }} — cập nhật {{ now()->format('d/m/Y') }}</p>

    <div class="hop">
        <b>Tóm tắt:</b> Tiện ích không thu thập lịch sử duyệt web, không đọc nội dung
        trang, không theo dõi bạn, và không bán dữ liệu cho bất kỳ ai.
    </div>

    <h2>Tiện ích lưu những gì</h2>
    <p>Tất cả đều nằm trong <code>chrome.storage</code> <b>trên máy bạn</b>:</p>
    <ul>
        <li>Danh sách tên miền bạn đã bật tiện ích, và lựa chọn bật/tắt từng cơ chế.</li>
        <li>Số lượt đã dùng thử trên mỗi tên miền.</li>
        <li>Mã kích hoạt, nếu bạn đã mua.</li>
        <li>Một chuỗi ngẫu nhiên nhận diện trình duyệt này, sinh ra lúc cài đặt.
            Chuỗi đó không gắn với tên, email hay bất cứ thứ gì về bạn.</li>
    </ul>

    <h2>Tiện ích gửi những gì ra ngoài</h2>
    <p>Chỉ một việc duy nhất, và chỉ khi bạn đã nhập mã kích hoạt: hỏi máy chủ xem
        mã còn hiệu lực không. Lần gọi đó mang theo <b>mã kích hoạt</b> và
        <b>chuỗi nhận diện trình duyệt</b> — không có gì khác. Mặc định 24 giờ một lần.</p>
    <p>Chuỗi nhận diện được <b>băm</b> trước khi lưu vào cơ sở dữ liệu, nên chính
        chúng tôi cũng không đọc lại được giá trị gốc.</p>

    <h2>Vì sao tiện ích xin quyền truy cập mọi trang web</h2>
    <p>Việc của tiện ích là ngăn trình duyệt tạm dừng video khi bạn chuyển sang
        cửa sổ khác. Muốn làm được thì phải chạy <b>trên chính trang đang phát
        video</b> — và vì bạn có thể xem video ở bất kỳ trang nào, quyền phải là
        mọi trang.</p>
    <p>Tiện ích chỉ can thiệp vào các tín hiệu "cửa sổ mất tiêu điểm" và chỉ ở
        những tên miền <b>bạn đã tự bật</b>. Tiện ích không đọc nội dung trang,
        không đọc ô nhập liệu, không chụp màn hình, không chạm vào mật khẩu.</p>

    <h2>Nếu bạn mua</h2>
    <p>Chúng tôi lưu địa chỉ email bạn cung cấp (để gửi mã và nhắc gia hạn) cùng
        thông tin giao dịch do ngân hàng gửi sang. Chúng tôi <b>không</b> nhận và
        <b>không</b> lưu số thẻ của bạn — toàn bộ việc chuyển tiền diễn ra trong
        ứng dụng ngân hàng của bạn.</p>
    <p>Email chỉ dùng cho hai việc trên. Không gửi quảng cáo, không chia sẻ cho
        bên thứ ba.</p>

    <h2>Xoá dữ liệu</h2>
    <p>Gỡ tiện ích là xoá sạch phần trên máy. Muốn xoá cả email và dữ liệu đơn
        hàng ở máy chủ thì viết cho
        @if(config('license.seller.email'))
            <a href="mailto:{{ config('license.seller.email') }}">{{ config('license.seller.email') }}</a>
        @else
            chúng tôi
        @endif
        — chúng tôi xoá trong vòng 30 ngày. Riêng chứng từ thanh toán phải giữ
        theo quy định về kế toán.</p>

    <h2>Liên hệ</h2>
    <p>
        {{ config('license.seller.name') }}
        @if(config('license.seller.email'))
            — <a href="mailto:{{ config('license.seller.email') }}">{{ config('license.seller.email') }}</a>
        @endif
    </p>
</div>
</body>
</html>

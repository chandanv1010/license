<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tra cứu mã kích hoạt</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px 16px 48px;
            font: 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9; color: #13224a;
        }
        .hop {
            max-width: 420px; margin: 0 auto; padding: 26px 24px 24px;
            border-radius: 14px; background: #fff;
            box-shadow: 0 10px 34px rgba(11, 40, 80, .10);
        }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .phu { margin: 0 0 20px; color: #6e7991; font-size: 14px; }
        label { display: block; margin-bottom: 6px; font-size: 13.5px; font-weight: 600; }
        input {
            width: 100%; padding: 11px 13px; font: inherit;
            border: 1px solid #d8dee6; border-radius: 9px; background: #fff; color: inherit;
        }
        input:focus { border-color: #0a78f5; outline: 2px solid rgba(10,120,245,.16); }
        button {
            width: 100%; margin-top: 14px; padding: 13px; font: inherit; font-weight: 700;
            border: 0; border-radius: 9px; background: #0a78f5; color: #fff; cursor: pointer;
        }
        button:hover { background: #0b6ee2; }
        .bao { margin: 0 0 16px; padding: 11px 13px; border-radius: 9px; font-size: 13.5px; }
        .bao--ok { background: #eafaf0; color: #14803c; }
        .bao--loi { background: #fdecec; color: #dc2626; }
        .duoi { margin: 16px 0 0; font-size: 12.5px; color: #8e929e; text-align: center; }
        .duoi a { color: #0a78f5; }
    </style>
</head>
<body>
<div class="hop">
    <h1>Tra cứu mã kích hoạt</h1>
    <p class="phu">Nhập email bạn đã dùng khi mua. Chúng tôi gửi lại mã vào hòm thư đó.</p>

    @if(session('nx_success'))
        <div class="bao bao--ok">{{ session('nx_success') }}</div>
    @endif

    @error('email')
        <div class="bao bao--loi">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('tra-cuu.gui') }}">
        @csrf
        <label for="email">Email đã mua</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}"
               placeholder="ban@example.com" required autocomplete="email">
        <button type="submit">Gửi lại mã cho tôi</button>
    </form>

    {{-- Noi truoc de khach khoi ngoi doi mot buc thu khong bao gio den. --}}
    <p class="duoi">
        Mua lúc không điền email thì không tra cứu được —
        <a href="{{ url('/mua/nopause') }}">mua mã mới</a>.
    </p>
</div>
</body>
</html>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Trang quan tri khong co ly do gi de nam trong ket qua tim kiem. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('ten', 'Quản trị') — License</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; font: 14px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f4f6f9; color: #13224a;
        }
        a { color: #0a78f5; }

        .thanh {
            display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
            padding: 0 18px; background: #13224a; color: #fff;
        }
        .thanh .hieu { margin-right: 14px; padding: 13px 0; font-weight: 700; color: #fff; text-decoration: none; }
        .thanh a.muc {
            padding: 13px 12px; color: #b9c4dd; text-decoration: none;
            border-bottom: 2px solid transparent;
        }
        .thanh a.muc:hover { color: #fff; }
        .thanh a.muc.dang { color: #fff; border-bottom-color: #4e9bff; }
        .thanh form { margin-left: auto; }
        .thanh button {
            padding: 7px 13px; font: inherit; border: 1px solid #3c4c74;
            border-radius: 7px; background: transparent; color: #b9c4dd; cursor: pointer;
        }
        .thanh button:hover { color: #fff; border-color: #6b7da8; }

        .than { max-width: 1120px; margin: 0 auto; padding: 22px 18px 60px; }
        h1 { margin: 0 0 18px; font-size: 21px; }
        h2 { margin: 26px 0 12px; font-size: 16px; }

        .o-tin { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .o {
            padding: 15px 16px; border-radius: 11px; background: #fff;
            box-shadow: 0 2px 10px rgba(11, 40, 80, .06);
        }
        .o .nhan { margin: 0 0 5px; color: #6e7991; font-size: 12.5px; }
        .o .so { font-size: 23px; font-weight: 700; }
        .o.canh { border-left: 4px solid #e8553a; }
        .o.canh .so { color: #d93b1e; }

        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 11px; overflow: hidden; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eef1f4; vertical-align: top; }
        th { background: #f7f9fc; font-size: 12.5px; color: #6e7991; font-weight: 600; white-space: nowrap; }
        td.phai, th.phai { text-align: right; }
        tr:last-child td { border-bottom: 0; }
        .trong { padding: 26px; text-align: center; color: #8e929e; background: #fff; border-radius: 11px; }

        code, .ma { font: 13px/1.4 "Courier New", monospace; }

        .nhan-tt {
            display: inline-block; padding: 2px 9px; border-radius: 20px;
            font-size: 12px; font-weight: 600; white-space: nowrap;
        }
        .tt-paid { background: #eafaf0; color: #14803c; }
        .tt-pending { background: #fff5e6; color: #a9641b; }
        .tt-xau { background: #fdecec; color: #d93b1e; }
        .tt-nhe { background: #eef1f4; color: #6e7991; }

        form.hang { display: inline; }
        button.nut, .nut {
            padding: 5px 11px; font: inherit; font-size: 12.5px;
            border: 1px solid #d8dee6; border-radius: 7px;
            background: #fff; color: #13224a; cursor: pointer; text-decoration: none;
            display: inline-block;
        }
        button.nut:hover { border-color: #0a78f5; color: #0a78f5; }
        button.nut.xau:hover { border-color: #d93b1e; color: #d93b1e; }
        input[type=text], input[type=number], input[type=email], input[type=password], select {
            padding: 7px 10px; font: inherit; border: 1px solid #d8dee6;
            border-radius: 7px; background: #fff; color: inherit;
        }
        input:focus, select:focus { border-color: #0a78f5; outline: 2px solid rgba(10,120,245,.16); }

        .loc { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }

        .bao { margin: 0 0 16px; padding: 11px 14px; border-radius: 9px; font-size: 13.5px; }
        .bao--ok { background: #eafaf0; color: #14803c; }
        .bao--loi { background: #fdecec; color: #d93b1e; }

        .nhe { color: #8e929e; font-size: 12.5px; }
        .trang { margin-top: 16px; }
        .trang nav { display: flex; gap: 6px; flex-wrap: wrap; }
    </style>
</head>
<body>

@auth
    <nav class="thanh">
        <a class="hieu" href="{{ route('admin.tong-quan') }}">License</a>
        @php $hienTai = request()->route()?->getName(); @endphp
        <a class="muc {{ $hienTai === 'admin.tong-quan' ? 'dang' : '' }}" href="{{ route('admin.tong-quan') }}">Tổng quan</a>
        <a class="muc {{ $hienTai === 'admin.don-hang' ? 'dang' : '' }}" href="{{ route('admin.don-hang') }}">Đơn hàng</a>
        <a class="muc {{ $hienTai === 'admin.giao-dich' ? 'dang' : '' }}" href="{{ route('admin.giao-dich') }}">Giao dịch</a>
        <a class="muc {{ $hienTai === 'admin.license' ? 'dang' : '' }}" href="{{ route('admin.license') }}">License</a>
        <form method="POST" action="{{ route('admin.dang-xuat') }}">
            @csrf
            <button type="submit">Thoát</button>
        </form>
    </nav>
@endauth

<div class="than">
    @if(session('nx_ok'))
        <div class="bao bao--ok">{{ session('nx_ok') }}</div>
    @endif
    @if(session('nx_loi'))
        <div class="bao bao--loi">{{ session('nx_loi') }}</div>
    @endif

    @yield('noi-dung')
</div>

</body>
</html>

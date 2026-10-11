@extends('admin.khung')
@section('ten', 'Đăng nhập')

@section('noi-dung')
    <div style="max-width:380px;margin:6vh auto 0;padding:26px 24px;background:#fff;border-radius:14px;box-shadow:0 10px 34px rgba(11,40,80,.10)">
        <h1 style="margin:0 0 4px;font-size:20px">Quản trị License</h1>
        <p class="nhe" style="margin:0 0 20px">Tạo tài khoản bằng <code>php artisan license:admin</code></p>

        @error('email')
            <div class="bao bao--loi">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('admin.dang-nhap') }}">
            @csrf

            <label for="email" style="display:block;margin-bottom:6px;font-weight:600;font-size:13.5px">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   style="width:100%" required autofocus autocomplete="username">

            <label for="password" style="display:block;margin:14px 0 6px;font-weight:600;font-size:13.5px">Mật khẩu</label>
            <input type="password" id="password" name="password"
                   style="width:100%" required autocomplete="current-password">

            <label style="display:flex;align-items:center;gap:7px;margin:14px 0 0;font-size:13.5px;color:#6e7991">
                <input type="checkbox" name="nho" value="1"> Ghi nhớ trên máy này
            </label>

            <button type="submit" style="width:100%;margin-top:16px;padding:12px;font:inherit;font-weight:700;border:0;border-radius:9px;background:#0a78f5;color:#fff;cursor:pointer">
                Đăng nhập
            </button>
        </form>
    </div>
@endsection

@extends('admin.khung')
@section('ten', 'Đơn hàng')

@section('noi-dung')
    <h1>Đơn hàng</h1>

    <form method="GET" class="loc">
        <input type="text" name="tim" value="{{ $tim }}" placeholder="Mã đơn hoặc email">
        <select name="trang_thai">
            <option value="">Tất cả trạng thái</option>
            <option value="paid" @selected($trangThai === 'paid')>Đã trả</option>
            <option value="pending" @selected($trangThai === 'pending')>Chờ thanh toán</option>
        </select>
        <button type="submit" class="nut">Lọc</button>
        @if($tim || $trangThai)
            <a href="{{ route('admin.don-hang') }}" class="nut">Bỏ lọc</a>
        @endif
    </form>

    @if($danhSach->isEmpty())
        <div class="trong">Không có đơn nào khớp.</div>
    @else
        <table>
            <tr>
                <th>Mã đơn</th><th>Lúc</th><th>Email</th>
                <th class="phai">Số tiền</th><th>Trạng thái</th><th>Mã kích hoạt</th>
            </tr>
            @foreach($danhSach as $don)
                @include('admin.phan.dong-don', ['don' => $don])
            @endforeach
        </table>

        <div class="trang">{{ $danhSach->links('phan-trang') }}</div>
    @endif
@endsection

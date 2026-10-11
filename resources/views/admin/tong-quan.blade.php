@extends('admin.khung')
@section('ten', 'Tổng quan')

@section('noi-dung')
    <h1>Tổng quan</h1>

    <div class="o-tin">
        <div class="o">
            <p class="nhan">Doanh thu tháng này</p>
            <div class="so">{{ number_format($doanhThuThang, 0, ',', '.') }}đ</div>
        </div>
        <div class="o">
            <p class="nhan">Đơn đã trả tháng này</p>
            <div class="so">{{ $donThang }}</div>
        </div>
        <div class="o">
            <p class="nhan">License đang dùng</p>
            <div class="so">{{ $dangDung }}</div>
        </div>
        <div class="o">
            <p class="nhan">Hết hạn trong 30 ngày</p>
            <div class="so">{{ $sapHet }}</div>
        </div>

        {{--
            O nay quan trong nhat tren ca trang. Moi dong trong do la mot nguoi
            DA CHUYEN TIEN ma chua nhan duoc hang - ho dang ngoi doi, va neu
            doi lau qua thi ho nghi minh lua dao.
        --}}
        <div class="o {{ $chuaKhop > 0 ? 'canh' : '' }}">
            <p class="nhan">Giao dịch chưa khớp đơn</p>
            <div class="so">{{ $chuaKhop }}</div>
        </div>
    </div>

    @if($giaoDichLo->isNotEmpty())
        <h2>Giao dịch chưa khớp — cần xử lý tay</h2>
        <p class="nhe" style="margin:-6px 0 12px">
            Thường là khách gõ sai nội dung chuyển khoản. Tìm đơn của họ rồi gắn vào.
        </p>

        <table>
            <tr>
                <th>Lúc</th><th class="phai">Số tiền</th><th>Nội dung</th><th>Gắn vào đơn</th>
            </tr>
            @foreach($giaoDichLo as $gd)
                <tr>
                    <td class="nhe" style="white-space:nowrap">{{ $gd->created_at->format('d/m H:i') }}</td>
                    <td class="phai"><b>{{ number_format($gd->amount, 0, ',', '.') }}đ</b></td>
                    <td class="ma" style="font-size:12px">{{ \Illuminate\Support\Str::limit($gd->content, 90) }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.gan-giao-dich', $gd) }}" class="hang">
                            @csrf
                            <input type="text" name="ma_don" placeholder="LXXXXXXX" size="10" required
                                   style="text-transform:uppercase">
                            <button type="submit" class="nut">Gắn</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Đơn gần đây</h2>
    @if($donMoi->isEmpty())
        <div class="trong">Chưa có đơn nào.</div>
    @else
        <table>
            <tr>
                <th>Mã đơn</th><th>Lúc</th><th>Email</th>
                <th class="phai">Số tiền</th><th>Trạng thái</th><th>Mã kích hoạt</th>
            </tr>
            @foreach($donMoi as $don)
                @include('admin.phan.dong-don', ['don' => $don])
            @endforeach
        </table>
    @endif
@endsection

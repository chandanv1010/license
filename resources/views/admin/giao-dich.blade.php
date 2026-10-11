@extends('admin.khung')
@section('ten', 'Giao dịch')

@section('noi-dung')
    <h1>Giao dịch</h1>
    <p class="nhe" style="margin:-10px 0 14px">
        Mọi giao dịch tiền vào đều được lưu, kể cả khi không khớp đơn nào —
        để khách kêu "tôi chuyển rồi" thì còn chỗ mà tra.
    </p>

    <form method="GET" class="loc">
        <input type="text" name="tim" value="{{ $tim }}" placeholder="Tìm trong nội dung chuyển khoản">
        <select name="loc">
            <option value="">Tất cả</option>
            <option value="chua-khop" @selected($loc === 'chua-khop')>Chưa khớp đơn</option>
        </select>
        <button type="submit" class="nut">Lọc</button>
        @if($tim || $loc)
            <a href="{{ route('admin.giao-dich') }}" class="nut">Bỏ lọc</a>
        @endif
    </form>

    @if($danhSach->isEmpty())
        <div class="trong">Không có giao dịch nào khớp.</div>
    @else
        <table>
            <tr>
                <th>Lúc</th><th class="phai">Số tiền</th><th>Nội dung</th>
                <th>Đơn</th><th>Ghi chú</th>
            </tr>
            @foreach($danhSach as $gd)
                <tr>
                    <td class="nhe" style="white-space:nowrap">{{ $gd->created_at->format('d/m/Y H:i') }}</td>
                    <td class="phai"><b>{{ number_format($gd->amount, 0, ',', '.') }}đ</b></td>
                    <td class="ma" style="font-size:12px;max-width:380px">{{ $gd->content }}</td>
                    <td>
                        @if($gd->order)
                            <code>{{ $gd->order->code }}</code>
                        @else
                            {{-- Gan tay: thuong la khach go sai noi dung chuyen khoan. --}}
                            <form method="POST" action="{{ route('admin.gan-giao-dich', $gd) }}" class="hang">
                                @csrf
                                <input type="text" name="ma_don" placeholder="LXXXXXXX" size="9" required
                                       style="text-transform:uppercase">
                                <button type="submit" class="nut">Gắn</button>
                            </form>
                        @endif
                    </td>
                    <td class="nhe">{{ $gd->note }}</td>
                </tr>
            @endforeach
        </table>

        <div class="trang">{{ $danhSach->links('phan-trang') }}</div>
    @endif
@endsection

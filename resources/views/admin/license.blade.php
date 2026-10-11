@extends('admin.khung')
@section('ten', 'License')

@section('noi-dung')
    <h1>License</h1>

    <form method="GET" class="loc">
        <input type="text" name="tim" value="{{ $tim }}" placeholder="Mã kích hoạt hoặc email" size="34">
        <button type="submit" class="nut">Tìm</button>
        @if($tim)
            <a href="{{ route('admin.license') }}" class="nut">Bỏ lọc</a>
        @endif
    </form>

    @if($danhSach->isEmpty())
        <div class="trong">Không có mã nào khớp.</div>
    @else
        <table>
            <tr>
                <th>Mã kích hoạt</th><th>Email</th><th>Hạn</th>
                <th>Máy</th><th>Việc</th>
            </tr>
            @foreach($danhSach as $l)
                <tr>
                    <td>
                        <code>{{ $l->key }}</code><br>
                        @if($l->biThuHoi())
                            <span class="nhan-tt tt-xau">đã thu hồi</span>
                        @elseif(!$l->conHan())
                            <span class="nhan-tt tt-pending">hết hạn</span>
                        @else
                            <span class="nhan-tt tt-paid">đang dùng</span>
                        @endif
                    </td>
                    <td>
                        {{ $l->email ?: '—' }}
                        @if($l->order)
                            <br><span class="nhe">đơn {{ $l->order->code }}</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        {{ $l->expires_at->format('d/m/Y') }}<br>
                        <span class="nhe">{{ $l->conHan() ? 'còn ' . $l->soNgayConLai() . ' ngày' : 'đã qua' }}</span>
                    </td>
                    <td>
                        <span class="nhe">{{ $l->devices->count() }}/{{ $l->max_devices }}</span>
                        @foreach($l->devices as $may)
                            <div style="margin-top:4px">
                                {{-- Chi hien 8 ky tu dau cua bam: du de phan biet hai may, khong
                                     bay gio dung duoc de lam gi khac. --}}
                                <code style="font-size:11px">{{ substr($may->device_hash, 0, 8) }}</code>
                                <span class="nhe">{{ $may->last_seen_at?->diffForHumans() ?? 'chưa rõ' }}</span>
                                <form method="POST" action="{{ route('admin.go-may', [$l, $may]) }}" class="hang">
                                    @csrf
                                    <button type="submit" class="nut xau" title="Gỡ máy này">×</button>
                                </form>
                            </div>
                        @endforeach
                        @if($l->devices->count() > 1)
                            <form method="POST" action="{{ route('admin.go-het-may', $l) }}" class="hang">
                                @csrf
                                <button type="submit" class="nut xau" style="margin-top:6px">Gỡ hết</button>
                            </form>
                        @endif
                    </td>
                    <td>
                        {{-- Cong ngay: den bu khi may chu sap, hoac tru lai neu lo go nham. --}}
                        <form method="POST" action="{{ route('admin.cong-ngay', $l) }}" class="hang">
                            @csrf
                            <input type="number" name="so_ngay" value="30" size="4" style="width:66px">
                            <button type="submit" class="nut">Cộng ngày</button>
                        </form>

                        <div style="margin-top:6px">
                            @if($l->biThuHoi())
                                <form method="POST" action="{{ route('admin.bo-thu-hoi', $l) }}" class="hang">
                                    @csrf
                                    <button type="submit" class="nut">Khôi phục</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.thu-hoi', $l) }}" class="hang">
                                    @csrf
                                    <button type="submit" class="nut xau">Thu hồi</button>
                                </form>
                            @endif

                            @if($l->email)
                                <form method="POST" action="{{ route('admin.gui-lai-ma', $l) }}" class="hang">
                                    @csrf
                                    <button type="submit" class="nut">Gửi lại mã</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>

        <div class="trang">{{ $danhSach->links('phan-trang') }}</div>
    @endif
@endsection

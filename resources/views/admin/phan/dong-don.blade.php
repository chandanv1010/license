{{-- Mot dong don hang. Dung chung cho trang tong quan va trang don hang. --}}
@php $license = $don->licenseKetQua(); @endphp
<tr>
    <td><code>{{ $don->code }}</code></td>
    <td class="nhe" style="white-space:nowrap">{{ $don->created_at->format('d/m/Y H:i') }}</td>
    <td>
        {{ $don->email ?: '—' }}
        @if($don->laGiaHan())
            <span class="nhan-tt tt-nhe">gia hạn</span>
        @endif
    </td>
    <td class="phai">{{ number_format($don->amount, 0, ',', '.') }}đ</td>
    <td>
        <span class="nhan-tt tt-{{ $don->status }}">
            {{ $don->daTra() ? 'đã trả' : 'chờ' }}
        </span>
    </td>
    <td class="ma">
        {{ $license?->key ?? '—' }}
    </td>
</tr>

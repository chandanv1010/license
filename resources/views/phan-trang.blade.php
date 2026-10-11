{{--
    Khung phan trang rieng.

    Mac dinh cua Laravel viet bang lop Tailwind, ma du an nay khong dung
    Tailwind - de nguyen thi phan trang hien ra nhu mot dong chu tran lan.
--}}
@if ($paginator->hasPages())
    <nav>
        @if ($paginator->onFirstPage())
            <span class="nut" style="opacity:.45">‹ Trước</span>
        @else
            <a class="nut" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Trước</a>
        @endif

        <span class="nut" style="border-color:transparent;background:transparent">
            Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
            — {{ $paginator->total() }} dòng
        </span>

        @if ($paginator->hasMorePages())
            <a class="nut" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau ›</a>
        @else
            <span class="nut" style="opacity:.45">Sau ›</span>
        @endif
    </nav>
@endif

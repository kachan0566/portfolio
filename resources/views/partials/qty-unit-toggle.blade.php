@php
    $pageKey = $pageKey ?? 'default';
@endphp
<div class="qty-unit-toggle" data-qty-unit-toggle data-page-key="{{ $pageKey }}">
    <span class="qty-unit-toggle__label">数量の入力単位</span>
    <button type="button" class="qty-unit-toggle__btn" data-unit="tan" aria-pressed="false">反数</button>
    <button type="button" class="qty-unit-toggle__btn" data-unit="meter" aria-pressed="false">メートル</button>
</div>

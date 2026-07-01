@php
    $qty = $qty ?? 0;
    $productId = $productId ?? null;
    $isGreige = $isGreige ?? false;
    $greigeSku = $greigeSku ?? null;
    $prefix = $prefix ?? '';
    $sign = $sign ?? null;
    if ($sign === null && is_string($prefix) && $prefix !== '' && in_array($prefix[0], ['+', '-'], true)) {
        $sign = $prefix[0];
        $prefix = '';
    }
@endphp
@if ($sign)
    {{ $sign }}
@endif
{{ $prefix }}@include('partials.qty', ['qty' => $qty, 'productId' => $productId, 'isGreige' => $isGreige, 'greigeSku' => $greigeSku])

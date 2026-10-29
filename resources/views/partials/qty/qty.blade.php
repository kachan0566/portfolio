@php
    $qty = $qty ?? 0;
    $productId = $productId ?? null;
    $isGreige = $isGreige ?? false;
    $greigeSku = $greigeSku ?? null;
    $prefix = $prefix ?? '';
@endphp
{{ $prefix }}{{ \App\Support\Qty\QtyHelper::format($qty, $productId, $isGreige, $greigeSku) }}

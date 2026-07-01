@php
    $qty = $qty ?? 0;
    $productId = $productId ?? null;
    $isGreige = $isGreige ?? false;
    $prefix = $prefix ?? '';
@endphp
{{ $prefix }}{{ \App\Support\QtyHelper::format($qty, $productId, $isGreige) }}

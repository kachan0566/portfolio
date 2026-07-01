@php
    $lines = $lines ?? collect();
    $qtyKey = $qtyKey ?? 'qty';
    $productId = $productId ?? null;
    $productIdKey = $productIdKey ?? 'product_id';
    $prefix = $prefix ?? '';
    $isGreige = $isGreige ?? false;
    $greigeSkuKey = $greigeSkuKey ?? null;
@endphp
{{ $prefix }}{{ \App\Support\QtyHelper::formatAggregateFromLines($lines, $qtyKey, $productId, $productIdKey, $isGreige, $greigeSkuKey) }}

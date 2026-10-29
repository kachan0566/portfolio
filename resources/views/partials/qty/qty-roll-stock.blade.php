@php
    use App\Support\ProductStock;
    use App\Support\QtyHelper;

    $productId = $productId ?? null;
    $prefix = $prefix ?? '';
    $meters = isset($meters) ? (float) $meters : null;
    $tan = isset($tan) ? (float) $tan : null;

    if ($meters === null || $tan === null) {
        $roll = $inStockRoll ?? ($productId ? ProductStock::inStockRollTotals((int) $productId) : (object) ['tan' => 0.0, 'meters' => 0.0]);
        $tan ??= (float) $roll->tan;
        $meters ??= (float) $roll->meters;
    }
@endphp
{{ $prefix }}{{ QtyHelper::formatAggregate($meters, $tan) }}

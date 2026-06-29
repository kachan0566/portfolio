@php
    use App\Support\PurchaseOrderType;

    $po = $purchase ?? null;
    $type = $po->type ?? PurchaseOrderType::PRODUCT;
@endphp
@if ($type === PurchaseOrderType::YARN)
    <span class="mono">{{ number_format((float) ($po->qty_kg ?? $po->qty ?? 0), 2) }} kg</span>
@elseif ($type === PurchaseOrderType::GREIGE)
    @include('partials.qty', [
        'qty' => (int) ($po->qty_meters ?? $po->qty ?? 0),
        'isGreige' => true,
        'greigeSku' => $po->greige_sku ?? $po->sku ?? null,
    ])
@else
    @include('partials.qty', [
        'qty' => (int) ($po->qty_meters ?? $po->qty ?? 0),
        'productId' => $po->product_id ?? null,
    ])
@endif

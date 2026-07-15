@php
    use App\Support\PurchaseOrderType;
    use App\Support\QtyHelper;

    $type = $purchase->type ?? PurchaseOrderType::PRODUCT;
@endphp
@if (count($receivedDetailRows) === 0)
    <p class="t-muted" style="margin:0;font-size:13px;">
        @if ($type === PurchaseOrderType::YARN)
            入荷記録はまだありません。
        @else
            入荷した反はまだありません。
        @endif
    </p>
@else
    <div class="table-wrap">
        <table class="data data--compact">
            <thead>
                <tr>
                    <th>{{ $type === PurchaseOrderType::YARN ? '入荷番号' : '反ID' }}</th>
                    <th>品番</th>
                    @if ($type !== PurchaseOrderType::YARN)
                        <th class="num">反数</th>
                        <th class="num">実測m</th>
                    @else
                        <th class="num">入荷kg</th>
                    @endif
                    <th>計測日</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receivedDetailRows as $row)
                    <tr>
                        <td class="code-cell mono">{{ $row['code'] }}</td>
                        <td class="code-cell">{{ $row['sku_label'] }}</td>
                        @if ($type !== PurchaseOrderType::YARN)
                            <td class="num mono">{{ QtyHelper::formatTanCount($row['tan_qty'] ?? 0) }}反</td>
                            <td class="num mono">{{ number_format((float) ($row['actual_m'] ?? 0), 1) }}m</td>
                        @else
                            <td class="num mono">{{ number_format((float) ($row['qty_kg'] ?? 0), 2) }} kg</td>
                        @endif
                        <td class="mono t-muted" style="font-size:12px;">{{ $row['measured_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

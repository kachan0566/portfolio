@php
    use App\Support\PurchaseOrderType;
    use App\Support\QtyHelper;

    $type = $purchase->type ?? PurchaseOrderType::PRODUCT;
@endphp
@if (count($receivingBySku) === 0)
    <p class="t-muted" style="margin:0;font-size:13px;">入荷状況はまだありません。</p>
@else
    <div class="table-wrap">
        <table class="data data--compact">
            <thead>
                <tr>
                    <th>品番</th>
                    @if ($type === PurchaseOrderType::YARN)
                        <th class="num">発注kg</th>
                        <th class="num">入荷済kg</th>
                        <th class="num">残kg</th>
                    @else
                        <th class="num">発注反数</th>
                        <th class="num">入荷済反数</th>
                        <th class="num">入荷済m</th>
                        <th class="num">残反数</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($receivingBySku as $row)
                    <tr>
                        <td class="code-cell t-strong">{{ $row['sku_label'] }}</td>
                        @if ($type === PurchaseOrderType::YARN)
                            <td class="num mono">{{ number_format((float) $row['ordered_kg'], 2) }}</td>
                            <td class="num mono">{{ number_format((float) $row['received_kg'], 2) }}</td>
                            <td class="num mono">{{ number_format((float) $row['remaining_kg'], 2) }}</td>
                        @else
                            <td class="num mono">{{ QtyHelper::formatTanCount($row['ordered_tan']) }}反</td>
                            <td class="num mono">{{ QtyHelper::formatTanCount($row['received_tan']) }}反</td>
                            <td class="num mono">{{ number_format((int) $row['received_m']) }}m</td>
                            <td class="num mono">{{ QtyHelper::formatTanCount($row['remaining_tan']) }}反</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

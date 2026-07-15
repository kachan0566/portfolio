@php
    use App\Support\PurchaseOrderType;
    use App\Support\QtyHelper;

    $type = $purchase->type ?? PurchaseOrderType::PRODUCT;
@endphp
@if (count($orderLines) === 0)
    <p class="t-muted" style="margin:0;font-size:13px;">発注明細はありません。</p>
@else
    <div class="table-wrap">
        <table class="data data--compact">
            <thead>
                <tr>
                    <th>行</th>
                    @if ($type === PurchaseOrderType::YARN)
                        <th>糸品番</th>
                        <th class="num">発注数量</th>
                    @elseif ($type === PurchaseOrderType::GREIGE)
                        <th>生機品番</th>
                        <th class="num">発注反数</th>
                    @else
                        <th>製品品番</th>
                        <th>生機品番</th>
                        <th class="num">発注反数</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($orderLines as $line)
                    <tr>
                        <td class="mono">{{ $line['line_no'] }}</td>
                        @if ($type === PurchaseOrderType::YARN)
                            <td class="code-cell t-strong">
                                {{ $line['sku'] }}
                                @if (! empty($line['material_name']) && $line['material_name'] !== '—')
                                    <span class="t-muted">（{{ $line['material_name'] }}）</span>
                                @endif
                            </td>
                            <td class="num mono">{{ number_format((float) ($line['ordered_kg'] ?? $line['ordered']), 2) }} kg</td>
                        @elseif ($type === PurchaseOrderType::GREIGE)
                            <td class="code-cell t-strong">
                                {{ $line['greige_sku'] ?? $line['sku'] }}
                                @if (! empty($line['greige_name']) && $line['greige_name'] !== '—')
                                    <span class="t-muted">（{{ $line['greige_name'] }}）</span>
                                @endif
                            </td>
                            <td class="num mono">{{ QtyHelper::formatTanCount($line['ordered_tan'] ?? 0) }}反</td>
                        @else
                            <td class="code-cell t-strong">
                                {{ $line['product_sku'] ?? $line['sku'] }}
                                @if (! empty($line['product_color']))
                                    <span class="t-muted">（{{ $line['product_color'] }}）</span>
                                @endif
                            </td>
                            <td class="code-cell">
                                {{ $line['greige_sku'] ?? '—' }}
                                @if (! empty($line['greige_name']) && $line['greige_name'] !== '—')
                                    <span class="t-muted">（{{ $line['greige_name'] }}）</span>
                                @endif
                            </td>
                            <td class="num mono">{{ QtyHelper::formatTanCount($line['ordered_tan'] ?? 0) }}反</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

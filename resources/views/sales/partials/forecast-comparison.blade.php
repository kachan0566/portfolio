@php
    $cmp = $forecastComparison;
    $avf = $cmp->actual_vs_forecast;
    $qtyLines = ($selectedProduct ?? null)
        ? $forecast->lines->where('product_id', $selectedProduct->id)
        : $forecast->lines;
    $snapshot = $forecast->latest_snapshot ?? null;
    $snapshotQtyLines = $snapshot !== null
        ? collect($snapshot->lines ?? [])->when(
            $selectedProduct ?? null,
            fn ($rows) => $rows->where('product_id', $selectedProduct->id)
        )
        : collect();
    $actualSales = (int) $qtyLines->sum('actual_sales');
    $remainingSales = (int) $qtyLines->sum('forecast_remaining_sales');
    $totalSales = (int) $qtyLines->sum('total_sales');
    $actualProfit = (int) $qtyLines->where('cost_calculable', true)->sum('actual_profit');
    $remainingProfit = (int) $qtyLines->where('cost_calculable', true)->sum('forecast_remaining_profit');
    $totalProfit = (int) $qtyLines->where('cost_calculable', true)->sum('total_profit');
@endphp

<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <span class="t-muted" style="font-size:13px;">基準: 出荷予定日が {{ $forecast->month_end_date }} まで</span>
    @if ($forecast->latest_snapshot)
        <span class="badge badge-green badge--plain">提出済 Ver.{{ $forecast->latest_snapshot->version }}</span>
    @endif
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('sales.forecast.csv', ['ym' => $ym]) }}" class="btn btn-secondary btn-sm">
            @include('partials.icon', ['name' => 'download']) CSV出力
        </a>
        <form method="POST" action="{{ route('sales.forecast.snapshot') }}" onsubmit="return confirm('提出版として保存しますか？');">
            @csrf
            <input type="hidden" name="ym" value="{{ $ym }}">
            <button type="submit" class="btn btn-primary btn-sm">提出版として保存</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">実績と見通し（対象月: {{ $ym }}）</h2></div>
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th class="num">出荷実績</th>
                        <th class="num">残り見通し</th>
                        <th class="num">月末見込み</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            出荷数量
                            @if (! ($selectedProduct ?? null))
                                <span class="t-muted" style="font-size:11px;display:block;">品番別反数の合計</span>
                            @endif
                        </td>
                        <td class="num mono">@include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'actual_qty', 'productId' => $selectedProduct?->id])</td>
                        <td class="num mono">@include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'forecast_remaining_qty', 'productId' => $selectedProduct?->id])</td>
                        <td class="num mono t-strong">@include('partials.qty-aggregate', ['lines' => $qtyLines, 'qtyKey' => 'total_qty', 'productId' => $selectedProduct?->id])</td>
                    </tr>
                    <tr>
                        <td>売上</td>
                        <td class="num mono">{{ number_format($actualSales) }} 円</td>
                        <td class="num mono">{{ number_format($remainingSales) }} 円</td>
                        <td class="num mono t-strong">{{ number_format($totalSales) }} 円</td>
                    </tr>
                    <tr>
                        <td>粗利</td>
                        <td class="num mono">{{ number_format($actualProfit) }} 円</td>
                        <td class="num mono">{{ number_format($remainingProfit) }} 円</td>
                        <td class="num mono t-strong">{{ number_format($totalProfit) }} 円</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($cmp->has_snapshot)
    @php
        $svc = $cmp->snapshot_vs_current;
        $currentQtyLines = $qtyLines;
        $snapshotTan = \App\Support\QtyHelper::sumTanFromLines($snapshotQtyLines, 'total_qty');
        $currentTan = \App\Support\QtyHelper::sumTanFromLines($currentQtyLines, 'total_qty');
        $diffTan = round($currentTan - $snapshotTan, \App\Support\QtyHelper::TAN_DECIMALS);
    @endphp
    <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
            <h2 class="card__title">提出版との差分（Ver.{{ $svc->version }}）</h2>
            <p class="t-muted" style="font-size:12px;margin:4px 0 0;">最終提出時点から見通しがどれだけ変わったかを表示します。</p>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>項目</th>
                            <th class="num">提出版</th>
                            <th class="num">現在の見通し</th>
                            <th class="num">差分</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                出荷数量
                                @if (! ($selectedProduct ?? null))
                                    <span class="t-muted" style="font-size:11px;display:block;">品番別反数の合計</span>
                                @endif
                            </td>
                            <td class="num mono">@include('partials.qty-aggregate', ['lines' => $snapshotQtyLines, 'qtyKey' => 'total_qty', 'productId' => $selectedProduct?->id])</td>
                            <td class="num mono">@include('partials.qty-aggregate', ['lines' => $currentQtyLines, 'qtyKey' => 'total_qty', 'productId' => $selectedProduct?->id])</td>
                            <td class="num mono {{ $svc->diff_qty != 0 ? 't-strong' : 't-muted' }}">
                                @if ($selectedProduct ?? null)
                                    @php
                                        $snapM = (int) \App\Support\QtyHelper::sumMetersFromLines($snapshotQtyLines, 'total_qty');
                                        $curM = (int) \App\Support\QtyHelper::sumMetersFromLines($currentQtyLines, 'total_qty');
                                    @endphp
                                    @include('partials.qty-display', ['qty' => abs($curM - $snapM), 'productId' => $selectedProduct->id, 'sign' => $curM - $snapM >= 0 ? '+' : '-'])
                                @else
                                    {{ $diffTan >= 0 ? '+' : '' }}{{ \App\Support\QtyHelper::formatTanCount($diffTan) }}反
                                    ／ {{ $svc->diff_qty >= 0 ? '+' : '' }}{{ number_format($svc->diff_qty) }}m
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>売上</td>
                            <td class="num mono">{{ number_format($svc->snapshot_total_sales) }} 円</td>
                            <td class="num mono">{{ number_format($svc->current_total_sales) }} 円</td>
                            <td class="num mono {{ $svc->diff_sales != 0 ? 't-strong' : 't-muted' }}">
                                {{ $svc->diff_sales >= 0 ? '+' : '' }}{{ number_format($svc->diff_sales) }} 円
                            </td>
                        </tr>
                        <tr>
                            <td>粗利</td>
                            <td class="num mono">{{ number_format($svc->snapshot_total_profit) }} 円</td>
                            <td class="num mono">{{ number_format($svc->current_total_profit) }} 円</td>
                            <td class="num mono {{ $svc->diff_profit != 0 ? 't-strong' : 't-muted' }}">
                                {{ $svc->diff_profit >= 0 ? '+' : '' }}{{ number_format($svc->diff_profit) }} 円
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if ($cmp->line_diffs->isNotEmpty())
                <div style="padding:12px 16px;border-top:1px solid var(--border);">
                    <div class="t-muted" style="font-size:12px;margin-bottom:8px;">品番別の変更（{{ $cmp->line_diffs->count() }} 件）</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($cmp->line_diffs as $diff)
                            <a href="{{ route('sales.forecast.show', ['product' => $diff->product_id, 'ym' => $ym]) }}" class="badge badge-amber badge--plain" style="font-size:11px;">
                                {{ $diff->sku }}
                                売上 {{ $diff->diff_sales >= 0 ? '+' : '' }}{{ number_format($diff->diff_sales) }}円
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

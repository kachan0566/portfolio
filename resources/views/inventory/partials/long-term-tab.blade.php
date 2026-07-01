@php
    $lt = $longTerm;
    $ltLines = $lt->lines ?? collect();
@endphp

<div style="margin-bottom:16px;">
    <span class="t-muted" style="font-size:13px;">判定基準日: {{ $lt->as_of_date }}（現在日）</span>
</div>

<div class="kpi-grid" style="margin-bottom:16px;">
    <div class="kpi">
        <div class="kpi__label">長期在庫品番数</div>
        <div class="kpi__value">{{ $lt->product_count }}<span style="font-size:14px;"> 件</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__label">長期在庫総数量</div>
        <div class="kpi__value" style="font-size:20px;">@include('partials.qty-aggregate', ['lines' => $ltLines, 'qtyKey' => 'long_term_qty'])</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">長期在庫総金額</div>
        <div class="kpi__value" style="font-size:20px;">{{ number_format($lt->total_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">12〜18か月</div>
        <div class="kpi__value" style="font-size:16px;">@include('partials.qty-aggregate', ['lines' => $ltLines, 'qtyKey' => 'bucket_12_18_qty'])</div>
        <div class="kpi__sub">{{ number_format($lt->bucket_12_18_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">18〜24か月</div>
        <div class="kpi__value" style="font-size:16px;">@include('partials.qty-aggregate', ['lines' => $ltLines, 'qtyKey' => 'bucket_18_24_qty'])</div>
        <div class="kpi__sub">{{ number_format($lt->bucket_18_24_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">24〜36か月</div>
        <div class="kpi__value" style="font-size:16px;">@include('partials.qty-aggregate', ['lines' => $ltLines, 'qtyKey' => 'bucket_24_36_qty'])</div>
        <div class="kpi__sub">{{ number_format($lt->bucket_24_36_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">36か月以上</div>
        <div class="kpi__value" style="font-size:16px;">@include('partials.qty-aggregate', ['lines' => $ltLines, 'qtyKey' => 'bucket_36_plus_qty'])</div>
        <div class="kpi__sub">{{ number_format($lt->bucket_36_plus_value) }} 円</div>
    </div>
</div>

<div class="card">
    <div class="card__head"><h2 class="card__title">長期在庫一覧</h2></div>
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>品番</th>
                        <th class="num">現在庫</th>
                        <th class="num">長期在庫</th>
                        <th class="num">長期在庫金額</th>
                        <th>最古入荷日</th>
                        <th class="num">最古月齢</th>
                        <th>最終出荷日</th>
                        <th>経過月数区分</th>
                        <th style="width:72px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lt->lines->where('long_term_qty', '>', 0) as $line)
                        <tr>
                            <td class="code-cell t-strong">{{ $line->sku }}</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->current_stock_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->long_term_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">{{ number_format($line->long_term_value) }} 円</td>
                            <td class="mono t-muted">{{ $line->oldest_received_date ?? '—' }}</td>
                            <td class="num mono">{{ $line->oldest_age_months !== null ? $line->oldest_age_months.'か月' : '—' }}</td>
                            <td class="mono t-muted">{{ $line->last_shipment_date ?? '—' }}</td>
                            <td><span class="badge badge-amber badge--plain">{{ $line->age_bucket }}</span></td>
                            <td>
                                <a href="{{ route('inventory.long-term.show', $line->product_id) }}" class="btn btn-secondary btn-sm">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty">12か月以上の長期在庫はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

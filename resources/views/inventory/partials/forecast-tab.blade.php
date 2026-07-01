@php
    $f = $forecastSummary;
@endphp

<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <form method="GET" action="{{ route('inventory.index') }}" class="sales-month-form">
        <input type="hidden" name="tab" value="forecast">
        <label class="sales-month-form__label" for="forecast-ym">対象月</label>
        <select class="select" id="forecast-ym" name="ym" onchange="this.form.submit()">
            @foreach ($forecastMonthOptions as $optionYm)
                <option value="{{ $optionYm }}" @selected($optionYm === $forecastYm)>{{ $optionYm }}</option>
            @endforeach
        </select>
    </form>
    <span class="t-muted" style="font-size:13px;">基準日: {{ $f->month_end_date }}（月末）</span>
    @if ($f->latest_snapshot)
        <span class="badge badge-green badge--plain">提出済 Ver.{{ $f->latest_snapshot->version }}</span>
    @endif
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('inventory.forecast.csv', ['ym' => $forecastYm]) }}" class="btn btn-secondary btn-sm">
            @include('partials.icon', ['name' => 'download']) CSV出力
        </a>
        <form method="POST" action="{{ route('inventory.forecast.snapshot') }}" onsubmit="return confirm('提出版として保存しますか？');">
            @csrf
            <input type="hidden" name="ym" value="{{ $forecastYm }}">
            <button type="submit" class="btn btn-primary btn-sm">提出版として保存</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">検索</h2></div>
    @include('partials.list-search', [
        'hidden' => ['tab' => 'forecast', 'ym' => $forecastYm],
        'params' => $search,
        'fields' => [
            'sku' => ['label' => '品番', 'placeholder' => '品番'],
            'status' => [
                'label' => '警告',
                'options' => [
                    '正常' => '正常',
                    '原価未登録' => '原価未登録',
                    '在庫不足予想' => '在庫不足予想',
                    '安全在庫割れ' => '安全在庫割れ',
                ],
            ],
        ],
    ])
</div>

@include('inventory.partials.forecast-kpi-grid', [
    'summary' => $f,
    'singleProduct' => false,
    'inventoryLines' => $forecast->lines,
])

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">手動調整の登録</h2></div>
    <div class="card__body">
        @include('inventory.partials.forecast-adjustment-form', [
            'targetYm' => $forecastYm,
            'productOptions' => $forecast->lines,
            'formId' => 'list',
        ])
    </div>
</div>

<div class="card">
    <div class="card__head"><h2 class="card__title">品番別明細（{{ $forecastYm }} 月末予想）</h2></div>
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>品番</th>
                        <th class="num">現在庫</th>
                        <th class="num">入荷予定</th>
                        <th class="num">出荷確定</th>
                        <th class="num">手動調整</th>
                        <th class="num">月末予想</th>
                        <th class="num">製造コスト</th>
                        <th class="num">予想金額</th>
                        <th>最古入荷日</th>
                        <th class="num">月齢</th>
                        <th class="num">12か月超</th>
                        <th>警告</th>
                        <th style="width:72px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($forecastLines as $line)
                        <tr>
                            <td class="code-cell t-strong">{{ $line->sku }}</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->current_stock_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono t-muted">+@include('partials.qty', ['qty' => $line->inbound_scheduled_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono t-muted">-@include('partials.qty', ['qty' => $line->outbound_confirmed_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">@include('partials.qty-display', ['qty' => abs($line->manual_adjustment_qty), 'productId' => $line->product_id, 'sign' => $line->manual_adjustment_qty >= 0 ? '+' : '-'])</td>
                            <td class="num mono t-strong {{ $line->is_negative ? 't-danger' : '' }}">@include('partials.qty', ['qty' => $line->forecast_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">{{ $line->unit_cost !== null ? number_format($line->unit_cost).' 円/m' : '—' }}</td>
                            <td class="num mono">{{ number_format($line->forecast_value) }} 円</td>
                            <td class="mono t-muted">{{ $line->oldest_received_date ?? '—' }}</td>
                            <td class="num mono">{{ $line->oldest_age_months !== null ? $line->oldest_age_months.'か月' : '—' }}</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->long_term_qty, 'productId' => $line->product_id])</td>
                            <td>
                                @if ($line->warning_text)
                                    <span class="badge badge-amber badge--plain" style="font-size:11px;">{{ $line->warning_text }}</span>
                                @else
                                    <span class="badge badge-green badge--plain" style="font-size:11px;">正常</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('inventory.forecast.show', ['product' => $line->product_id, 'ym' => $forecastYm]) }}" class="btn btn-secondary btn-sm">詳細</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="empty">条件に一致する品番はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

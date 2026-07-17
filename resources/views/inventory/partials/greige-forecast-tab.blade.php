@php
    $f = $greigeForecastSummary;
@endphp

<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <form method="GET" action="{{ route('inventory.index') }}" class="sales-month-form">
        <input type="hidden" name="tab" value="greige_forecast">
        <label class="sales-month-form__label" for="greige-forecast-ym">対象月</label>
        <select class="select" id="greige-forecast-ym" name="ym" onchange="this.form.submit()">
            @foreach ($forecastMonthOptions as $optionYm)
                <option value="{{ $optionYm }}" @selected($optionYm === $forecastYm)>{{ $optionYm }}</option>
            @endforeach
        </select>
    </form>
    <span class="t-muted" style="font-size:13px;">基準日: {{ $f->month_end_date }}（月末）</span>
    @if ($f->latest_snapshot)
        <span class="badge badge-green badge--plain">提出済 Ver.{{ $f->latest_snapshot->version }}</span>
    @endif
    <span class="t-muted" style="font-size:12px;">染機投入予定は製品発注の先方連絡予定日（contact_date）を使用</span>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('inventory.greige-forecast.csv', ['ym' => $forecastYm]) }}" class="btn btn-secondary btn-sm">
            @include('partials.icon', ['name' => 'download']) CSV出力
        </a>
        <form method="POST" action="{{ route('inventory.greige-forecast.snapshot') }}" onsubmit="return confirm('提出版として保存しますか？');">
            @csrf
            <input type="hidden" name="ym" value="{{ $forecastYm }}">
            <button type="submit" class="btn btn-primary btn-sm">提出版として保存</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">検索</h2></div>
    @include('partials.list-search', [
        'hidden' => ['tab' => 'greige_forecast', 'ym' => $forecastYm],
        'params' => $search,
        'fields' => [
            'sku' => ['label' => '生機品番', 'placeholder' => 'KB-T'],
            'status' => [
                'label' => '警告',
                'options' => [
                    '正常' => '正常',
                    '原価未登録' => '原価未登録',
                    '在庫不足予想' => '在庫不足予想',
                ],
            ],
        ],
    ])
</div>

@include('inventory.partials.greige-forecast-kpi-grid', [
    'summary' => $f,
    'inventoryLines' => $greigeForecast->lines,
])

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">手動調整の登録</h2></div>
    <div class="card__body">
        @include('inventory.partials.greige-forecast-adjustment-form', [
            'targetYm' => $forecastYm,
            'greigeOptions' => $greigeForecast->lines,
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
                        <th>生機品番</th>
                        <th class="num">現在庫</th>
                        <th class="num">入荷予定</th>
                        <th class="num">染機投入予定</th>
                        <th class="num">手動調整</th>
                        <th class="num">月末予想</th>
                        <th class="num">生機単価</th>
                        <th class="num">予想金額</th>
                        <th>最古入荷日</th>
                        <th class="num">月齢</th>
                        <th class="num">12か月超</th>
                        <th>警告</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($greigeForecastLines as $line)
                        <tr>
                            <td class="code-cell t-strong">{{ $line->greige_sku }}<div class="t-muted" style="font-size:11px;">{{ $line->greige_name }}</div></td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->current_stock_qty, 'isGreige' => true, 'greigeSku' => $line->greige_sku])</td>
                            <td class="num mono t-muted">+@include('partials.qty', ['qty' => $line->inbound_scheduled_qty, 'isGreige' => true, 'greigeSku' => $line->greige_sku])</td>
                            <td class="num mono t-muted">-@include('partials.qty', ['qty' => $line->outbound_scheduled_qty, 'isGreige' => true, 'greigeSku' => $line->greige_sku])</td>
                            <td class="num mono">@include('partials.qty-display', ['qty' => abs($line->manual_adjustment_qty), 'isGreige' => true, 'greigeSku' => $line->greige_sku, 'sign' => $line->manual_adjustment_qty >= 0 ? '+' : '-'])</td>
                            <td class="num mono t-strong {{ $line->is_negative ? 't-danger' : '' }}">@include('partials.qty', ['qty' => $line->forecast_qty, 'isGreige' => true, 'greigeSku' => $line->greige_sku])</td>
                            <td class="num mono">{{ $line->unit_cost !== null ? number_format($line->unit_cost).' 円/m' : '—' }}</td>
                            <td class="num mono">{{ number_format($line->forecast_value) }} 円</td>
                            <td class="mono t-muted">{{ $line->oldest_received_date ?? '—' }}</td>
                            <td class="num mono">{{ $line->oldest_age_months !== null ? $line->oldest_age_months.'か月' : '—' }}</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->long_term_qty, 'isGreige' => true, 'greigeSku' => $line->greige_sku])</td>
                            <td>
                                @if ($line->warning_text)
                                    <span class="badge badge-amber badge--plain" style="font-size:11px;">{{ $line->warning_text }}</span>
                                @else
                                    <span class="badge badge-green badge--plain" style="font-size:11px;">正常</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="empty">条件に一致する生機品番はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

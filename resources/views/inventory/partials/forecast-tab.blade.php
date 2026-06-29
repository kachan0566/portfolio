@php
    $f = $forecast;
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

<div class="kpi-grid" style="margin-bottom:16px;">
    <div class="kpi">
        <div class="kpi__label">現在庫金額</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->current_stock_value) }}<span style="font-size:13px;"> 円</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__label">入荷予定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->inbound_scheduled_qty) }}m</div>
        <div class="kpi__sub">{{ number_format($f->inbound_scheduled_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">出荷確定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->outbound_confirmed_qty) }}m</div>
        <div class="kpi__sub">{{ number_format($f->outbound_confirmed_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">月末予想在庫</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->forecast_qty) }}m</div>
        <div class="kpi__sub">{{ number_format($f->forecast_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">前月末との差額</div>
        <div class="kpi__value" style="font-size:18px;">
            @if ($f->prev_month_diff !== null)
                {{ $f->prev_month_diff >= 0 ? '+' : '' }}{{ number_format($f->prev_month_diff) }}<span style="font-size:13px;"> 円</span>
            @else
                —
            @endif
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label">長期在庫（月末予想）</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->long_term_qty) }}m</div>
        <div class="kpi__sub">{{ number_format($f->long_term_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">原価未登録品番</div>
        <div class="kpi__value" style="font-size:18px;">{{ $f->uncosted_count }}<span style="font-size:13px;"> 件</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__label">在庫不足予想</div>
        <div class="kpi__value" style="font-size:18px;">{{ $f->shortage_count }}<span style="font-size:13px;"> 件</span></div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">手動調整の登録</h2></div>
    <div class="card__body">
        <form method="POST" action="{{ route('inventory.forecast.adjustments') }}" class="form-row" style="align-items:flex-end;gap:12px;flex-wrap:wrap;">
            @csrf
            <input type="hidden" name="target_ym" value="{{ $forecastYm }}">
            <div class="field" style="min-width:140px;">
                <label class="label" for="adj-product">品番</label>
                <select class="select" id="adj-product" name="product_id" required>
                    @foreach ($f->lines as $line)
                        <option value="{{ $line->product_id }}">{{ $line->sku }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="min-width:100px;">
                <label class="label" for="adj-direction">増減</label>
                <select class="select" id="adj-direction" name="direction" required>
                    <option value="increase">増加</option>
                    <option value="decrease">減少</option>
                </select>
            </div>
            <div class="field" style="min-width:100px;">
                <label class="label" for="adj-qty">数量（m）</label>
                <input class="input" type="number" id="adj-qty" name="qty" min="0.01" step="0.01" required>
            </div>
            <div class="field" style="flex:1;min-width:200px;">
                <label class="label" for="adj-reason">調整理由</label>
                <input class="input" type="text" id="adj-reason" name="reason" required placeholder="例）染工場からの連絡により入荷が2日遅れ">
            </div>
            <button type="submit" class="btn btn-secondary">登録</button>
        </form>
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
                    @foreach ($f->lines as $line)
                        <tr>
                            <td class="code-cell t-strong">{{ $line->sku }}</td>
                            <td class="num mono">@include('partials.qty', ['qty' => $line->current_stock_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono t-muted">+@include('partials.qty', ['qty' => $line->inbound_scheduled_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono t-muted">-@include('partials.qty', ['qty' => $line->outbound_confirmed_qty, 'productId' => $line->product_id])</td>
                            <td class="num mono">{{ $line->manual_adjustment_qty >= 0 ? '+' : '' }}{{ number_format($line->manual_adjustment_qty) }}m</td>
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

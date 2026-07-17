@php
    $c = $combinedForecast;
    $ps = $c->product_summary;
    $gs = $c->greige_summary;
@endphp

<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <form method="GET" action="{{ route('inventory.index') }}" class="sales-month-form">
        <input type="hidden" name="tab" value="forecast_combined">
        <label class="sales-month-form__label" for="combined-forecast-ym">対象月</label>
        <select class="select" id="combined-forecast-ym" name="ym" onchange="this.form.submit()">
            @foreach ($forecastMonthOptions as $optionYm)
                <option value="{{ $optionYm }}" @selected($optionYm === $forecastYm)>{{ $optionYm }}</option>
            @endforeach
        </select>
    </form>
    <span class="t-muted" style="font-size:13px;">基準日: {{ $c->month_end_date }}（月末）</span>
    @if ($c->both_submitted && $c->unified_version)
        <span class="badge badge-green badge--plain">提出済 Ver.{{ $c->unified_version }}</span>
    @endif
    <div style="margin-left:auto;">
        <form method="POST" action="{{ route('inventory.forecast-combined.snapshot') }}" onsubmit="return confirm('製品・生機・合算の提出版を同一バージョンで保存します。よろしいですか？');">
            @csrf
            <input type="hidden" name="ym" value="{{ $forecastYm }}">
            <button type="submit" class="btn btn-primary btn-sm">まとめて提出版として保存</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card__head"><h2 class="card__title">合算（製品＋生機）</h2></div>
    <div class="card__body">
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi__label">現在庫金額（合計）</div>
                <div class="kpi__value" style="font-size:18px;">{{ number_format($c->current_stock_value) }}<span style="font-size:13px;"> 円</span></div>
                <div class="kpi__sub t-muted">各品番の m × 円/m の合計</div>
            </div>
            <div class="kpi">
                <div class="kpi__label">月末予想在庫金額（合計）</div>
                <div class="kpi__value" style="font-size:18px;">{{ number_format($c->forecast_value) }}<span style="font-size:13px;"> 円</span></div>
                <div class="kpi__sub">製品 {{ number_format($c->product_forecast_value) }} 円 ／ 生機 {{ number_format($c->greige_forecast_value) }} 円</div>
            </div>
            <div class="kpi">
                <div class="kpi__label">前月末との差額（合計）</div>
                <div class="kpi__value" style="font-size:18px;">
                    @if ($c->prev_month_diff !== null)
                        {{ $c->prev_month_diff >= 0 ? '+' : '' }}{{ number_format($c->prev_month_diff) }}<span style="font-size:13px;"> 円</span>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:16px;">
    <div class="card">
        <div class="card__head" style="display:flex;justify-content:space-between;align-items:center;">
            <h2 class="card__title">製品</h2>
            <a href="{{ route('inventory.index', ['tab' => 'forecast', 'ym' => $forecastYm]) }}" class="btn btn-secondary btn-sm">製品月末予想へ</a>
        </div>
        <div class="card__body">
            <div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);">
                <div class="kpi">
                    <div class="kpi__label">現在庫</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->product->lines, 'qtyKey' => 'current_stock_qty'])
                    </div>
                    <div class="kpi__sub">{{ number_format($ps['current_stock_value']) }} 円</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">月末予想</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->product->lines, 'qtyKey' => 'forecast_qty'])
                    </div>
                    <div class="kpi__sub">{{ number_format($ps['forecast_value']) }} 円</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">入荷予定</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->product->lines, 'qtyKey' => 'inbound_scheduled_qty'])
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">出荷確定</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->product->lines, 'qtyKey' => 'outbound_confirmed_qty'])
                    </div>
                </div>
            </div>
            @if ($ps['uncosted_count'] > 0 || $ps['shortage_count'] > 0)
                <p class="t-muted" style="font-size:12px;margin:12px 0 0;">
                    @if ($ps['uncosted_count'] > 0)原価未登録 {{ $ps['uncosted_count'] }} 件 @endif
                    @if ($ps['shortage_count'] > 0)在庫不足予想 {{ $ps['shortage_count'] }} 件 @endif
                </p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card__head" style="display:flex;justify-content:space-between;align-items:center;">
            <h2 class="card__title">生機（染工場）</h2>
            <a href="{{ route('inventory.index', ['tab' => 'greige_forecast', 'ym' => $forecastYm]) }}" class="btn btn-secondary btn-sm">生機月末予想へ</a>
        </div>
        <div class="card__body">
            <div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);">
                <div class="kpi">
                    <div class="kpi__label">現在庫</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->greige->lines, 'qtyKey' => 'current_stock_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
                    </div>
                    <div class="kpi__sub">{{ number_format($gs['current_stock_value']) }} 円</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">月末予想</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->greige->lines, 'qtyKey' => 'forecast_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
                    </div>
                    <div class="kpi__sub">{{ number_format($gs['forecast_value']) }} 円</div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">入荷予定</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->greige->lines, 'qtyKey' => 'inbound_scheduled_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi__label">染機投入予定</div>
                    <div class="kpi__value" style="font-size:16px;">
                        @include('partials.qty-aggregate', ['lines' => $c->greige->lines, 'qtyKey' => 'outbound_scheduled_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
                    </div>
                </div>
            </div>
            @if ($gs['uncosted_count'] > 0 || $gs['shortage_count'] > 0)
                <p class="t-muted" style="font-size:12px;margin:12px 0 0;">
                    @if ($gs['uncosted_count'] > 0)原価未登録 {{ $gs['uncosted_count'] }} 件 @endif
                    @if ($gs['shortage_count'] > 0)在庫不足予想 {{ $gs['shortage_count'] }} 件 @endif
                </p>
            @endif
        </div>
    </div>
</div>

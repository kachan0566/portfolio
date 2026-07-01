@php
    $f = $summary;
    $singleProduct = $singleProduct ?? false;
    $inventoryLines = $inventoryLines ?? collect();
@endphp

<div class="kpi-grid" style="margin-bottom:16px;">
    <div class="kpi">
        <div class="kpi__label">現在庫金額</div>
        <div class="kpi__value" style="font-size:18px;">{{ number_format($f->current_stock_value) }}<span style="font-size:13px;"> 円</span></div>
    </div>
    <div class="kpi">
        <div class="kpi__label">現在庫数量</div>
        <div class="kpi__value" style="font-size:18px;">
            @if ($singleProduct && isset($line))
                @include('partials.qty', ['qty' => $f->current_stock_qty, 'productId' => $line->product_id])
            @else
                @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'current_stock_qty'])
            @endif
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label">入荷予定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">
            @if ($singleProduct && isset($line))
                @include('partials.qty', ['qty' => $f->inbound_scheduled_qty, 'productId' => $line->product_id])
            @else
                @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'inbound_scheduled_qty'])
            @endif
        </div>
        <div class="kpi__sub">{{ number_format($f->inbound_scheduled_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">出荷確定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">
            @if ($singleProduct && isset($line))
                @include('partials.qty', ['qty' => $f->outbound_confirmed_qty, 'productId' => $line->product_id])
            @else
                @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'outbound_confirmed_qty'])
            @endif
        </div>
        <div class="kpi__sub">{{ number_format($f->outbound_confirmed_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">月末予想在庫</div>
        <div class="kpi__value" style="font-size:18px;">
            @if ($singleProduct && isset($line))
                @include('partials.qty', ['qty' => $f->forecast_qty, 'productId' => $line->product_id])
            @else
                @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'forecast_qty'])
            @endif
        </div>
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
        <div class="kpi__value" style="font-size:18px;">
            @if ($singleProduct && isset($line))
                @include('partials.qty', ['qty' => $f->long_term_qty, 'productId' => $line->product_id])
            @else
                @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'long_term_qty'])
            @endif
        </div>
        <div class="kpi__sub">{{ number_format($f->long_term_value) }} 円</div>
    </div>
    @if ($singleProduct)
        @if (! ($line->cost_calculable ?? true))
            <div class="kpi">
                <div class="kpi__label">原価</div>
                <div class="kpi__value" style="font-size:16px;">
                    <span class="badge badge-amber badge--plain">原価未登録</span>
                </div>
            </div>
        @endif
        @if ($line->is_negative ?? false)
            <div class="kpi">
                <div class="kpi__label">在庫予想</div>
                <div class="kpi__value" style="font-size:16px;">
                    <span class="badge badge-rose badge--plain">在庫不足予想</span>
                </div>
            </div>
        @elseif ($line->is_shortage ?? false)
            <div class="kpi">
                <div class="kpi__label">在庫予想</div>
                <div class="kpi__value" style="font-size:16px;">
                    <span class="badge badge-amber badge--plain">安全在庫割れ</span>
                </div>
            </div>
        @endif
    @else
        <div class="kpi">
            <div class="kpi__label">原価未登録品番</div>
            <div class="kpi__value" style="font-size:18px;">{{ $f->uncosted_count }}<span style="font-size:13px;"> 件</span></div>
        </div>
        <div class="kpi">
            <div class="kpi__label">在庫不足予想</div>
            <div class="kpi__value" style="font-size:18px;">{{ $f->shortage_count }}<span style="font-size:13px;"> 件</span></div>
        </div>
    @endif
</div>

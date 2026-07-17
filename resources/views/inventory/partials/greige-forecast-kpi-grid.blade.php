@php
    $f = $summary;
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
            @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'current_stock_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
        </div>
    </div>
    <div class="kpi">
        <div class="kpi__label">入荷予定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">
            @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'inbound_scheduled_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
        </div>
        <div class="kpi__sub">{{ number_format($f->inbound_scheduled_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">染機投入予定（月末まで）</div>
        <div class="kpi__value" style="font-size:18px;">
            @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'outbound_scheduled_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
        </div>
        <div class="kpi__sub">{{ number_format($f->outbound_scheduled_value) }} 円</div>
    </div>
    <div class="kpi">
        <div class="kpi__label">月末予想在庫</div>
        <div class="kpi__value" style="font-size:18px;">
            @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'forecast_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
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
            @include('partials.qty-aggregate', ['lines' => $inventoryLines, 'qtyKey' => 'long_term_qty', 'isGreige' => true, 'greigeSkuKey' => 'greige_sku'])
        </div>
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

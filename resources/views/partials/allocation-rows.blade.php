@php
    use App\Support\StockAllocation;
    $stockType = StockAllocation::TYPE_STOCK;
    $poType = StockAllocation::TYPE_PO;
    $allocPageKey = $allocPageKey ?? 'allocation';
    $allocMetersPerTan = $allocMetersPerTan ?? 50;
@endphp

@foreach ($orders as $sOrder)
    @php
        $isCurrent = ($highlightOrderId ?? null) === $sOrder->id;
        $rowBg = $isCurrent ? 'background:#eff6ff;border-left:3px solid #3b82f6;' : '';
        $stockLines = $sOrder->stock_lines ?? collect();
        $poLines = $sOrder->po_lines ?? collect();
        $stockMap = [];
        foreach ($stockLines as $line) {
            $stockMap[$line->po_id] = ($stockMap[$line->po_id] ?? 0) + $line->qty;
        }
        $poMap = [];
        foreach ($poLines as $line) {
            $poMap[$line->po_id] = ($poMap[$line->po_id] ?? 0) + $line->qty;
        }
        if (empty($stockMap)) { $stockMap = ['' => 0]; }
        if (empty($poMap)) { $poMap = ['' => 0]; }
        $stockAlloc = $sOrder->stock_allocated ?? (int) collect($stockMap)->sum();
        $poAlloc = $sOrder->po_allocated ?? (int) collect($poMap)->sum();
        $totalAlloc = $stockAlloc + $poAlloc;
    @endphp
    <tr class="allocation-order-row" style="{{ $rowBg }}" data-order-id="{{ $sOrder->id }}" data-order-remaining="{{ $sOrder->remaining }}" data-order-code="{{ $sOrder->code }}">
        @if ($mergeOrderInfoInDue ?? false)
            <td class="col-due col-order-due">
                <div class="alloc-order-due">
                    <div class="alloc-order-due__code code-cell">
                        @if ($isCurrent)
                            <span class="alloc-order-due__code-text">{{ $sOrder->code }}</span>
                            <span class="badge badge-indigo badge--plain alloc-this-badge">この受注</span>
                        @else
                            <a href="{{ route('orders.show', $sOrder->id) }}" class="link-strong">{{ $sOrder->code }}</a>
                        @endif
                    </div>
                    <div class="alloc-order-due__customer" title="{{ $sOrder->customer }}">{{ $sOrder->customer }}</div>
                    <div class="alloc-order-due__date mono">{{ $sOrder->due_date }}</div>
                </div>
            </td>
        @else
            <td class="code-cell col-order">
                @if ($isCurrent)
                    <span style="color:#2563eb;font-weight:700;">{{ $sOrder->code }}</span>
                    <span class="badge badge-indigo badge--plain alloc-this-badge">この受注</span>
                @else
                    <a href="{{ route('orders.show', $sOrder->id) }}" class="link-strong">{{ $sOrder->code }}</a>
                @endif
            </td>
            <td class="col-customer" title="{{ $sOrder->customer }}">{{ $sOrder->customer }}</td>
            <td class="mono col-due">{{ $sOrder->due_date }}</td>
        @endif
        <td class="num mono col-remaining">@include('partials.qty', ['qty' => $sOrder->remaining, 'productId' => $productId])</td>

        {{-- 現在庫引当 --}}
        <td class="alloc-cell alloc-cell--stock">
            <div class="alloc-cell__label">現在庫引当</div>
            <div class="po-lines" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $stockType }}">
                @foreach ($stockMap as $poId => $qty)
                    @php $safePoId = ($poId !== '') ? $poId : '__NEW__'; @endphp
                    <div class="po-line">
                        <select class="po-line__select input" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $stockType }}">
                            <option value="">— 発注を選択 —</option>
                            @foreach ($stockPoOptions as $po)
                                <option value="{{ $po->id }}" data-qty="{{ $po->qty }}" @selected((int) $poId === $po->id)>{{ $po->label }}</option>
                            @endforeach
                        </select>
                        <div class="po-line__qty-wrap">
                            <div class="qty-unit-field po-line__qty-field"
                                 data-qty-unit-field
                                 data-page-key="{{ $allocPageKey }}"
                                 data-meters-per-tan="{{ $allocMetersPerTan }}"
                                 data-max-meters="{{ $sOrder->remaining }}">
                                <input type="hidden"
                                       name="allocations[{{ $sOrder->id }}][{{ $stockType }}][{{ $safePoId }}]"
                                       value="{{ $qty }}"
                                       data-qty-meters-hidden>
                                <div class="input-group po-line__input-group">
                                    <input class="input mono" type="number" data-qty-display min="0" step="0.01" placeholder="0">
                                    <span class="input-group__suffix" data-qty-suffix>反</span>
                                </div>
                                <p class="field-hint qty-unit-field__hint" data-qty-hint></p>
                            </div>
                        </div>
                        <button type="button" class="btn-icon po-line__remove" title="削除">@include('partials.icon', ['name' => 'close'])</button>
                    </div>
                @endforeach
            </div>
            <div class="po-lines__footer">
                <button type="button" class="btn btn-ghost btn-sm po-lines__add" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $stockType }}">
                    @include('partials.icon', ['name' => 'plus']) 行を追加
                </button>
                <span class="mono t-muted alloc-stock-total" data-order-id="{{ $sOrder->id }}">@include('partials.qty', ['qty' => $stockAlloc, 'productId' => $productId])</span>
            </div>
        </td>

        {{-- 発注引当 --}}
        <td class="alloc-cell alloc-cell--po">
            <div class="alloc-cell__label">発注引当</div>
            <div class="po-lines" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $poType }}">
                @foreach ($poMap as $poId => $qty)
                    @php $safePoId = ($poId !== '') ? $poId : '__NEW__'; @endphp
                    <div class="po-line">
                        <select class="po-line__select input" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $poType }}">
                            <option value="">— 発注を選択 —</option>
                            @foreach ($poPoOptions as $po)
                                <option value="{{ $po->id }}" data-qty="{{ $po->qty }}" @selected((int) $poId === $po->id)>{{ $po->label }}</option>
                            @endforeach
                        </select>
                        <div class="po-line__qty-wrap">
                            <div class="qty-unit-field po-line__qty-field"
                                 data-qty-unit-field
                                 data-page-key="{{ $allocPageKey }}"
                                 data-meters-per-tan="{{ $allocMetersPerTan }}"
                                 data-max-meters="{{ $sOrder->remaining }}">
                                <input type="hidden"
                                       name="allocations[{{ $sOrder->id }}][{{ $poType }}][{{ $safePoId }}]"
                                       value="{{ $qty }}"
                                       data-qty-meters-hidden>
                                <div class="input-group po-line__input-group">
                                    <input class="input mono" type="number" data-qty-display min="0" step="0.01" placeholder="0">
                                    <span class="input-group__suffix" data-qty-suffix>反</span>
                                </div>
                                <p class="field-hint qty-unit-field__hint" data-qty-hint></p>
                            </div>
                        </div>
                        <button type="button" class="btn-icon po-line__remove" title="削除">@include('partials.icon', ['name' => 'close'])</button>
                    </div>
                @endforeach
            </div>
            <div class="po-lines__footer">
                <button type="button" class="btn btn-ghost btn-sm po-lines__add" data-order-id="{{ $sOrder->id }}" data-alloc-type="{{ $poType }}">
                    @include('partials.icon', ['name' => 'plus']) 行を追加
                </button>
                <span class="mono t-muted alloc-po-total" data-order-id="{{ $sOrder->id }}">@include('partials.qty', ['qty' => $poAlloc, 'productId' => $productId])</span>
            </div>
        </td>

        <td class="col-progress">
            <div class="alloc-progress">
                <div class="alloc-progress__track">
                    <div class="alloc-bar-fill" data-bar-order="{{ $sOrder->id }}"
                         style="height:100%;background:{{ $isCurrent ? '#3b82f6' : '#f59e0b' }};width:{{ $sOrder->remaining > 0 ? round($totalAlloc / $sOrder->remaining * 100) : 0 }}%;transition:width 0.2s;"></div>
                </div>
                <span class="mono alloc-bar-pct alloc-progress__pct" data-pct-order="{{ $sOrder->id }}">
                    {{ $sOrder->remaining > 0 ? round($totalAlloc / $sOrder->remaining * 100) : 0 }}%
                </span>
            </div>
            <div class="alloc-progress__detail">
                在庫 @include('partials.qty', ['qty' => $stockAlloc, 'productId' => $productId]) + 発注 @include('partials.qty', ['qty' => $poAlloc, 'productId' => $productId])
            </div>
        </td>
        <td class="col-status">
            @if ($sOrder->allocation_status ?? null)
                <span class="badge {{ $sOrder->allocation_badge ?? 'badge-rose' }} alloc-status-badge">{{ $sOrder->allocation_status }}</span>
            @else
                <span class="badge badge-rose alloc-status-badge">未引当</span>
            @endif
        </td>
    </tr>
@endforeach

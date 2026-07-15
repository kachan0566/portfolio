@php
    $idx = $index;
    $isTemplate = $idx === '__INDEX__';
@endphp
<div class="po-line-row" data-po-line-row @if(!$isTemplate) data-line-index="{{ $idx }}" @endif style="border-top:1px solid var(--border-subtle,#e2e8f0);padding:12px 0;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <span class="t-muted" style="font-size:13px;" data-line-label>明細行 @if(!$isTemplate){{ (int) $idx + 1 }}@endif</span>
        @if ($showRemove)
            <button type="button" class="btn btn-secondary btn-sm" data-remove-po-line>削除</button>
        @endif
    </div>

    @if ($type === \App\Support\PurchaseOrderType::YARN)
        <div class="form-row">
            <div class="field">
                <label class="label">糸品番<span class="req">*</span></label>
                <select class="select" name="lines[{{ $idx }}][material_id]" required data-yarn-material>
                    @foreach ($yarnMaterials as $m)
                        <option value="{{ $m->id }}" @selected((string) ($line['material_id'] ?? '') === (string) $m->id)>
                            {{ $m->sku }}（{{ $m->name }}）
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label">発注数量（kg）<span class="req">*</span></label>
                <input class="input mono" type="number" name="lines[{{ $idx }}][qty_kg]" step="0.01" min="0.01"
                       value="{{ $line['qty_kg'] ?? '' }}" required>
            </div>
        </div>
    @elseif ($type === \App\Support\PurchaseOrderType::GREIGE)
        <div class="form-row">
            <div class="field">
                <label class="label">生機品番<span class="req">*</span></label>
                <select class="select" name="lines[{{ $idx }}][greige_sku]" required data-greige-select>
                    @foreach ($greiges as $g)
                        <option value="{{ $g->sku }}" @selected(($line['greige_sku'] ?? '') === $g->sku)>
                            {{ $g->sku }}（{{ $g->name }}）
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label">標準反長（m/反）</label>
                <input class="input mono" type="text" readonly data-greige-meters-per-tan value="—">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label">発注反数<span class="req">*</span></label>
                <input class="input mono" type="number" name="lines[{{ $idx }}][qty_tan]" step="1" min="1"
                       value="{{ $line['qty_tan'] ?? '' }}" required data-greige-tan>
            </div>
            <div class="field">
                <label class="label">総m数（自動）</label>
                <input class="input mono" type="text" readonly data-greige-total-m value="—">
            </div>
        </div>
    @else
        <div class="form-row">
            <div class="field">
                <label class="label">製品品番<span class="req">*</span></label>
                <select class="select" name="lines[{{ $idx }}][product_id]" required data-product-select>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}"
                                @selected(($sourceOrder && $p->id === $sourceOrder->product_id && $idx === 0) || (string) ($line['product_id'] ?? '') === (string) $p->id)>
                            {{ $p->sku }}（{{ $p->color }}）
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="label">標準反長（m/反）</label>
                <input class="input mono" type="text" readonly data-product-meters-per-tan value="—">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="label">発注反数<span class="req">*</span></label>
                <input class="input mono" type="number" name="lines[{{ $idx }}][product_qty_tan]" step="1" min="1"
                       value="{{ $line['product_qty_tan'] ?? '' }}" data-product-tan>
            </div>
            <div class="field">
                <label class="label">総m数（自動）</label>
                <input type="hidden" name="lines[{{ $idx }}][qty_meters]" data-qty-meters-hidden
                       value="{{ $line['qty_meters'] ?? ($idx === 0 ? ($suggestedMeters ?? '') : '') }}">
                <input class="input mono" type="text" readonly data-product-total-m value="—">
            </div>
        </div>
    @endif
</div>

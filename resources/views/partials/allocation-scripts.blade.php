@include('partials.qty-unit-loader')
<script>
(function () {
    const metaEl = document.getElementById('alloc-meta');
    if (!metaEl) return;

    const meta = JSON.parse(metaEl.textContent);
    const stockOptions = JSON.parse(document.getElementById('stock-po-options')?.textContent || '[]');
    const poOptions = JSON.parse(document.getElementById('po-po-options')?.textContent || '[]');
    const PAGE_KEY = 'allocation';

    const metersPerTan = meta.metersPerTan || 50;
    const stockTan = typeof meta.stockTan === 'number'
        ? meta.stockTan
        : QtyUnit.metersToTan(meta.stock || 0, metersPerTan);
    const stockMeters = typeof meta.stockMeters === 'number'
        ? meta.stockMeters
        : (meta.stock || 0);
    const currentId = meta.currentOrderId;

    const stockTanById = Object.fromEntries(stockOptions.map(po => [
        String(po.id),
        typeof po.qty_tan === 'number' ? po.qty_tan : QtyUnit.metersToTan(po.qty || 0, metersPerTan),
    ]));
    const stockCodeById = Object.fromEntries(stockOptions.map(po => [String(po.id), po.code]));
    const poTanById = Object.fromEntries(poOptions.map(po => [
        String(po.id),
        typeof po.qty_tan === 'number' ? po.qty_tan : QtyUnit.metersToTan(po.qty || 0, metersPerTan),
    ]));
    const poQtyById = Object.fromEntries(poOptions.map(po => [String(po.id), po.qty || 0]));
    const poCodeById = Object.fromEntries(poOptions.map(po => [String(po.id), po.code]));

    const allocationForm = document.getElementById('allocation-form');
    const TYPE_STOCK = 'stock';
    const TYPE_PO = 'po';

    const qtyUnitApi = QtyUnit.initPage(PAGE_KEY, {
        onInit(api) {
            api.setMetersPerTan(metersPerTan);
        },
    });

    function formatQty(m) {
        return QtyUnit.formatQty(m, metersPerTan);
    }

    /** 現在庫引当バー用（反明細の tan / m 比で表示mを按分） */
    function formatStockQty(tan) {
        const t = QtyUnit.roundTan(tan);
        let m = 0;
        if (stockTan > 0 && stockMeters > 0) {
            m = Math.round((t / stockTan) * stockMeters * 100) / 100;
        }
        return QtyUnit.formatTanCount(t) + '反 / ' + m.toLocaleString(undefined, { maximumFractionDigits: 2 }) + 'm';
    }

    function readLineTan(line) {
        return parseFloat(
            line.querySelector('[data-qty-tan-hidden]')?.value
            || '0'
        ) || 0;
    }

    function readLineMeters(line) {
        const tan = readLineTan(line);
        return QtyUnit.tanToMeters(tan, metersPerTan);
    }

    function optionsForType(type) {
        return type === TYPE_STOCK ? stockOptions : poOptions;
    }

    function tanById(type) {
        return type === TYPE_STOCK ? stockTanById : poTanById;
    }

    function codeById(type) {
        return type === TYPE_STOCK ? stockCodeById : poCodeById;
    }

    function updateBar(orderId, stockSum, poSum, remaining) {
        const total = stockSum + poSum;
        const rate = remaining > 0 ? Math.round(total / remaining * 100) : 0;
        const fill = document.querySelector('.alloc-bar-fill[data-bar-order="' + orderId + '"]');
        const pct = document.querySelector('.alloc-bar-pct[data-pct-order="' + orderId + '"]');
        if (fill) fill.style.width = rate + '%';
        if (pct) pct.textContent = rate + '%';
        const stockTotalEl = document.querySelector('.alloc-stock-total[data-order-id="' + orderId + '"]');
        const poTotalEl = document.querySelector('.alloc-po-total[data-order-id="' + orderId + '"]');
        if (stockTotalEl) stockTotalEl.textContent = formatQty(stockSum);
        if (poTotalEl) poTotalEl.textContent = formatQty(poSum);
    }

    function syncSelectName(select) {
        const type = select.dataset.allocType;
        const poId = select.value || '__NEW__';
        const orderId = select.dataset.orderId;
        const line = select.closest('.po-line');
        const field = line.querySelector('[data-qty-unit-field]');
        const hidden = line.querySelector('[data-qty-tan-hidden]');
        const row = line.closest('tr');
        const remainingMeters = parseInt(row?.dataset.orderRemaining || '0', 10);
        const remainingTan = QtyUnit.metersToTan(remainingMeters, metersPerTan);
        const poMaxTan = poId ? (tanById(type)[poId] || 0) : 0;
        const maxTan = poId ? Math.min(remainingTan, poMaxTan) : remainingTan;
        if (hidden) {
            hidden.name = `allocations[${orderId}][${type}][${poId}]`;
        }
        if (field) {
            field.dataset.maxTan = String(maxTan);
            qtyUnitApi.refresh();
        }
    }

    function sumContainerMeters(container) {
        let sum = 0;
        container.querySelectorAll('.po-line').forEach(line => {
            sum += readLineMeters(line);
        });
        return sum;
    }

    function sumContainerTan(container) {
        let sum = 0;
        container.querySelectorAll('.po-line').forEach(line => {
            sum += readLineTan(line);
        });
        return QtyUnit.roundTan(sum);
    }

    function updateOrderRow(orderId) {
        const row = document.querySelector('.allocation-order-row[data-order-id="' + orderId + '"]');
        if (!row) return;
        const remaining = parseInt(row.dataset.orderRemaining, 10);
        const stockContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_STOCK + '"]');
        const poContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_PO + '"]');
        const stockSum = stockContainer ? sumContainerMeters(stockContainer) : 0;
        const poSum = poContainer ? sumContainerMeters(poContainer) : 0;
        updateBar(orderId, stockSum, poSum, remaining);
        recalcBudget();
    }

    function recalcBudget() {
        let totalStockAllocTan = 0;
        let thisStockAllocTan = 0;

        document.querySelectorAll('.allocation-order-row').forEach(row => {
            const orderId = row.dataset.orderId;
            const stockContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_STOCK + '"]');
            const sumTan = stockContainer ? sumContainerTan(stockContainer) : 0;
            totalStockAllocTan += sumTan;
            if (parseInt(orderId, 10) === currentId) {
                thisStockAllocTan = sumTan;
            }
        });

        const otherStockAllocTan = QtyUnit.roundTan(totalStockAllocTan - thisStockAllocTan);
        thisStockAllocTan = QtyUnit.roundTan(thisStockAllocTan);
        totalStockAllocTan = QtyUnit.roundTan(totalStockAllocTan);
        const freeStockTan = Math.max(0, QtyUnit.roundTan(stockTan - totalStockAllocTan));
        const overBudget = totalStockAllocTan > stockTan + 0.0001;

        const otherPct = stockTan > 0 ? Math.round(otherStockAllocTan / stockTan * 100) : 0;
        const thisPct = stockTan > 0 && !overBudget ? Math.round(thisStockAllocTan / stockTan * 100) : 0;
        const freePct = overBudget ? 0 : Math.max(0, 100 - otherPct - thisPct);

        ['budget-bar-others', 'budget-bar-this', 'budget-bar-free'].forEach((id, i) => {
            const el = document.getElementById(id);
            if (!el) return;
            const pcts = [otherPct, overBudget ? 100 - otherPct : thisPct, freePct];
            el.style.width = pcts[i] + '%';
        });
        const barThis = document.getElementById('budget-bar-this');
        if (barThis) barThis.style.background = overBudget ? '#ef4444' : '#3b82f6';

        const setStockText = (id, tan) => {
            const el = document.getElementById(id);
            if (el) el.textContent = formatStockQty(tan);
        };
        setStockText('budget-other-text', otherStockAllocTan);
        setStockText('budget-this-text', thisStockAllocTan);
        setStockText('budget-free-text', freeStockTan);
        setStockText('total-stock-allocated-text', totalStockAllocTan);

        const warn = document.getElementById('budget-over-warning');
        if (warn) warn.style.display = overBudget ? 'inline-flex' : 'none';
        const btn = document.getElementById('alloc-submit-btn');
        if (btn) btn.disabled = overBudget;
    }

    function createPoLine(orderId, type) {
        const opts = optionsForType(type).map(po =>
            `<option value="${po.id}" data-qty-tan="${po.qty_tan ?? ''}">${po.label || po.code}</option>`
        ).join('');
        const placeholder = '— 発注を選択 —';
        const div = document.createElement('div');
        div.className = 'po-line';
        div.innerHTML = `
            <select class="po-line__select input" data-order-id="${orderId}" data-alloc-type="${type}">
                <option value="">${placeholder}</option>${opts}
            </select>
            <div class="po-line__qty-wrap">
                <div class="qty-unit-field qty-unit-field--compact po-line__qty-field"
                     data-qty-unit-field
                     data-page-key="${PAGE_KEY}"
                     data-qty-mode="tan"
                     data-meters-per-tan="${metersPerTan}"
                     data-tan-step="0.25">
                    <input type="hidden"
                           name="allocations[${orderId}][${type}][__NEW__]"
                           value="0"
                           data-qty-tan-hidden>
                    <input type="hidden" value="" data-qty-meters-hidden>
                    <div data-qty-tan-row>
                        <div class="input-group po-line__input-group">
                            <input class="input mono" type="number" data-qty-tan-display min="0" step="0.25" placeholder="0">
                            <span class="input-group__suffix">反</span>
                        </div>
                    </div>
                    <div data-qty-meter-row hidden>
                        <div class="input-group po-line__input-group">
                            <input class="input mono" type="number" data-qty-meter-display min="0" step="1" placeholder="0">
                            <span class="input-group__suffix">m</span>
                        </div>
                    </div>
                    <p class="field-hint qty-unit-field__hint" data-qty-hint></p>
                </div>
            </div>
            <button type="button" class="btn-icon po-line__remove" title="削除">×</button>`;
        const field = div.querySelector('[data-qty-unit-field]');
        if (field) qtyUnitApi.bindField(field);
        field?.addEventListener('qty-meters-changed', () => updateOrderRow(orderId));
        div.querySelector('.po-line__select').addEventListener('change', function () {
            syncSelectName(this);
            updateOrderRow(orderId);
        });
        div.querySelector('.po-line__remove').addEventListener('click', function () {
            div.remove();
            updateOrderRow(orderId);
        });
        return div;
    }

    function bindLine(line) {
        const select = line.querySelector('.po-line__select');
        const field = line.querySelector('[data-qty-unit-field]');
        const removeBtn = line.querySelector('.po-line__remove');
        const orderId = line.closest('.po-lines')?.dataset.orderId;
        if (select) {
            syncSelectName(select);
            select.addEventListener('change', function () { syncSelectName(this); updateOrderRow(orderId); });
        }
        if (field) {
            field.addEventListener('qty-meters-changed', () => updateOrderRow(orderId));
        }
        if (removeBtn) removeBtn.addEventListener('click', function () { line.remove(); updateOrderRow(orderId); });
    }

    document.querySelectorAll('.po-line').forEach(bindLine);

    document.querySelectorAll('.po-lines__add').forEach(btn => {
        btn.addEventListener('click', function () {
            const orderId = this.dataset.orderId;
            const type = this.dataset.allocType;
            const container = document.querySelector(`.po-lines[data-order-id="${orderId}"][data-alloc-type="${type}"]`);
            container.appendChild(createPoLine(orderId, type));
        });
    });

    function validateAllocationForm() {
        const stockUsageByPoTan = {};
        const poUsageByPo = {};
        let totalStockAllocTan = 0;
        let firstError = '';

        document.querySelectorAll('.allocation-order-row').forEach(row => {
            const orderCode = row.dataset.orderCode || row.querySelector('.code-cell')?.textContent?.trim() || '受注';
            const remaining = parseInt(row.dataset.orderRemaining, 10);
            let orderStockM = 0;
            let orderPoM = 0;

            row.querySelectorAll('.po-lines').forEach(container => {
                const type = container.dataset.allocType;
                container.querySelectorAll('.po-line').forEach(line => {
                    const poId = line.querySelector('.po-line__select')?.value || '';
                    if (!poId) {
                        if (readLineTan(line) > 0) {
                            firstError ||= '数量を入力する場合は、発注を選択してください。';
                        }
                        return;
                    }
                    const poCode = codeById(type)[poId] || '発注';
                    const typeLabel = type === TYPE_STOCK ? '現在庫引当' : '発注引当';

                    if (type === TYPE_STOCK) {
                        const qtyTan = QtyUnit.roundTan(readLineTan(line));
                        if (qtyTan <= 0) return;
                        const poMaxTan = stockTanById[poId] || 0;
                        const usedTan = QtyUnit.roundTan((stockUsageByPoTan[poId] || 0) + qtyTan);
                        if (qtyTan > poMaxTan + 0.0001) {
                            firstError ||= `${poCode} への${typeLabel}（${formatStockQty(qtyTan)}）が上限（${formatStockQty(poMaxTan)}）を超えています。`;
                        }
                        if (usedTan > poMaxTan + 0.0001) {
                            firstError ||= `${poCode} への${typeLabel}合計（${formatStockQty(usedTan)}）が上限（${formatStockQty(poMaxTan)}）を超えています。`;
                        }
                        stockUsageByPoTan[poId] = usedTan;
                        orderStockM += readLineMeters(line);
                        totalStockAllocTan += qtyTan;
                    } else {
                        const qtyM = readLineMeters(line);
                        if (qtyM <= 0) return;
                        const poMaxM = poQtyById[poId] || 0;
                        const usedM = (poUsageByPo[poId] || 0) + qtyM;
                        if (qtyM > poMaxM) {
                            firstError ||= `${poCode} への${typeLabel}（${formatQty(qtyM)}）が上限（${formatQty(poMaxM)}）を超えています。`;
                        }
                        if (usedM > poMaxM) {
                            firstError ||= `${poCode} への${typeLabel}合計（${formatQty(usedM)}）が上限（${formatQty(poMaxM)}）を超えています。`;
                        }
                        poUsageByPo[poId] = usedM;
                        orderPoM += qtyM;
                    }
                });
            });

            if (orderStockM + orderPoM > remaining) {
                firstError ||= `${orderCode} の引当合計が受注残を超えています。`;
            }
        });

        if (QtyUnit.roundTan(totalStockAllocTan) > stockTan + 0.0001) {
            firstError ||= '現在庫引当合計が現在庫（' + formatStockQty(stockTan) + '）を超えています。数量を見直してください。';
        }
        return firstError;
    }

    if (allocationForm) {
        allocationForm.addEventListener('submit', function (event) {
            document.querySelectorAll('[data-qty-unit-field][data-page-key="' + PAGE_KEY + '"]').forEach(field => {
                qtyUnitApi.readMeters(field);
            });
            const error = validateAllocationForm();
            if (error) { event.preventDefault(); alert(error); }
        });
    }

    document.querySelectorAll('.allocation-order-row').forEach(row => updateOrderRow(row.dataset.orderId));
    recalcBudget();
})();
</script>

@include('partials.qty-unit-loader')
<script>
(function () {
    const metaEl = document.getElementById('alloc-meta');
    if (!metaEl) return;

    const meta = JSON.parse(metaEl.textContent);
    const stockOptions = JSON.parse(document.getElementById('stock-po-options')?.textContent || '[]');
    const poOptions = JSON.parse(document.getElementById('po-po-options')?.textContent || '[]');
    const PAGE_KEY = 'allocation';

    const stock = meta.stock;
    const currentId = meta.currentOrderId;
    const metersPerTan = meta.metersPerTan || 50;
    const stockQtyById = Object.fromEntries(stockOptions.map(po => [String(po.id), po.qty]));
    const stockCodeById = Object.fromEntries(stockOptions.map(po => [String(po.id), po.code]));
    const poQtyById = Object.fromEntries(poOptions.map(po => [String(po.id), po.qty]));
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

    function readLineMeters(line) {
        // 隠しフィールドから直接読む。qtyUnitApi.readMeters() を使うと
        // qty-meters-changed イベントが発火して updateOrderRow が再帰呼び出しされるため使わない。
        // フォーム送信時は送信ハンドラ内で readMeters() を一括実行済み。
        return parseInt(
            line.querySelector('[data-qty-meters-hidden]')?.value
            || line.querySelector('.po-line__qty')?.value
            || '0',
            10
        ) || 0;
    }

    function optionsForType(type) {
        return type === TYPE_STOCK ? stockOptions : poOptions;
    }

    function qtyById(type) {
        return type === TYPE_STOCK ? stockQtyById : poQtyById;
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
        const hidden = line.querySelector('[data-qty-meters-hidden]');
        const row = line.closest('tr');
        const remaining = parseInt(row?.dataset.orderRemaining || '0', 10);
        const poMax = poId ? (qtyById(type)[poId] || 0) : 0;
        const maxMeters = poId ? Math.min(remaining, poMax) : remaining;
        if (hidden) {
            hidden.name = `allocations[${orderId}][${type}][${poId}]`;
        }
        if (field) {
            field.dataset.maxMeters = String(maxMeters);
            qtyUnitApi.refresh();
        }
    }

    function sumContainer(container) {
        let sum = 0;
        container.querySelectorAll('.po-line').forEach(line => {
            sum += readLineMeters(line);
        });
        return sum;
    }

    function updateOrderRow(orderId) {
        const row = document.querySelector('.allocation-order-row[data-order-id="' + orderId + '"]');
        if (!row) return;
        const remaining = parseInt(row.dataset.orderRemaining, 10);
        const stockContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_STOCK + '"]');
        const poContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_PO + '"]');
        const stockSum = stockContainer ? sumContainer(stockContainer) : 0;
        const poSum = poContainer ? sumContainer(poContainer) : 0;
        updateBar(orderId, stockSum, poSum, remaining);
        recalcBudget();
    }

    function recalcBudget() {
        let totalStockAlloc = 0;
        let thisStockAlloc = 0;

        document.querySelectorAll('.allocation-order-row').forEach(row => {
            const orderId = row.dataset.orderId;
            const stockContainer = row.querySelector('.po-lines[data-alloc-type="' + TYPE_STOCK + '"]');
            const sum = stockContainer ? sumContainer(stockContainer) : 0;
            totalStockAlloc += sum;
            if (parseInt(orderId, 10) === currentId) thisStockAlloc = sum;
        });

        const otherStockAlloc = totalStockAlloc - thisStockAlloc;
        const freeStock = Math.max(0, stock - totalStockAlloc);
        const overBudget = totalStockAlloc > stock;

        const otherPct = stock > 0 ? Math.round(otherStockAlloc / stock * 100) : 0;
        const thisPct = stock > 0 ? Math.round(thisStockAlloc / stock * 100) : 0;
        const freePct = Math.max(0, 100 - otherPct - thisPct);

        ['budget-bar-others', 'budget-bar-this', 'budget-bar-free'].forEach((id, i) => {
            const el = document.getElementById(id);
            if (!el) return;
            const pcts = [otherPct, overBudget ? 100 - otherPct : thisPct, overBudget ? 0 : freePct];
            el.style.width = pcts[i] + '%';
        });
        const barThis = document.getElementById('budget-bar-this');
        if (barThis) barThis.style.background = overBudget ? '#ef4444' : '#3b82f6';

        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = formatQty(val); };
        setText('budget-other-text', otherStockAlloc);
        setText('budget-this-text', thisStockAlloc);
        setText('budget-free-text', freeStock);
        setText('total-stock-allocated-text', totalStockAlloc);
        const totalFree = document.getElementById('total-free-text');
        if (totalFree) totalFree.textContent = formatQty(freeStock);

        const warn = document.getElementById('budget-over-warning');
        if (warn) warn.style.display = overBudget ? 'inline-flex' : 'none';
        const btn = document.getElementById('alloc-submit-btn');
        if (btn) btn.disabled = overBudget;
    }

    function createPoLine(orderId, type) {
        const opts = optionsForType(type).map(po =>
            `<option value="${po.id}" data-qty="${po.qty}">${po.label || po.code}</option>`
        ).join('');
        const placeholder = '— 発注を選択 —';
        const div = document.createElement('div');
        div.className = 'po-line';
        div.innerHTML = `
            <select class="po-line__select input" data-order-id="${orderId}" data-alloc-type="${type}">
                <option value="">${placeholder}</option>${opts}
            </select>
            <div class="po-line__qty-wrap">
                <div class="qty-unit-field po-line__qty-field"
                     data-qty-unit-field
                     data-page-key="${PAGE_KEY}"
                     data-meters-per-tan="${metersPerTan}"
                     data-max-meters="0">
                    <input type="hidden"
                           name="allocations[${orderId}][${type}][__NEW__]"
                           value="0"
                           data-qty-meters-hidden>
                    <div class="input-group po-line__input-group">
                        <input class="input mono" type="number" data-qty-display min="0" step="0.01" placeholder="0">
                        <span class="input-group__suffix" data-qty-suffix>反</span>
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
        const stockUsageByPo = {};
        const poUsageByPo = {};
        let totalStockAlloc = 0;
        let firstError = '';

        document.querySelectorAll('.allocation-order-row').forEach(row => {
            const orderCode = row.dataset.orderCode || row.querySelector('.code-cell')?.textContent?.trim() || '受注';
            const remaining = parseInt(row.dataset.orderRemaining, 10);
            let orderStock = 0;
            let orderPo = 0;

            row.querySelectorAll('.po-lines').forEach(container => {
                const type = container.dataset.allocType;
                container.querySelectorAll('.po-line').forEach(line => {
                    const qty = readLineMeters(line);
                    const poId = line.querySelector('.po-line__select')?.value || '';
                    if (qty === 0) return;
                    if (!poId) {
                        firstError ||= '数量を入力する場合は、発注を選択してください。';
                        return;
                    }
                    const poMax = qtyById(type)[poId] || 0;
                    const poCode = codeById(type)[poId] || '発注';
                    const usageMap = type === TYPE_STOCK ? stockUsageByPo : poUsageByPo;
                    const used = (usageMap[poId] || 0) + qty;
                    const typeLabel = type === TYPE_STOCK ? '現在庫引当' : '発注引当';
                    if (qty > poMax) {
                        firstError ||= `${poCode} への${typeLabel}（${formatQty(qty)}）が上限（${formatQty(poMax)}）を超えています。`;
                    }
                    if (used > poMax) {
                        firstError ||= `${poCode} への${typeLabel}合計（${formatQty(used)}）が上限（${formatQty(poMax)}）を超えています。`;
                    }
                    usageMap[poId] = used;
                    if (type === TYPE_STOCK) { orderStock += qty; totalStockAlloc += qty; }
                    else orderPo += qty;
                });
            });

            if (orderStock + orderPo > remaining) {
                firstError ||= `${orderCode} の引当合計が受注残を超えています。`;
            }
        });

        if (totalStockAlloc > stock) {
            firstError ||= '現在庫引当合計が現在庫を超えています。数量を見直してください。';
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
})();
</script>

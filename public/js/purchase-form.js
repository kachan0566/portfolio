(function () {
    'use strict';

    const greigeMeta = window.PURCHASE_GREIGE_META || {};
    const productMeta = window.PURCHASE_PRODUCT_META || {};
    const TAN_STEP = 0.05;

    function roundTan(tan) {
        const steps = Math.round((parseFloat(tan) || 0) / TAN_STEP);
        return Math.round(steps * TAN_STEP * 100) / 100;
    }

    function roundMeters(tan, perTan) {
        return Math.round(roundTan(tan) * (parseInt(perTan, 10) || 0));
    }

    function formatTan(tan) {
        return roundTan(tan).toFixed(2).replace(/\.?0+$/, '') || '0';
    }

    function calcGreigeYarn(sku, meters) {
        const meta = greigeMeta[sku];
        if (!meta || meters <= 0) return [];
        const loss = 1 + (parseFloat(meta.loss_rate) || 0);
        const lines = meta.lines || [];
        return lines.map(function (line) {
            const materialId = line[0];
            const perM = parseFloat(line[1]) || 0;
            const kg = Math.round(perM * meters * loss * 100) / 100;
            return { materialId: materialId, kg: kg };
        });
    }

    function renderYarnPreview() {
        const el = document.getElementById('yarn-requirements-preview');
        if (!el) return;

        const totals = {};
        document.querySelectorAll('[data-po-line-row]').forEach(function (row) {
            const skuSelect = row.querySelector('[data-greige-select]');
            const tanInput = row.querySelector('[data-greige-tan]');
            if (!skuSelect || !tanInput) return;
            const sku = skuSelect.value;
            const meta = greigeMeta[sku] || {};
            const perTan = meta.meters_per_tan || 100;
            const meters = roundMeters(tanInput.value, perTan);
            calcGreigeYarn(sku, meters).forEach(function (r) {
                totals[r.materialId] = (totals[r.materialId] || 0) + r.kg;
            });
        });

        const keys = Object.keys(totals);
        if (!keys.length) {
            el.textContent = '明細行を入力すると表示されます。';
            return;
        }

        el.innerHTML = keys.map(function (id) {
            return '糸ID ' + id + ': <strong class="mono">' + totals[id].toFixed(2) + ' kg</strong>';
        }).join('<br>');
    }

    function initGreigeRow(row) {
        const skuSelect = row.querySelector('[data-greige-select]');
        const tanInput = row.querySelector('[data-greige-tan]');
        const perTanEl = row.querySelector('[data-greige-meters-per-tan]');
        const totalEl = row.querySelector('[data-greige-total-m]');
        if (!skuSelect || !tanInput) return;

        function sync() {
            const sku = skuSelect.value;
            const meta = greigeMeta[sku] || {};
            const perTan = meta.meters_per_tan || 100;
            if (perTanEl) perTanEl.value = perTan + ' m/反（標準）';
            const roundedTan = roundTan(tanInput.value);
            tanInput.value = formatTan(roundedTan);
            const meters = roundMeters(roundedTan, perTan);
            if (totalEl) totalEl.value = formatTan(roundedTan) + '反 / ' + meters.toLocaleString() + 'm';
            renderYarnPreview();
        }

        skuSelect.addEventListener('change', sync);
        tanInput.addEventListener('input', sync);
        tanInput.addEventListener('change', sync);
        sync();
    }

    function initProductRow(row) {
        const productSelect = row.querySelector('[data-product-select]');
        const tanInput = row.querySelector('[data-product-tan]');
        const perTanEl = row.querySelector('[data-product-meters-per-tan]');
        const totalEl = row.querySelector('[data-product-total-m]');
        const hiddenMeters = row.querySelector('[data-qty-meters-hidden]');
        if (!productSelect || !tanInput) return;

        function sync() {
            const id = productSelect.value;
            const meta = productMeta[id] || {};
            const perTan = meta.meters_per_tan || 50;
            if (perTanEl) perTanEl.value = perTan + ' m/反（標準）';
            const roundedTan = roundTan(tanInput.value);
            tanInput.value = formatTan(roundedTan);
            const meters = roundMeters(roundedTan, perTan);
            if (totalEl) totalEl.value = formatTan(roundedTan) + '反 / ' + meters.toLocaleString() + 'm';
            if (hiddenMeters) hiddenMeters.value = meters > 0 ? String(meters) : '';
        }

        productSelect.addEventListener('change', sync);
        tanInput.addEventListener('input', sync);
        tanInput.addEventListener('change', sync);

        if (hiddenMeters && hiddenMeters.value) {
            const id = productSelect.value;
            const perTan = (productMeta[id] || {}).meters_per_tan || 50;
            tanInput.value = formatTan((parseInt(hiddenMeters.value, 10) || 0) / perTan);
        }
        sync();
    }

    function initRow(row) {
        if (row.querySelector('[data-greige-select]')) {
            initGreigeRow(row);
        }
        if (row.querySelector('[data-product-select]')) {
            initProductRow(row);
        }
    }

    document.querySelectorAll('[data-po-line-row]').forEach(initRow);

    window.PurchaseForm = {
        initRow: initRow,
        refreshGreigePreview: renderYarnPreview,
    };
})();

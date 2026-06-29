(function () {
    'use strict';

    const greigeMeta = window.PURCHASE_GREIGE_META || {};
    const productMeta = window.PURCHASE_PRODUCT_META || {};

    function roundMeters(tan, perTan) {
        return Math.round((parseFloat(tan) || 0) * (parseInt(perTan, 10) || 0));
    }

    function formatTan(tan) {
        return (parseFloat(tan) || 0).toFixed(2).replace(/\.?0+$/, '') || '0';
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

    function renderYarnPreview(sku, meters) {
        const el = document.getElementById('yarn-requirements-preview');
        if (!el) return;
        const reqs = calcGreigeYarn(sku, meters);
        if (!reqs.length) {
            el.textContent = '生機品番と反数を入力すると表示されます。';
            return;
        }
        el.innerHTML = reqs.map(function (r) {
            return '糸ID ' + r.materialId + ': <strong class="mono">' + r.kg + ' kg</strong>';
        }).join('<br>');
    }

    function initGreige() {
        const skuSelect = document.querySelector('[data-greige-select]');
        const tanInput = document.querySelector('[data-greige-tan]');
        const perTanEl = document.getElementById('greige_meters_per_tan');
        const totalEl = document.getElementById('greige_total_m');
        if (!skuSelect || !tanInput) return;

        function sync() {
            const sku = skuSelect.value;
            const meta = greigeMeta[sku] || {};
            const perTan = meta.meters_per_tan || 100;
            perTanEl.value = perTan + ' m/反';
            const meters = roundMeters(tanInput.value, perTan);
            totalEl.value = formatTan(tanInput.value) + '反 / ' + meters.toLocaleString() + 'm';
            renderYarnPreview(sku, meters);
        }

        skuSelect.addEventListener('change', sync);
        tanInput.addEventListener('input', sync);
        sync();
    }

    function initProduct() {
        const productSelect = document.querySelector('[data-product-select]');
        const tanInput = document.querySelector('[data-product-tan]');
        const perTanEl = document.getElementById('product_meters_per_tan');
        const totalEl = document.getElementById('product_total_m');
        const hiddenMeters = document.getElementById('qty_meters');
        if (!productSelect || !tanInput) return;

        function sync() {
            const id = productSelect.value;
            const meta = productMeta[id] || {};
            const perTan = meta.meters_per_tan || 50;
            perTanEl.value = perTan + ' m/反';
            const meters = roundMeters(tanInput.value, perTan);
            totalEl.value = formatTan(tanInput.value) + '反 / ' + meters.toLocaleString() + 'm';
            if (hiddenMeters) hiddenMeters.value = meters > 0 ? String(meters) : '';
        }

        productSelect.addEventListener('change', sync);
        tanInput.addEventListener('input', sync);

        if (hiddenMeters && hiddenMeters.value) {
            const id = productSelect.value;
            const perTan = (productMeta[id] || {}).meters_per_tan || 50;
            tanInput.value = formatTan((parseInt(hiddenMeters.value, 10) || 0) / perTan);
        }
        sync();
    }

    initGreige();
    initProduct();
})();

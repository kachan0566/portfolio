(function () {
    const panel = document.getElementById('recipe-profit-panel');
    const processingInput = document.getElementById('processing_cost');
    const priceInput = document.getElementById('price');

    if (!panel || !processingInput || !priceInput) {
        return;
    }

    const els = {
        greige: panel.querySelector('[data-profit-greige]'),
        processing: panel.querySelector('[data-profit-processing]'),
        total: panel.querySelector('[data-profit-total]'),
        price: panel.querySelector('[data-profit-price]'),
        profit: panel.querySelector('[data-profit-amount]'),
        margin: panel.querySelector('[data-profit-margin]'),
        profitBox: panel.querySelector('[data-profit-row="profit"]'),
    };

    function formatYen(value) {
        return Math.round(value).toLocaleString('ja-JP') + ' 円/m';
    }

    function setProfitTone(profit) {
        if (!els.profitBox) {
            return;
        }

        const positive = profit !== null && profit >= 0;
        const negative = profit !== null && profit < 0;

        els.profitBox.classList.toggle('is-positive', positive);
        els.profitBox.classList.toggle('is-negative', negative);
    }

    function setUncalculable() {
        if (els.total) {
            els.total.innerHTML = '<span class="t-muted">算出不可</span>';
        }
        if (els.profit) {
            els.profit.innerHTML = '<span class="t-muted">算出不可</span>';
        }
        if (els.margin) {
            els.margin.innerHTML = '<span class="t-muted">粗利率 —</span>';
        }
        setProfitTone(null);
    }

    function recalc() {
        const calculable = panel.dataset.calculable === '1';
        const greigeCost = parseFloat(panel.dataset.greigeCost);
        const processing = parseFloat(processingInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;

        if (els.processing) {
            els.processing.textContent = formatYen(processing);
        }

        if (!calculable || Number.isNaN(greigeCost)) {
            setUncalculable();
            return;
        }

        const total = greigeCost + processing;
        const profit = price - total;
        const margin = price > 0 ? Math.round((profit / price) * 1000) / 10 : null;

        if (els.total) {
            els.total.textContent = formatYen(total);
        }
        if (els.price) {
            els.price.textContent = formatYen(price);
        }
        if (els.profit) {
            els.profit.textContent = formatYen(profit);
        }
        if (els.margin) {
            if (margin === null) {
                els.margin.innerHTML = '<span class="t-muted">粗利率 —</span>';
            } else {
                els.margin.textContent = '粗利率 ' + margin + ' %';
            }
        }
        setProfitTone(profit);
    }

    window.recipeProfitSyncProduct = function (option) {
        if (!option) {
            return;
        }

        panel.dataset.calculable = option.dataset.calculable || '0';
        panel.dataset.greigeCost = option.dataset.greigeCost || '';

        if (option.dataset.price) {
            priceInput.value = option.dataset.price;
        }

        if (els.greige) {
            const greigeCost = parseFloat(option.dataset.greigeCost);
            if (option.dataset.calculable === '1' && !Number.isNaN(greigeCost)) {
                els.greige.textContent = formatYen(greigeCost);
            } else {
                els.greige.innerHTML = '<span class="t-muted">算出不可</span>';
            }
        }

        recalc();
    };

    processingInput.addEventListener('input', recalc);
    priceInput.addEventListener('input', recalc);
    recalc();
})();

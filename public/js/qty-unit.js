(function () {
    'use strict';

    const TAN_STEP = 0.05;

    function roundTan(tan) {
        const steps = Math.round((parseFloat(tan) || 0) / TAN_STEP);
        return Math.round(steps * TAN_STEP * 100) / 100;
    }

    function formatTanCount(tan) {
        return roundTan(tan).toFixed(2).replace(/\.?0+$/, '') || '0';
    }

    function metersPerTanOf(field) {
        return parseFloat(field.dataset.metersPerTan || '50') || 50;
    }

    function metersToTan(meters, perTan) {
        return perTan > 0 ? roundTan(meters / perTan) : 0;
    }

    function tanToMeters(tan, perTan) {
        return Math.round(roundTan(tan) * perTan);
    }

    function formatQty(meters, perTan) {
        const m = parseInt(meters, 10) || 0;
        const tan = formatTanCount(metersToTan(m, perTan));
        return tan + '反 / ' + m.toLocaleString() + 'm';
    }

    function getMode(field) {
        return field.dataset.qtyMode === 'meter' ? 'meter' : 'tan';
    }

    function setMode(field, mode) {
        field.dataset.qtyMode = mode;
        const tanRow = field.querySelector('[data-qty-tan-row]');
        const meterRow = field.querySelector('[data-qty-meter-row]');
        const switchBtn = field.querySelector('[data-qty-mode-switch]');
        if (tanRow) tanRow.hidden = mode === 'meter';
        if (meterRow) meterRow.hidden = mode !== 'meter';
        if (switchBtn) {
            switchBtn.textContent = mode === 'meter' ? '反数で指定' : 'mで直接指定';
        }
    }

    function readTanHidden(field) {
        return roundTan(parseFloat(field.querySelector('[data-qty-tan-hidden]')?.value || '0') || 0);
    }

    function readMetersHidden(field) {
        const raw = field.querySelector('[data-qty-meters-hidden]')?.value;
        return raw === '' || raw === undefined ? 0 : parseInt(raw, 10) || 0;
    }

    function syncField(field) {
        const tanHidden = field.querySelector('[data-qty-tan-hidden]');
        const metersHidden = field.querySelector('[data-qty-meters-hidden]');
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const mode = getMode(field);
        const tan = readTanHidden(field);
        const metersOverride = readMetersHidden(field);
        const nominalMeters = tan > 0 ? tanToMeters(tan, perTan) : 0;
        const meters = mode === 'meter' && metersOverride > 0 ? metersOverride : nominalMeters;
        const maxTan = field.dataset.maxTan !== undefined && field.dataset.maxTan !== ''
            ? roundTan(parseFloat(field.dataset.maxTan))
            : null;

        if (mode === 'tan') {
            const tanText = tan > 0 ? formatTanCount(tan) : '0';
            if (tanDisplay) {
                tanDisplay.step = String(TAN_STEP);
                tanDisplay.min = '0';
                tanDisplay.value = tanText;
                if (maxTan !== null) {
                    tanDisplay.max = formatTanCount(maxTan);
                } else {
                    tanDisplay.removeAttribute('max');
                }
            }
            if (hint) hint.textContent = '= ' + nominalMeters.toLocaleString() + 'm';
        } else {
            if (meterDisplay) {
                meterDisplay.step = '1';
                meterDisplay.min = '0';
                meterDisplay.value = meters > 0 ? String(meters) : '0';
                if (maxTan !== null) {
                    meterDisplay.max = String(tanToMeters(maxTan, perTan));
                } else {
                    meterDisplay.removeAttribute('max');
                }
            }
            if (hint) {
                hint.textContent = '≈ ' + formatTanCount(metersToTan(meters, perTan)) + '反';
            }
        }

        if (tanHidden && tan > 0) {
            tanHidden.value = formatTanCount(tan);
        }
        if (metersHidden) {
            const overridden = mode === 'meter' && meters > 0 && meters !== nominalMeters;
            metersHidden.value = overridden ? String(meters) : '';
        }
    }

    function updateFieldFromDisplay(field) {
        const tanHidden = field.querySelector('[data-qty-tan-hidden]');
        const metersHidden = field.querySelector('[data-qty-meters-hidden]');
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const mode = getMode(field);
        let tan;
        let meters;

        if (mode === 'tan') {
            tan = roundTan(parseFloat(tanDisplay?.value || '0') || 0);
            if (tanDisplay) tanDisplay.value = formatTanCount(tan);
            meters = tanToMeters(tan, perTan);
            if (hint) hint.textContent = '= ' + meters.toLocaleString() + 'm';
            if (metersHidden) metersHidden.value = '';
        } else {
            meters = Math.round(parseFloat(meterDisplay?.value || '0') || 0);
            tan = meters > 0 ? metersToTan(meters, perTan) : 0;
            if (hint) {
                hint.textContent = '≈ ' + formatTanCount(tan) + '反';
            }
            if (metersHidden) metersHidden.value = meters > 0 ? String(meters) : '';
        }

        if (tanHidden) tanHidden.value = formatTanCount(Math.max(0, tan));

        field.dispatchEvent(new CustomEvent('qty-changed', {
            bubbles: true,
            detail: { tan, meters, mode },
        }));
        field.dispatchEvent(new CustomEvent('qty-meters-changed', {
            bubbles: true,
            detail: { meters: meters, tan: tan },
        }));
    }

    function bindField(field, getPageApi) {
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const switchBtn = field.querySelector('[data-qty-mode-switch]');

        tanDisplay?.addEventListener('input', () => updateFieldFromDisplay(field));
        tanDisplay?.addEventListener('change', () => updateFieldFromDisplay(field));
        meterDisplay?.addEventListener('input', () => updateFieldFromDisplay(field));
        meterDisplay?.addEventListener('change', () => updateFieldFromDisplay(field));

        switchBtn?.addEventListener('click', function () {
            const next = getMode(field) === 'tan' ? 'meter' : 'tan';
            setMode(field, next);
            syncField(field);
            updateFieldFromDisplay(field);
        });
    }

    function initPage(pageKey, options) {
        options = options || {};

        function fields() {
            return document.querySelectorAll('[data-qty-unit-field][data-page-key="' + pageKey + '"]');
        }

        function refresh() {
            fields().forEach(function (field) {
                syncField(field);
            });
        }

        fields().forEach(function (field) {
            setMode(field, 'tan');
            bindField(field, function () { return api; });
        });

        refresh();

        const api = {
            pageKey: pageKey,
            refresh: refresh,
            setMetersPerTan: function (perTan) {
                fields().forEach(function (field) {
                    field.dataset.metersPerTan = String(perTan);
                    syncField(field);
                });
            },
            bindField: function (field) {
                field.dataset.pageKey = pageKey;
                if (!field.dataset.qtyMode) setMode(field, 'tan');
                bindField(field, function () { return api; });
                syncField(field);
            },
            readTan: function (field) {
                updateFieldFromDisplay(field);
                return readTanHidden(field);
            },
            readMeters: function (field) {
                updateFieldFromDisplay(field);
                const perTan = metersPerTanOf(field);
                const tan = readTanHidden(field);
                const metersOverride = readMetersHidden(field);
                if (getMode(field) === 'meter' && metersOverride > 0) {
                    return metersOverride;
                }
                return tanToMeters(tan, perTan);
            },
        };

        if (!window.QtyUnitPages) window.QtyUnitPages = {};
        window.QtyUnitPages[pageKey] = api;

        if (typeof options.onInit === 'function') {
            options.onInit(api);
        }

        return api;
    }

    window.QtyUnit = {
        initPage: initPage,
        formatQty: formatQty,
        formatTanCount: formatTanCount,
        roundTan: roundTan,
        tanToMeters: tanToMeters,
        metersToTan: metersToTan,
        TAN_STEP: TAN_STEP,
    };
})();

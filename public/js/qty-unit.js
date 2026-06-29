(function () {
    'use strict';

    const STORAGE_KEY = 'qty_unit_by_page';

    function readPrefs() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch {
            return {};
        }
    }

    function writePrefs(prefs) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    }

    function getPageUnit(pageKey) {
        return readPrefs()[pageKey] || 'tan';
    }

    function setPageUnit(pageKey, unit) {
        const prefs = readPrefs();
        prefs[pageKey] = unit;
        writePrefs(prefs);
    }

    function formatTanCount(tan) {
        return tan.toFixed(2).replace(/\.?0+$/, '') || '0';
    }

    function metersPerTanOf(field) {
        return parseFloat(field.dataset.metersPerTan || '50') || 50;
    }

    function metersToTan(meters, perTan) {
        return perTan > 0 ? meters / perTan : 0;
    }

    function tanToMeters(tan, perTan) {
        return Math.round(tan * perTan);
    }

    function formatQty(meters, perTan) {
        const m = parseInt(meters, 10) || 0;
        const tan = formatTanCount(metersToTan(m, perTan));
        return tan + '反 / ' + m.toLocaleString() + 'm';
    }

    function syncField(field, unit) {
        const hidden = field.querySelector('[data-qty-meters-hidden]');
        const display = field.querySelector('[data-qty-display]');
        const suffix = field.querySelector('[data-qty-suffix]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const meters = parseInt(hidden?.value || '0', 10) || 0;
        const maxMeters = field.dataset.maxMeters !== undefined && field.dataset.maxMeters !== ''
            ? parseInt(field.dataset.maxMeters, 10)
            : null;

        if (unit === 'tan') {
            if (suffix) suffix.textContent = '反';
            if (display) {
                display.step = '0.01';
                display.min = '0';
                display.value = meters > 0 ? formatTanCount(metersToTan(meters, perTan)) : '0';
                if (maxMeters !== null) {
                    display.max = formatTanCount(metersToTan(maxMeters, perTan));
                } else {
                    display.removeAttribute('max');
                }
            }
            if (hint) hint.textContent = '= ' + meters.toLocaleString() + 'm';
        } else {
            if (suffix) suffix.textContent = 'm';
            if (display) {
                display.step = '1';
                display.min = '0';
                display.value = meters > 0 ? String(meters) : '0';
                if (maxMeters !== null) {
                    display.max = String(maxMeters);
                } else {
                    display.removeAttribute('max');
                }
            }
            if (hint) hint.textContent = '= ' + formatTanCount(metersToTan(meters, perTan)) + '反';
        }
    }

    function updateFieldFromDisplay(field, unit) {
        const hidden = field.querySelector('[data-qty-meters-hidden]');
        const display = field.querySelector('[data-qty-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const raw = parseFloat(display?.value || '0') || 0;
        const meters = unit === 'tan' ? tanToMeters(raw, perTan) : Math.round(raw);

        if (hidden) hidden.value = Math.max(0, meters);
        if (unit === 'tan' && hint) hint.textContent = '= ' + meters.toLocaleString() + 'm';
        if (unit === 'meter' && hint) hint.textContent = '= ' + formatTanCount(metersToTan(meters, perTan)) + '反';

        field.dispatchEvent(new CustomEvent('qty-meters-changed', { bubbles: true, detail: { meters } }));
    }

    function bindField(field, getUnit) {
        const display = field.querySelector('[data-qty-display]');
        display?.addEventListener('input', () => updateFieldFromDisplay(field, getUnit()));
        display?.addEventListener('change', () => updateFieldFromDisplay(field, getUnit()));
    }

    function initPage(pageKey, options) {
        options = options || {};
        let unit = getPageUnit(pageKey);
        const toggle = document.querySelector('[data-qty-unit-toggle][data-page-key="' + pageKey + '"]');

        function fields() {
            return document.querySelectorAll('[data-qty-unit-field][data-page-key="' + pageKey + '"]');
        }

        function applyUnit(nextUnit) {
            unit = nextUnit;
            setPageUnit(pageKey, unit);
            if (toggle) {
                toggle.querySelectorAll('[data-unit]').forEach(function (btn) {
                    btn.classList.toggle('qty-unit-toggle__btn--active', btn.dataset.unit === unit);
                    btn.setAttribute('aria-pressed', btn.dataset.unit === unit ? 'true' : 'false');
                });
            }
            fields().forEach(function (field) {
                syncField(field, unit);
            });
            document.dispatchEvent(new CustomEvent('qty-unit-changed', {
                detail: { pageKey: pageKey, unit: unit },
            }));
        }

        if (toggle) {
            toggle.querySelectorAll('[data-unit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyUnit(btn.dataset.unit);
                });
            });
        }

        fields().forEach(function (field) {
            bindField(field, function () { return unit; });
        });

        applyUnit(unit);

        const api = {
            pageKey: pageKey,
            getUnit: function () { return unit; },
            applyUnit: applyUnit,
            refresh: function () {
                fields().forEach(function (field) {
                    syncField(field, unit);
                });
            },
            setMetersPerTan: function (perTan) {
                fields().forEach(function (field) {
                    field.dataset.metersPerTan = String(perTan);
                    syncField(field, unit);
                });
            },
            bindField: function (field) {
                field.dataset.pageKey = pageKey;
                bindField(field, function () { return unit; });
                syncField(field, unit);
            },
            readMeters: function (field) {
                updateFieldFromDisplay(field, unit);
                return parseInt(field.querySelector('[data-qty-meters-hidden]')?.value || '0', 10) || 0;
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
        getPageUnit: getPageUnit,
        tanToMeters: tanToMeters,
        metersToTan: metersToTan,
    };
})();

/**
 * 入荷画面：反数に応じた反行テーブル（0.25刻み + 実測m）
 */
(function (global) {
    const TAN_OPTIONS = [0.25, 0.5, 0.75, 1.0];

    function roundReceivingTan(value) {
        return Math.round(value * 4) / 4;
    }

    function parseTan(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? roundReceivingTan(n) : 0;
    }

    function buildRow(index, tanQty, actualM) {
        const options = TAN_OPTIONS.map(function (v) {
            const selected = Math.abs(v - tanQty) < 0.01 ? ' selected' : '';
            return '<option value="' + v + '"' + selected + '>' + v + '反</option>';
        }).join('');

        return (
            '<tr data-roll-row>' +
            '<td class="mono">' + (index + 1) + '</td>' +
            '<td><select class="select mono" name="rolls[' + index + '][tan_qty]" data-roll-tan>' + options + '</select></td>' +
            '<td><input class="input mono" type="number" name="rolls[' + index + '][actual_qty_m]" data-roll-m min="0.01" step="0.01" value="' + (actualM > 0 ? actualM : '') + '" placeholder="実測m"></td>' +
            '</tr>'
        );
    }

    function defaultActualM(tanQty, metersPerTan) {
        return Math.round(tanQty * metersPerTan);
    }

    function splitHeaderTan(headerTan) {
        const lines = [];
        let remaining = roundReceivingTan(headerTan);
        while (remaining > 0.001) {
            let chunk = 1.0;
            for (let i = TAN_OPTIONS.length - 1; i >= 0; i--) {
                if (TAN_OPTIONS[i] <= remaining + 0.001) {
                    chunk = TAN_OPTIONS[i];
                    break;
                }
            }
            lines.push(chunk);
            remaining = roundReceivingTan(remaining - chunk);
        }
        return lines;
    }

    function init(options) {
        const qtyField = options.qtyField;
        const tbody = options.tbody;
        const tanSumEl = options.tanSumEl;
        const mSumEl = options.mSumEl;
        const metersPerTan = options.metersPerTan || 50;

        if (!qtyField || !tbody) {
            return;
        }

        function readHeaderTan() {
            const hidden = qtyField.querySelector('[data-qty-tan-hidden]');
            const display = qtyField.querySelector('[data-qty-tan-display]');
            if (display && display.value !== '') {
                return parseTan(display.value);
            }
            if (hidden && hidden.value !== '') {
                return parseTan(hidden.value);
            }
            return 0;
        }

        function rebuild(preserve) {
            const headerTan = readHeaderTan();
            const chunks = splitHeaderTan(headerTan);
            const preserved = preserve ? collectRows() : [];
            tbody.innerHTML = '';

            chunks.forEach(function (chunk, index) {
                const prev = preserved[index];
                const actualM = prev && prev.actualM > 0
                    ? prev.actualM
                    : defaultActualM(chunk, metersPerTan);
                tbody.insertAdjacentHTML('beforeend', buildRow(index, chunk, actualM));
            });

            updateSummary();
        }

        function collectRows() {
            return Array.from(tbody.querySelectorAll('[data-roll-row]')).map(function (row) {
                return {
                    tanQty: parseTan(row.querySelector('[data-roll-tan]')?.value),
                    actualM: parseFloat(row.querySelector('[data-roll-m]')?.value || '0') || 0,
                };
            });
        }

        function updateSummary() {
            const rows = collectRows();
            const tanSum = rows.reduce(function (sum, row) {
                return sum + row.tanQty;
            }, 0);
            const mSum = rows.reduce(function (sum, row) {
                return sum + (row.actualM > 0 ? row.actualM : 0);
            }, 0);

            if (tanSumEl) {
                tanSumEl.textContent = roundReceivingTan(tanSum).toFixed(2);
            }
            if (mSumEl) {
                mSumEl.textContent = mSum > 0 ? mSum.toFixed(1) : '—';
            }
        }

        qtyField.addEventListener('input', function () {
            rebuild(true);
        });
        qtyField.addEventListener('change', function () {
            rebuild(true);
        });

        tbody.addEventListener('input', updateSummary);
        tbody.addEventListener('change', updateSummary);

        rebuild(false);
    }

    global.ReceivingRolls = { init: init };
})(window);

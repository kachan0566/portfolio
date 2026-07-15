/**
 * 入荷登録：発注明細行を複数選択して入荷
 */
(function (global) {
    'use strict';

    const TAN_OPTIONS = [0.25, 0.5, 0.75, 1.0];

    function roundReceivingTan(value) {
        return Math.round(value * 4) / 4;
    }

    function parseTan(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? roundReceivingTan(n) : 0;
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

    function buildRollRow(entryIndex, rollIndex, tanQty, actualM) {
        const options = TAN_OPTIONS.map(function (v) {
            const selected = Math.abs(v - tanQty) < 0.01 ? ' selected' : '';
            return '<option value="' + v + '"' + selected + '>' + v + '反</option>';
        }).join('');

        return (
            '<tr data-roll-row>' +
            '<td class="mono">' + (rollIndex + 1) + '</td>' +
            '<td><select class="select mono" name="entries[' + entryIndex + '][rolls][' + rollIndex + '][tan_qty]" data-roll-tan>' + options + '</select></td>' +
            '<td><input class="input mono" type="number" name="entries[' + entryIndex + '][rolls][' + rollIndex + '][actual_qty_m]" data-roll-m min="0.01" step="0.01" value="' + (actualM > 0 ? actualM : '') + '" placeholder="実測m"></td>' +
            '</tr>'
        );
    }

    function rebuildRollsForEntry(entryEl, preserve) {
        const entryIndex = entryEl.dataset.entryIndex;
        const tanInput = entryEl.querySelector('[data-entry-tan]');
        const tbody = entryEl.querySelector('[data-entry-rolls-body]');
        const tanSumEl = entryEl.querySelector('[data-entry-tan-sum]');
        const mSumEl = entryEl.querySelector('[data-entry-m-sum]');
        const metersPerTan = parseInt(entryEl.dataset.metersPerTan, 10) || 50;

        if (!tanInput || !tbody) return;

        const headerTan = parseTan(tanInput.value);
        const chunks = splitHeaderTan(headerTan);
        const preserved = preserve ? collectRollRows(tbody) : [];
        tbody.innerHTML = '';

        chunks.forEach(function (chunk, rollIndex) {
            const prev = preserved[rollIndex];
            const actualM = prev && prev.actualM > 0 ? prev.actualM : defaultActualM(chunk, metersPerTan);
            tbody.insertAdjacentHTML('beforeend', buildRollRow(entryIndex, rollIndex, chunk, actualM));
        });

        updateRollSummary(tbody, tanSumEl, mSumEl);
    }

    function collectRollRows(tbody) {
        return Array.from(tbody.querySelectorAll('[data-roll-row]')).map(function (row) {
            return {
                tanQty: parseTan(row.querySelector('[data-roll-tan]')?.value),
                actualM: parseFloat(row.querySelector('[data-roll-m]')?.value || '0') || 0,
            };
        });
    }

    function updateRollSummary(tbody, tanSumEl, mSumEl) {
        const rows = collectRollRows(tbody);
        const tanSum = rows.reduce(function (sum, row) { return sum + row.tanQty; }, 0);
        const mSum = rows.reduce(function (sum, row) { return sum + (row.actualM > 0 ? row.actualM : 0); }, 0);
        if (tanSumEl) tanSumEl.textContent = roundReceivingTan(tanSum).toFixed(2);
        if (mSumEl) mSumEl.textContent = mSum > 0 ? mSum.toFixed(1) : '—';
    }

    function buildYarnEntry(index, line) {
        const remLabel = Number(line.remaining).toFixed(2) + ' kg';
        return (
            '<div class="receiving-entry" data-receiving-entry data-entry-index="' + index + '" style="padding:12px;border-top:1px solid var(--border-subtle,#e2e8f0);">' +
            '<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
            '<input type="checkbox" name="entries[' + index + '][selected]" value="1" checked>' +
            '<span><strong class="code-cell">' + line.sku + '</strong>（行' + line.line_no + '）残 ' + remLabel + '</span>' +
            '</label>' +
            '<input type="hidden" name="entries[' + index + '][po_line_id]" value="' + line.id + '">' +
            '<div class="field">' +
            '<label class="label">入荷数量（kg）</label>' +
            '<input class="input mono" type="number" name="entries[' + index + '][qty_kg]" min="0.01" step="0.01" placeholder="' + remLabel + '">' +
            '</div></div>'
        );
    }

    function buildFabricEntry(index, line, type) {
        const remLabel = Math.floor(line.remaining) + ' m';
        const perTan = line.meters_per_tan || 50;
        return (
            '<div class="receiving-entry" data-receiving-entry data-entry-index="' + index + '" data-meters-per-tan="' + perTan + '" style="padding:12px;border-top:1px solid var(--border-subtle,#e2e8f0);">' +
            '<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
            '<input type="checkbox" name="entries[' + index + '][selected]" value="1" checked>' +
            '<span><strong class="code-cell">' + line.sku + '</strong>（行' + line.line_no + '）残 ' + remLabel + '</span>' +
            '</label>' +
            '<input type="hidden" name="entries[' + index + '][po_line_id]" value="' + line.id + '">' +
            '<div class="form-row">' +
            '<div class="field">' +
            '<label class="label">入荷反数</label>' +
            '<input class="input mono" type="number" name="entries[' + index + '][qty_tan]" data-entry-tan min="0.25" step="0.25" value="0.25">' +
            '</div>' +
            '<div class="field">' +
            '<label class="label">入荷m数（参考）</label>' +
            '<input class="input mono" type="number" name="entries[' + index + '][qty_meters]" data-entry-meters min="1" step="1" readonly>' +
            '</div></div>' +
            '<div class="table-wrap" style="margin-top:8px;">' +
            '<table class="data"><thead><tr><th>#</th><th>反数</th><th class="num">実測m</th></tr></thead>' +
            '<tbody data-entry-rolls-body></tbody></table></div>' +
            '<p class="field-hint" style="margin:8px 0 0;">合計: <span data-entry-tan-sum class="mono">0.00</span>反 / 実測 <span data-entry-m-sum class="mono">—</span>m</p>' +
            '</div>'
        );
    }

    function bindFabricEntry(entryEl) {
        const tanInput = entryEl.querySelector('[data-entry-tan]');
        const metersInput = entryEl.querySelector('[data-entry-meters]');
        const perTan = parseInt(entryEl.dataset.metersPerTan, 10) || 50;

        function syncMeters() {
            const tan = parseTan(tanInput?.value || 0);
            if (metersInput) metersInput.value = tan > 0 ? String(defaultActualM(tan, perTan)) : '';
            rebuildRollsForEntry(entryEl, true);
        }

        tanInput?.addEventListener('input', syncMeters);
        tanInput?.addEventListener('change', syncMeters);
        entryEl.querySelector('[data-entry-rolls-body]')?.addEventListener('input', function () {
            updateRollSummary(
                entryEl.querySelector('[data-entry-rolls-body]'),
                entryEl.querySelector('[data-entry-tan-sum]'),
                entryEl.querySelector('[data-entry-m-sum]')
            );
        });
        entryEl.querySelector('[data-entry-rolls-body]')?.addEventListener('change', function () {
            updateRollSummary(
                entryEl.querySelector('[data-entry-rolls-body]'),
                entryEl.querySelector('[data-entry-tan-sum]'),
                entryEl.querySelector('[data-entry-m-sum]')
            );
        });
        syncMeters();
    }

    function renderLines(linesBody, poLines, poId, type) {
        const lines = poLines[poId] || [];
        if (!lines.length) {
            linesBody.innerHTML = '<p class="t-muted" style="padding:12px;margin:0;">入荷可能な明細行がありません。</p>';
            return;
        }

        const html = lines.map(function (line, index) {
            return type === 'yarn' ? buildYarnEntry(index, line) : buildFabricEntry(index, line, type);
        }).join('');

        linesBody.innerHTML = html;
        linesBody.querySelectorAll('[data-receiving-entry]').forEach(function (entryEl) {
            if (type !== 'yarn') bindFabricEntry(entryEl);
        });
    }

    function init(options) {
        const poSelect = options.poSelect;
        const linesBody = options.linesBody;
        const poLines = options.poLines || {};
        const type = options.type || 'product';

        if (!poSelect || !linesBody) return;

        function refresh() {
            renderLines(linesBody, poLines, poSelect.value, type);
        }

        poSelect.addEventListener('change', refresh);
        refresh();
    }

    global.ReceivingMultiLines = { init: init };
})(window);

(function () {
    'use strict';

    const container = document.getElementById('po-lines-container');
    const addBtn = document.getElementById('add-po-line');
    const template = document.getElementById('po-line-template');

    if (!container || !addBtn || !template) {
        return;
    }

    function nextIndex() {
        const rows = container.querySelectorAll('[data-po-line-row]');
        return rows.length;
    }

    function renumberRows() {
        container.querySelectorAll('[data-po-line-row]').forEach(function (row, index) {
            row.dataset.lineIndex = String(index);
            const label = row.querySelector('[data-line-label]');
            if (label) {
                label.textContent = '明細行 ' + (index + 1);
            }

            row.querySelectorAll('[name]').forEach(function (el) {
                const name = el.getAttribute('name');
                if (!name) return;
                el.setAttribute('name', name.replace(/lines\[\d+\]/, 'lines[' + index + ']'));
            });

            const removeBtn = row.querySelector('[data-remove-po-line]');
            if (removeBtn) {
                removeBtn.style.display = index === 0 ? 'none' : '';
            }
        });
    }

    function initRow(row) {
        if (window.PurchaseForm && typeof window.PurchaseForm.initRow === 'function') {
            window.PurchaseForm.initRow(row);
        }
    }

    addBtn.addEventListener('click', function () {
        const index = nextIndex();
        const html = template.innerHTML.replace(/__INDEX__/g, String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        container.appendChild(row);
        renumberRows();
        initRow(row);
    });

    container.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-remove-po-line]');
        if (!btn) return;
        const row = btn.closest('[data-po-line-row]');
        if (!row) return;
        const rows = container.querySelectorAll('[data-po-line-row]');
        if (rows.length <= 1) return;
        row.remove();
        renumberRows();
        if (window.PurchaseForm && typeof window.PurchaseForm.refreshGreigePreview === 'function') {
            window.PurchaseForm.refreshGreigePreview();
        }
    });

    container.querySelectorAll('[data-po-line-row]').forEach(initRow);
    renumberRows();
})();

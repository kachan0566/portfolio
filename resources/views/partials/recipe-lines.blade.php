@php
    $normalizeLine = function ($line) {
        if (is_object($line)) {
            return ['material_id' => $line->material_id, 'qty' => $line->qty];
        }

        return $line;
    };

    $initialLines = collect(old('lines'))->map($normalizeLine)->values();

    if ($initialLines->isEmpty()) {
        $initialLines = collect($lines ?? [])->map($normalizeLine)->values();
    }

    if ($initialLines->isEmpty()) {
        $initialLines = collect([['material_id' => $materials->first()->id ?? '', 'qty' => '']]);
    }
@endphp

<div class="field">
    <label class="label">原材料と使用量<span class="req">*</span></label>
    <div class="table-wrap">
        <table class="data" data-recipe-lines>
            <thead>
                <tr>
                    <th>原材料</th>
                    <th class="num" style="width:200px;">使用量</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($initialLines as $i => $line)
                    <tr>
                        <td>
                            <select class="select" data-field="material_id" name="lines[{{ $i }}][material_id]">
                                @foreach ($materials as $m)
                                    <option value="{{ $m->id }}" @selected((string) $m->id === (string) ($line['material_id'] ?? ''))>
                                        {{ $m->name }}（{{ $m->unit }}）
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="num">
                            <input
                                class="input"
                                type="number"
                                data-field="qty"
                                name="lines[{{ $i }}][qty]"
                                step="0.01"
                                min="0"
                                value="{{ $line['qty'] ?? '' }}"
                                style="max-width:160px;margin-left:auto;"
                            >
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm" data-recipe-line-remove>削除</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" data-recipe-line-add style="margin-top:8px;">
        @include('partials.icon', ['name' => 'plus']) 行を追加
    </button>
    <p class="field-hint">1単位を作るのに必要な量を入力します。染料・仕上げ剤は一覧画面で加工料としてまとめて表示されます。</p>
</div>

<template id="recipe-line-template">
    <tr>
        <td>
            <select class="select" data-field="material_id">
                @foreach ($materials as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}（{{ $m->unit }}）</option>
                @endforeach
            </select>
        </td>
        <td class="num">
            <input
                class="input"
                type="number"
                data-field="qty"
                step="0.01"
                min="0"
                style="max-width:160px;margin-left:auto;"
            >
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" data-recipe-line-remove>削除</button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const table = document.querySelector('[data-recipe-lines]');
    const template = document.getElementById('recipe-line-template');
    const addBtn = document.querySelector('[data-recipe-line-add]');
    if (!table || !template || !addBtn) return;

    const tbody = table.querySelector('tbody');

    function reindex() {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((row, i) => {
            row.querySelector('[data-field="material_id"]').name = 'lines[' + i + '][material_id]';
            row.querySelector('[data-field="qty"]').name = 'lines[' + i + '][qty]';
            const removeBtn = row.querySelector('[data-recipe-line-remove]');
            removeBtn.disabled = rows.length <= 1;
        });
    }

    function bindRemove(row) {
        row.querySelector('[data-recipe-line-remove]').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length <= 1) return;
            row.remove();
            reindex();
        });
    }

    function addRow() {
        const row = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row);
        bindRemove(row);
        reindex();
    }

    tbody.querySelectorAll('tr').forEach(bindRemove);
    addBtn.addEventListener('click', addRow);
    reindex();
})();
</script>
@endpush

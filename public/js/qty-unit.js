/**
 * 反数・メートル数量入力の共通処理（表示欄・hidden・ヒント・換算イベント）。
 *
 * 入力の流れ:
 * 1. ユーザーは data-qty-tan-display（見える欄）に打鍵する
 * 2. 打鍵中は updateHintFromDisplay でヒントだけ更新する（小数の途中入力を壊さない）
 * 3. blur / change で updateFieldFromDisplay が確定（0.25反刻み・hidden・qty-meters-changed）
 * 4. 送信前の readMeters / readTan でも確定処理を走らせる
 */
(function () {
    'use strict';

    /** @type {number} 受注・発注・引当などで共通の反数刻み（0.25反） */
    const DEFAULT_TAN_STEP = 0.25;

    /**
     * その入力欄で使う反数の刻み（step）を返す。
     *
     * data-tan-step が HTML にあればそれを使い、なければ DEFAULT_TAN_STEP を使う。
     *
     * @param {HTMLElement} field data-qty-unit-field の要素
     * @returns {number} 正の刻み値。0 以下の指定は DEFAULT_TAN_STEP に置き換える
     */
    function tanStepOf(field) {
        const step = parseFloat(field.dataset.tanStep || String(DEFAULT_TAN_STEP));
        return step > 0 ? step : DEFAULT_TAN_STEP;
    }

    /**
     * 反数を指定刻み（通常 0.25反）の倍数に丸める。
     *
     * 処理の流れ:
     * 1. 反数を刻みで割り、最も近い整数「段数」に丸める
     * 2. 段数 × 刻み で反数に戻し、小数桁を揃える
     *
     * @param {number|string} tan 丸める前の反数
     * @param {number} [step] 刻み。省略時は DEFAULT_TAN_STEP
     * @returns {number} 刻みに沿った反数
     */
    function roundTan(tan, step) {
        step = step ?? DEFAULT_TAN_STEP;
        const steps = Math.round((parseFloat(tan) || 0) / step);
        const decimals = step >= 1 ? 0 : 2;
        return Math.round(steps * step * Math.pow(10, decimals)) / Math.pow(10, decimals);
    }

    /**
     * 反数を入力欄・hidden に表示する文字列に整える。
     *
     * roundTan で刻みに揃えたあと、不要な末尾の 0 を削る（例: 1.00 → 1）。
     *
     * @param {number|string} tan 表示したい反数
     * @param {number} [step] 刻み。省略時は DEFAULT_TAN_STEP
     * @returns {string} 表示用の反数文字列。空扱いは '0'
     */
    function formatTanCount(tan, step) {
        step = step ?? DEFAULT_TAN_STEP;
        const rounded = roundTan(tan, step);
        const decimals = step >= 1 ? 0 : 2;
        return rounded.toFixed(decimals).replace(/\.?0+$/, '') || '0';
    }

    function metersPerTanOf(field) {
        return parseFloat(field.dataset.metersPerTan || '50') || 50;
    }

    function metersToTan(meters, perTan, step) {
        return perTan > 0 ? roundTan(meters / perTan, step) : 0;
    }

    function roundMeters(m) {
        return Math.round((parseFloat(m) || 0) * 100) / 100;
    }

    function tanToMeters(tan, perTan, step) {
        return roundMeters(roundTan(tan, step) * perTan);
    }

    function formatQty(meters, perTan, step) {
        step = step ?? DEFAULT_TAN_STEP;
        const m = roundMeters(meters);
        const tan = formatTanCount(metersToTan(m, perTan, step), step);
        return tan + '反 / ' + m.toLocaleString(undefined, { maximumFractionDigits: 2 }) + 'm';
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
        const step = tanStepOf(field);
        return roundTan(parseFloat(field.querySelector('[data-qty-tan-hidden]')?.value || '0') || 0, step);
    }

    function readMetersHidden(field) {
        const raw = field.querySelector('[data-qty-meters-hidden]')?.value;
        return raw === '' || raw === undefined ? 0 : roundMeters(raw);
    }

    /**
     * 数量の表示用 input が、今フォーカス（入力中）かどうかを判定する。
     *
     * syncField が refresh で表示を上書きする際、入力途中の欄を守るために使う。
     *
     * @param {HTMLInputElement|null|undefined} displayEl data-qty-tan-display または data-qty-meter-display
     * @returns {boolean} その要素にフォーカスがあるとき true
     */
    function isQtyDisplayFocused(displayEl) {
        return displayEl && document.activeElement === displayEl;
    }

    /**
     * 打鍵中に、換算ヒント（data-qty-hint）だけを更新する。
     *
     * 処理の流れ:
     * 1. 表示欄の文字列を parseFloat する（途中の "0." などはまだ壊さないため表示欄は書き換えない）
     * 2. 反数モードなら「= ○○m」、m モードなら「≈ ○○反」をヒントに出す
     *
     * hidden や qty-meters-changed は更新しない（引当バー等は確定時まで動かない）。
     *
     * @see updateFieldFromDisplay 確定（丸め・hidden・イベント）はこちら
     * @param {HTMLElement} field data-qty-unit-field の要素
     * @returns {void}
     */
    function updateHintFromDisplay(field) {
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const step = tanStepOf(field);
        const mode = getMode(field);

        if (!hint) {
            return;
        }

        if (mode === 'tan') {
            const raw = parseFloat(tanDisplay?.value || '0') || 0;
            const meters = raw > 0 ? roundMeters(raw * perTan) : 0;
            hint.textContent = '= ' + meters.toLocaleString() + 'm';
        } else {
            const meters = roundMeters(parseFloat(meterDisplay?.value || '0') || 0);
            const tan = meters > 0 ? metersToTan(meters, perTan, step) : 0;
            hint.textContent = '≈ ' + formatTanCount(tan, step) + '反';
        }
    }

    /**
     * hidden（送信用）の値から、表示欄・ヒント・上限属性を同期する。
     *
     * 処理の流れ:
     * 1. data-qty-tan-hidden を正として反数を読む
     * 2. 反数モード / m モードに応じて表示欄とヒントをセットする
     * 3. いまその表示欄にフォーカスがある場合は value とヒントを上書きしない（入力途中を守る）
     *
     * 発注選択時の refresh など、画面側の都合で全欄を再描画するときに呼ばれる。
     *
     * @see isQtyDisplayFocused フォーカス判定
     * @param {HTMLElement} field data-qty-unit-field の要素
     * @returns {void}
     */
    function syncField(field) {
        const tanHidden = field.querySelector('[data-qty-tan-hidden]');
        const metersHidden = field.querySelector('[data-qty-meters-hidden]');
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const step = tanStepOf(field);
        const mode = getMode(field);
        const tan = readTanHidden(field);
        const metersOverride = readMetersHidden(field);
        const nominalMeters = tan > 0 ? tanToMeters(tan, perTan, step) : 0;
        const meters = mode === 'meter' && metersOverride > 0 ? metersOverride : nominalMeters;
        const maxTan = field.dataset.maxTan !== undefined && field.dataset.maxTan !== ''
            ? roundTan(parseFloat(field.dataset.maxTan), step)
            : null;

        if (mode === 'tan') {
            const tanText = tan > 0 ? formatTanCount(tan, step) : '0';
            const tanEditing = isQtyDisplayFocused(tanDisplay);
            if (tanDisplay) {
                tanDisplay.step = String(step);
                tanDisplay.min = '0';
                if (!tanEditing) {
                    tanDisplay.value = tanText;
                }
                if (maxTan !== null) {
                    tanDisplay.max = formatTanCount(maxTan, step);
                } else {
                    tanDisplay.removeAttribute('max');
                }
            }
            if (hint && !tanEditing) {
                hint.textContent = '= ' + nominalMeters.toLocaleString() + 'm';
            }
        } else {
            const meterEditing = isQtyDisplayFocused(meterDisplay);
            if (meterDisplay) {
                meterDisplay.step = '1';
                meterDisplay.min = '0';
                if (!meterEditing) {
                    meterDisplay.value = meters > 0 ? String(meters) : '0';
                }
                if (maxTan !== null) {
                    meterDisplay.max = String(tanToMeters(maxTan, perTan, step));
                } else {
                    meterDisplay.removeAttribute('max');
                }
            }
            if (hint && !meterEditing) {
                hint.textContent = '≈ ' + formatTanCount(metersToTan(meters, perTan, step), step) + '反';
            }
        }

        if (tanHidden && tan > 0) {
            tanHidden.value = formatTanCount(tan, step);
        }
        if (metersHidden) {
            const overridden = mode === 'meter' && meters > 0 && meters !== nominalMeters;
            metersHidden.value = overridden ? String(meters) : '';
        }
    }

    /**
     * 表示欄の内容を確定し、hidden・ヒント・換算イベントを更新する。
     *
     * 処理の流れ（反数モード）:
     * 1. 表示欄を読み、roundTan で 0.25 刻みに丸める
     * 2. 表示欄を formatTanCount で書き戻す
     * 3. data-qty-tan-hidden に送信用の反数を入れる
     * 4. qty-changed / qty-meters-changed を発火（引当バー更新など）
     *
     * blur・change・送信前 readMeters で呼ぶ。打鍵の input では呼ばない。
     *
     * @see updateHintFromDisplay 打鍵中のヒントだけの更新
     * @param {HTMLElement} field data-qty-unit-field の要素
     * @returns {void}
     */
    function updateFieldFromDisplay(field) {
        const tanHidden = field.querySelector('[data-qty-tan-hidden]');
        const metersHidden = field.querySelector('[data-qty-meters-hidden]');
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const hint = field.querySelector('[data-qty-hint]');
        const perTan = metersPerTanOf(field);
        const step = tanStepOf(field);
        const mode = getMode(field);
        let tan;
        let meters;

        if (mode === 'tan') {
            tan = roundTan(parseFloat(tanDisplay?.value || '0') || 0, step);
            if (tanDisplay) tanDisplay.value = formatTanCount(tan, step);
            meters = tanToMeters(tan, perTan, step);
            if (hint) hint.textContent = '= ' + meters.toLocaleString() + 'm';
            if (metersHidden) metersHidden.value = '';
        } else {
            meters = roundMeters(parseFloat(meterDisplay?.value || '0') || 0);
            tan = meters > 0 ? metersToTan(meters, perTan, step) : 0;
            if (hint) {
                hint.textContent = '≈ ' + formatTanCount(tan, step) + '反';
            }
            if (metersHidden) metersHidden.value = meters > 0 ? String(meters) : '';
        }

        if (tanHidden) tanHidden.value = formatTanCount(Math.max(0, tan), step);

        field.dispatchEvent(new CustomEvent('qty-changed', {
            bubbles: true,
            detail: { tan, meters, mode },
        }));
        field.dispatchEvent(new CustomEvent('qty-meters-changed', {
            bubbles: true,
            detail: { meters: meters, tan: tan },
        }));
    }

    /**
     * 1つの数量入力欄に、打鍵・確定・モード切替のイベントを結びつける。
     *
     * イベントの役割:
     * - input → updateHintFromDisplay（ヒントのみ）
     * - change / blur → updateFieldFromDisplay（確定）
     * - モード切替ボタン → syncField のあと確定
     *
     * @param {HTMLElement} field data-qty-unit-field の要素
     * @param {function(): object} getPageApi ページ API（initPage が返すオブジェクト）を返す関数
     * @returns {void}
     */
    function bindField(field, getPageApi) {
        const tanDisplay = field.querySelector('[data-qty-tan-display]');
        const meterDisplay = field.querySelector('[data-qty-meter-display]');
        const switchBtn = field.querySelector('[data-qty-mode-switch]');

        tanDisplay?.addEventListener('input', () => updateHintFromDisplay(field));
        tanDisplay?.addEventListener('change', () => updateFieldFromDisplay(field));
        tanDisplay?.addEventListener('blur', () => updateFieldFromDisplay(field));
        meterDisplay?.addEventListener('input', () => updateHintFromDisplay(field));
        meterDisplay?.addEventListener('change', () => updateFieldFromDisplay(field));
        meterDisplay?.addEventListener('blur', () => updateFieldFromDisplay(field));

        switchBtn?.addEventListener('click', function () {
            const next = getMode(field) === 'tan' ? 'meter' : 'tan';
            setMode(field, next);
            syncField(field);
            updateFieldFromDisplay(field);
        });
    }

    /**
     * 同じ画面（pageKey）にある数量入力欄をまとめて初期化する。
     *
     * 処理の流れ:
     * 1. data-page-key が一致する欄を探し、bindField でイベントを付ける
     * 2. refresh（syncField 一括）で初期表示を揃える
     * 3. readMeters / readTan は送信前に updateFieldFromDisplay で確定してから値を返す
     *
     * @param {string} pageKey 例: 'allocation', 'orders-form'
     * @param {{ onInit?: function(object): void }} [options] 初期化直後のコールバック
     * @returns {object} refresh / bindField / readMeters などを持つ API
     */
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
                const step = tanStepOf(field);
                const tan = readTanHidden(field);
                const metersOverride = readMetersHidden(field);
                if (getMode(field) === 'meter' && metersOverride > 0) {
                    return metersOverride;
                }
                return tanToMeters(tan, perTan, step);
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
        TAN_STEP: DEFAULT_TAN_STEP,
    };
})();

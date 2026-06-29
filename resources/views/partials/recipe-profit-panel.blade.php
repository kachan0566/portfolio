@php
    $ym = $ym ?? \App\Support\DemoData::CURRENT_YM;
    $breakdown = $breakdown ?? null;
    $profit = $profit ?? null;
    $priceValue = old('price', $price ?? 0);
    $calculable = $breakdown?->calculable ?? false;
    $greigeCost = $breakdown?->greige_cost;
@endphp

<div class="field">
    <label class="label" for="price">販売価格（円/m）<span class="req">*</span></label>
    <input
        class="input"
        type="number"
        id="price"
        name="price"
        min="0"
        step="1"
        value="{{ $priceValue }}"
        style="max-width:200px;"
    >
    @error('price')<p class="field-error">{{ $message }}</p>@enderror
    <p class="field-hint">顧客への販売単価です。製造コストと合わせて粗利を確認できます。</p>
</div>

@include('partials.cost-warning', [
    'warnings' => $costWarnings ?? [],
    'heading' => '製造コストを算出できないため、粗利を表示できません。',
])

<div
    id="recipe-profit-panel"
    class="card"
    style="margin-top:8px;"
    data-calculable="{{ $calculable ? '1' : '0' }}"
    data-greige-cost="{{ $calculable && $greigeCost !== null ? $greigeCost : '' }}"
>
    <div class="card__head">
        <h2 class="card__title" style="font-size:14px;">コスト・粗利サマリー</h2>
        <span class="badge badge-indigo badge--plain">{{ $ym }} 糸単価ベース</span>
    </div>
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>項目</th><th class="num">金額</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>生機単価</td>
                        <td class="num mono" data-profit-greige>
                            @if ($greigeCost !== null)
                                {{ number_format($greigeCost) }} 円/m
                            @else
                                <span class="t-muted">算出不可</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>染色加工料</td>
                        <td class="num mono" data-profit-processing>
                            @if ($profit !== null)
                                {{ number_format($profit->processing_cost) }} 円/m
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                </tbody>
                <tfoot class="data-foot">
                    <tr>
                        <td>製造コスト合計</td>
                        <td class="num mono" data-profit-total>
                            @if ($profit?->unit_cost !== null)
                                {{ number_format($profit->unit_cost) }} 円/m
                            @else
                                <span class="t-muted">算出不可</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>粗利</td>
                        <td class="num mono {{ ($profit?->profit ?? 0) < 0 ? 'text-danger' : '' }}" data-profit-amount>
                            @if ($profit?->profit !== null)
                                {{ number_format($profit->profit) }} 円/m
                            @else
                                <span class="t-muted">算出不可</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>粗利率</td>
                        <td class="num mono {{ ($profit?->profit ?? 0) < 0 ? 'text-danger' : '' }}" data-profit-margin>
                            @if ($profit?->margin_percent !== null)
                                {{ $profit->margin_percent }} %
                            @else
                                <span class="t-muted">—</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

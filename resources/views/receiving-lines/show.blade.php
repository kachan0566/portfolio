@extends('layouts.app')

@section('title', '入荷明細・反修正')
@section('breadcrumb', '取引 / 入荷処理 / 反明細修正')

@section('content')
    <div class="page-header">
        <div>
            <h1>反明細修正</h1>
            <p class="lead">
                入荷 <span class="code-cell">{{ $receiving->code }}</span>
                ／ 明細行 {{ $line->line_no }}
                ／ <span class="badge badge-indigo badge--plain">{{ $poTypeLabel }}</span>
                {{ $sku }}
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('receiving-lines.amendments', $line->id) }}" class="btn btn-secondary btn-sm">
                変更履歴（{{ $amendmentCount }}）
            </a>
            <a href="{{ route('receivings.index') }}" class="btn btn-secondary">
                @include('partials.icon', ['name' => 'back']) 入荷一覧
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            @include('partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <div class="kpi-grid" style="margin-bottom:16px;">
        <div class="kpi">
            <div class="kpi__label">入荷日</div>
            <div class="kpi__value mono" style="font-size:18px;">{{ $receiving->received_date?->toDateString() }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">発注番号</div>
            <div class="kpi__value code-cell" style="font-size:18px;">
                @if ($po)
                    <a href="{{ route('purchases.show', $po->id) }}" class="link-strong">{{ $po->code }}</a>
                @else
                    —
                @endif
            </div>
        </div>
        <div class="kpi">
            <div class="kpi__label">明細合計（キャッシュ）</div>
            <div class="kpi__value mono" style="font-size:18px;">
                {{ \App\Support\QtyHelper::formatTanCount((float) $line->qty_tan) }}反
                ／ {{ number_format((int) $line->qty_m) }}m
            </div>
            <div class="kpi__sub">反明細から自動再計算されます</div>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">反明細（実測の修正）</h2>
        </div>
        <div class="card__body">
            @if ($rollRows->isEmpty())
                <p class="t-muted" style="margin:0;">反明細がありません。</p>
            @else
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach ($rollRows as $row)
                        @php
                            $roll = $row['roll'];
                            $routeName = $poType === \App\Support\PurchaseOrderType::GREIGE
                                ? 'receiving-lines.update-greige-roll'
                                : 'receiving-lines.update-product-roll';
                        @endphp
                        <div class="card" style="margin:0;background:var(--bg-subtle,#f8fafc);">
                            <div class="card__body">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                                    <div>
                                        <div class="code-cell t-strong" style="font-size:15px;">{{ $roll->code }}</div>
                                        <div class="t-muted" style="font-size:13px;margin-top:4px;">
                                            現在:
                                            {{ \App\Support\QtyHelper::formatTanCount((float) $roll->tan_qty) }}反
                                            ／ {{ number_format((float) $roll->actual_qty_m, 1) }}m
                                        </div>
                                    </div>
                                    @if ($row['editable'])
                                        <span class="badge badge-green badge--plain">修正可</span>
                                    @else
                                        <span class="badge badge-rose badge--plain">{{ $row['block_reason'] }}</span>
                                    @endif
                                </div>

                                @if ($row['editable'])
                                    <form action="{{ route($routeName, [$line->id, $roll->id]) }}" method="POST" style="margin-top:12px;">
                                        @csrf
                                        @method('PUT')
                                        <div class="form-row">
                                            <div class="field">
                                                <label class="label">反数</label>
                                                <input class="input mono" type="number" name="tan_qty"
                                                       value="{{ old('tan_qty', (float) $roll->tan_qty) }}"
                                                       step="{{ \App\Support\QtyHelper::RECEIVING_TAN_STEP }}" min="0.25" required>
                                            </div>
                                            <div class="field">
                                                <label class="label">実測m</label>
                                                <input class="input mono" type="number" name="actual_qty_m"
                                                       value="{{ old('actual_qty_m', (float) $roll->actual_qty_m) }}"
                                                       step="0.01" min="0.01" required>
                                            </div>
                                            <div class="field" style="flex:2;">
                                                <label class="label">修正理由（任意）</label>
                                                <input class="input" type="text" name="reason" maxlength="500" value="{{ old('reason') }}">
                                            </div>
                                        </div>
                                        <div class="actions" style="margin-top:8px;">
                                            <button type="submit" class="btn btn-primary btn-sm">この反を保存</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <p class="field-hint" style="margin-top:12px;">
        修正内容は変更履歴に記録されます。出荷済み・消費済みの反は修正できません。
    </p>
@endsection

@extends('layouts.app')

@section('title', '入荷登録')
@section('breadcrumb', '取引 / 入荷処理 / 登録')

@section('content')
    @php
        use App\Support\PurchaseOrderType;
        $typeLabel = PurchaseOrderType::label($type);
        $qtyUnit = $type === PurchaseOrderType::YARN ? 'kg' : 'm';
        $qtyStep = $type === PurchaseOrderType::YARN ? '0.01' : '1';
    @endphp
    <div class="page-header">
        <div>
            <h1>入荷登録</h1>
            <p class="lead">{{ $typeLabel }}の入荷を登録します。種別ごとに単位が異なります（糸＝kg、生機・製品＝m）。</p>
        </div>
        <a href="{{ route('receivings.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 一覧に戻る
        </a>
    </div>

    <div class="tabs" style="margin-bottom:16px;display:flex;gap:8px;">
        @foreach (PurchaseOrderType::all() as $t)
            <a href="{{ route('receivings.create', ['type' => $t]) }}"
               class="btn {{ $type === $t ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ PurchaseOrderType::label($t) }}</a>
        @endforeach
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">入荷待ちの{{ $typeLabel }}</h2>
            </div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>発注番号</th><th>仕入先</th><th>品番</th><th class="num">残数</th><th>状態</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($pending as $po)
                                @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                <tr>
                                    <td class="code-cell">{{ $po->code }}</td>
                                    <td>{{ $po->supplier }}</td>
                                    <td class="code-cell t-strong">{{ $po->sku }}</td>
                                    <td class="num mono">
                                        @if ($type === PurchaseOrderType::YARN)
                                            {{ number_format($rem, 2) }} kg
                                        @else
                                            @include('partials.purchase-qty', ['purchase' => (object) ['type' => $type, 'qty_meters' => $rem, 'greige_sku' => $po->greige_sku ?? null, 'product_id' => $po->product_id ?? null]])
                                        @endif
                                    </td>
                                    <td><span class="badge badge-indigo badge--plain">{{ $po->status_label ?? $po->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty">入荷待ちの発注はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card form-card" style="max-width:none;">
            <div class="card__head"><h2 class="card__title">入荷内容</h2></div>
            <div class="card__body">
                @if ($pending->isEmpty())
                    <p class="t-muted" style="margin:0;">入荷対象の発注がありません。</p>
                @else
                    <form action="{{ route('receivings.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div class="field">
                            <label class="label" for="po">対象発注<span class="req">*</span></label>
                            <select class="select" id="po" name="po_id">
                                @foreach ($pending as $po)
                                    @php $rem = \App\Support\DemoState::poRemaining($po->id); @endphp
                                    <option value="{{ $po->id }}">
                                        {{ $po->code }} ／ {{ $po->sku }}
                                        （残 {{ $type === PurchaseOrderType::YARN ? number_format($rem, 2).' kg' : number_format($rem).' m' }}）
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label class="label" for="qty">入荷数量（{{ $qtyUnit }}）<span class="req">*</span></label>
                                <input class="input" type="number" id="qty" name="qty" min="{{ $type === PurchaseOrderType::YARN ? '0.01' : '1' }}" step="{{ $qtyStep }}" placeholder="{{ $type === PurchaseOrderType::YARN ? '100.5' : '100' }}">
                            </div>
                            <div class="field">
                                <label class="label" for="date">入荷日<span class="req">*</span></label>
                                <input class="input" type="date" id="date" name="date" value="2026-06-15">
                            </div>
                        </div>
                        <p class="field-hint">
                            @if ($type === PurchaseOrderType::YARN)
                                登録すると糸在庫（kg）が増加します。
                            @elseif ($type === PurchaseOrderType::GREIGE)
                                登録すると染工場の生機在庫（m）が増加します。
                            @else
                                登録すると製品在庫（m）が増加します。発注引当がある場合は自動で現在庫引当に変換されます。
                            @endif
                        </p>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 入荷を登録</button>
                            <a href="{{ route('receivings.index') }}" class="btn btn-secondary">キャンセル</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

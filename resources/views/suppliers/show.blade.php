@extends('layouts.app')

@section('title', '仕入先詳細')
@section('breadcrumb', 'マスタ管理 / 仕入先 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $supplier->name }}</h1>
            <p class="lead">この仕入先への発注履歴を確認します。</p>
        </div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 仕入先一覧に戻る
        </a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__body">
            <div class="stat-row">
                <div class="stat-row__item">
                    <div class="stat-row__label">担当者</div>
                    <div class="stat-row__value" style="font-size:15px;">{{ $supplier->contact }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">電話番号</div>
                    <div class="stat-row__value mono" style="font-size:15px;">{{ $supplier->tel }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">発注件数</div>
                    <div class="stat-row__value">{{ $purchases->count() }} 件</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">発注履歴（{{ $purchases->count() }} 件）</h2></div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '発注番号', 'placeholder' => 'PO-2606-001'],
                'customer' => ['label' => '得意先'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '入荷予定'],
                'status' => [
                    'label' => '進捗段階',
                    'options' => collect(\App\Support\DemoData::PO_STAGES)->mapWithKeys(fn ($s) => [$s => $s])->all(),
                ],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>発注番号</th><th>得意先</th><th>品番</th>
                            <th class="num">数量</th><th>進捗段階</th><th>入荷予定</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchases as $po)
                            <tr>
                                <td class="code-cell">{{ $po->code }}</td>
                                <td>{{ $po->customer }}</td>
                                <td class="code-cell t-strong">{{ $po->sku }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $po->qty, 'productId' => $po->product_id])</td>
                                <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
                                <td class="mono">{{ $po->eta }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">発注履歴はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

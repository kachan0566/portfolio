@extends('layouts.app')

@section('title', '得意先詳細')
@section('breadcrumb', 'マスタ管理 / 得意先 / 詳細')

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $customer->name }}</h1>
            <p class="lead">この得意先の受注履歴を確認します。</p>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 得意先一覧に戻る
        </a>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card__body">
            <div class="stat-row">
                <div class="stat-row__item">
                    <div class="stat-row__label">担当者</div>
                    <div class="stat-row__value" style="font-size:15px;">{{ $customer->contact }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">電話番号</div>
                    <div class="stat-row__value mono" style="font-size:15px;">{{ $customer->tel }}</div>
                </div>
                <div class="stat-row__item">
                    <div class="stat-row__label">受注件数</div>
                    <div class="stat-row__value">{{ $orders->count() }} 件</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">受注履歴（{{ $orders->count() }} 件）</h2></div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'code' => ['label' => '受注番号', 'placeholder' => 'SO-2606-001'],
                'sku' => ['label' => '品番'],
                'due' => ['label' => '納期'],
                'status' => [
                    'label' => 'ステータス',
                    'options' => [
                        '未出荷' => '未出荷',
                        '一部出荷' => '一部出荷',
                        '出荷済み' => '出荷済み',
                    ],
                ],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>受注番号</th><th>品番</th><th>カラー</th>
                            <th class="num">数量</th><th>納期</th><th>ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $o)
                            <tr>
                                <td class="code-cell">{{ $o->code }}</td>
                                <td class="code-cell t-strong">{{ $o->sku }}</td>
                                <td>{{ $o->color }}</td>
                                <td class="num mono">@include('partials.qty', ['qty' => $o->qty, 'productId' => $o->product_id])</td>
                                <td class="mono">{{ $o->due_date }}</td>
                                <td>@include('partials.status', ['status' => $o->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">受注履歴はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

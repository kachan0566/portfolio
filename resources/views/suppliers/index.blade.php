@extends('layouts.app')

@section('title', '仕入先')
@section('breadcrumb', 'マスタ管理 / 仕入先')

@section('content')
    <div class="page-header">
        <div>
            <h1>仕入先</h1>
            <p class="lead">発注先（仕入先＝依頼先）の企業を管理します。種別により、発注種別ごとに選択候補が制限されます。</p>
        </div>
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 仕入先を登録
        </a>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">仕入先一覧（{{ $suppliers->count() }} 件）</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'supplier' => ['label' => '仕入先名', 'placeholder' => '仕入先名'],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>仕入先名</th>
                            <th>種別</th>
                            <th>担当者</th>
                            <th>電話番号</th>
                            <th class="num">発注件数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $s)
                            <tr>
                                <td class="t-strong"><a href="{{ route('suppliers.show', $s->id) }}" class="link-strong">{{ $s->name }}</a></td>
                                <td><span class="badge badge-indigo badge--plain">{{ \App\Support\SupplierType::label($s->type) }}</span></td>
                                <td>{{ $s->contact }}</td>
                                <td class="mono t-muted">{{ $s->tel }}</td>
                                <td class="num mono">{{ $purchases->where('supplier', $s->name)->count() }} 件</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

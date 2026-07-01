@extends('layouts.app')

@section('title', '得意先')
@section('breadcrumb', 'マスタ管理 / 得意先')

@section('content')
    <div class="page-header">
        <div>
            <h1>得意先</h1>
            <p class="lead">受注先（販売先）の企業を管理します。</p>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 得意先を登録
        </a>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">得意先一覧（{{ $customers->count() }} 件）</h2>
        </div>
        @include('partials.list-search', [
            'params' => $search,
            'fields' => [
                'customer' => ['label' => '得意先名', 'placeholder' => '得意先名'],
            ],
        ])
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>得意先名</th><th>担当者</th><th>電話番号</th><th class="num">受注件数</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $c)
                            <tr>
                                <td class="t-strong"><a href="{{ route('customers.show', $c->id) }}" class="link-strong">{{ $c->name }}</a></td>
                                <td>{{ $c->contact }}</td>
                                <td class="mono t-muted">{{ $c->tel }}</td>
                                <td class="num mono">{{ $orders->where('customer', $c->name)->count() }} 件</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

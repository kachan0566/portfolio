@extends('layouts.app')

@section('title', '商品一覧')

@section('content')
    <h1>商品一覧</h1>

    @if ($products->isEmpty())
        <p class="empty">商品が登録されていません。</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>商品名</th>
                    <th>品番</th>
                    <th>販売価格</th>
                    <th>カテゴリ</th>
                    <th>単位</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td>{{ $product->id }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>{{ number_format($product->price) }}円</td>
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

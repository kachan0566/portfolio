@extends('layouts.app')

@section('title', '商品一覧')

@section('content')
    <div class="page-header">
        <h1>商品一覧</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary">商品を登録</a>
    </div>

    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

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
                    <th>操作</th>
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
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm">編集</a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline-form" onsubmit="return confirm('この商品を削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

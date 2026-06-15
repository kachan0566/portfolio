@extends('layouts.app')

@section('title', '原材料一覧')

@section('content')
    <div class="page-header">
        <h1>原材料一覧</h1>
        <a href="{{ route('materials.create') }}" class="btn btn-primary">原材料を登録</a>
    </div>

    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    @if ($materials->isEmpty())
        <p class="empty">原材料が登録されていません。</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>原材料名</th>
                    <th>単位</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($materials as $material)
                    <tr>
                        <td>{{ $material->id }}</td>
                        <td>{{ $material->name }}</td>
                        <td>{{ $material->unit }}</td>
                        <td>
                            <div class="table-actions">
                                <a href="{{ route('materials.edit', $material) }}" class="btn btn-secondary btn-sm">編集</a>
                                <form action="{{ route('materials.destroy', $material) }}" method="POST" class="inline-form" onsubmit="return confirm('この原材料を削除しますか？')">
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

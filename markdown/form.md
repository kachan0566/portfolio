## 3. ルート定義 — `routes/web.php`

```
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
```

/products/{product}/edit' コントローラーにある$product変数に渡されるidが{product}に入る

↓

## 4. コントローラ — `ProductController.php`

```
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse; //処理後、別のページへ飛ばす（リダイレクト）
use Illuminate\Http\Request; //ブラウザから送られてきた情報をまとめて使う

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::orderBy('id')->get();

        return view('products.index', compact('products'));
    }

    public function create(): View
{
    return view('products.create');
}

public function store(Request $request): RedirectResponse　
{
    $validated = $request->validate([ 
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
        'price' => ['required', 'integer', 'min:0'],
        'category' => ['required', 'string', 'max:255'],
        'unit' => ['required', 'string', 'max:255'],
    ]);

    Product::create($validated);

    return redirect()
        ->route('products.index')
        ->with('success', '商品を登録しました。');
}

public function edit(Product $product): View
{
    return view('products.edit', compact('product'));
}

public function update(Request $request, Product $product): RedirectResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['required', 'string', 'max:255', 'unique:products,sku,' . $product->id],
        'price' => ['required', 'integer', 'min:0'],
        'category' => ['required', 'string', 'max:255'],
        'unit' => ['required', 'string', 'max:255'],
    ]);

    $product->update($validated);

    return redirect()
        ->route('products.index')
        ->with('success', '商品を更新しました。');
}

public function destroy(Product $product): RedirectResponse
{
    $product->delete();

    return redirect()
        ->route('products.index')
        ->with('success', '商品を削除しました。');
}
}
```

用語
Request $request　Illuminate\Http\Request;の情報を入れる変数$request　Request内容を変数で使えるようにした形のインスタンス
validated　入力チェック（storeproductrequest.phpのrules）を通ったデータだけを取り出す
required　入力されていることを確認
$product　Laravelが自動で$product = Product::findOrFail($id);を実行し、idが一致するものを取得してくる


・StoreProductRequest/UpdateProductRequest

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool　//実行者に権限があるか、true or faleseの型で
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array　//valudatedのルール定義
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'price' => ['required', 'integer', 'min:0'],
            'category' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array　//データ内容を日本語に
    {
        return [
            'name' => '商品名',
            'sku' => '品番',
            'price' => '販売価格',
            'category' => 'カテゴリ',
            'unit' => '単位',
        ];
    }
}


index.blade.php
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

・edit.blade.php

@extends('layouts.app')

@section('title', '商品編集')

@section('content')
    <div class="page-header">
        <h1>商品編集</h1>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <form action="{{ route('products.update', $product) }}" method="POST" class="form-card">
        @csrf
        @method('PUT')

        <div class="field">
            <label for="name" class="label">商品名</label>
            <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" class="input">
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="sku" class="label">品番</label>
            <input type="text" name="sku" id="sku" value="{{ old('sku', $product->sku) }}" class="input">
            @error('sku')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="price" class="label">販売価格</label>
            <input type="number" name="price" id="price" value="{{ old('price', $product->price) }}" min="0" class="input">
            @error('price')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="category" class="label">カテゴリ</label>
            <input type="text" name="category" id="category" value="{{ old('category', $product->category) }}" class="input">
            @error('category')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="unit" class="label">単位</label>
            <input type="text" name="unit" id="unit" value="{{ old('unit', $product->unit) }}" class="input">
            @error('unit')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">更新する</button>
            <a href="{{ route('products.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
@endsection

・create.blade.php

@extends('layouts.app')

@section('title', '商品登録')

@section('content')
    <div class="page-header">
        <h1>商品登録</h1>
        <a href="{{ route('products.index') }}" class="btn btn-secondary">一覧に戻る</a>
    </div>

    <form action="{{ route('products.store') }}" method="POST" class="form-card">
        @csrf

        <div class="field">
            <label for="name" class="label">商品名</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="input">
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="sku" class="label">品番</label>
            <input type="text" name="sku" id="sku" value="{{ old('sku') }}" class="input">
            @error('sku')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="price" class="label">販売価格</label>
            <input type="number" name="price" id="price" value="{{ old('price') }}" min="0" class="input">
            @error('price')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="category" class="label">カテゴリ</label>
            <input type="text" name="category" id="category" value="{{ old('category') }}" class="input">
            @error('category')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="unit" class="label">単位</label>
            <input type="text" name="unit" id="unit" value="{{ old('unit') }}" class="input">
            @error('unit')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">登録する</button>
            <a href="{{ route('products.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
@endsection


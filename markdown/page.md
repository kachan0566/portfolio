# Laravel 商品一覧ページの処理の流れ

ブラウザで `http://127.0.0.1:8000/products` にアクセスしたとき、サーバー側でどのファイルがどの順番で動くかをまとめたメモです。

---

## 1. ブラウザ

- URL を開く: `http://127.0.0.1:8000/products`

↓

## 2. サーバー — `public/index.php`

```php
<?php

use Illuminate\Foundation\Application; // アプリ使用の土台
use Illuminate\Http\Request;         // リクエスト情報をここに保存してる？

define('LARAVEL_START', microtime(true)); // laravelが動き始めた時間（数値）を定数として残す

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php'; // autoload.php（必要なファイルを自動で読み込む処理を書いたファイル）を実行

$app = require_once __DIR__.'/../bootstrap/app.php';
// $app = Applicationクラスに書いてあるlaravelの中心機能を使い、独自の機能を作る bootstrap/app.php を実行する と定義する
// web: __DIR__.'/../routes/web.php' により、web用ルートの定義場所を指定

$app->handleRequest(Request::capture()); // $appで定義した独自本体機能の中で、ブラウザから受け取ったリクエストへの処理を実行
```

### 用語

| 用語 | 意味 |
|------|------|
| `define` | 登録する定数名、登録する内容 |
| `microtime` | 少数で今の時刻を取得 |
| `..` | ディレクトリ内で使用、一つ上の親ディレクトリに移動 |
| `if()` | true のときに `require` ~ を返す |
| `require 'ファイルのパス'` | ~ のファイルを読み込んでそのファイルのコードを実行する |
| `handleRequest()` | リクエストへの処理を実行 |
| `Request` | ブラウザからのリクエスト情報クラス |
| `capture()` | 捕える、集める。今回は Request クラスにあるリクエストを集める |

### `bootstrap/app.php` 内

| 記法 | 意味 |
|------|------|
| `A::B` | A クラスの B メソッドを読んで実行する |
| `configure()` | `()` のディレクトリを基準として設定（インスタンス）を作成する |

↓

## 3. ルート定義 — `routes/web.php`

```php
<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Facades\Route で読み込んだ Route クラスの GET メソッドを使用
    // "/" のページの GET リクエストが来たら
    return view('welcome'); // 画面表示として welcome.blade.php を返す
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
// Route の get メソッド使用、'/products' パスでアクセスが来たら
// ProductController クラスの index メソッドを実行（この処理を products.index とする）
```

### 用語

| 用語 | 意味 |
|------|------|
| `/` | URL のパス。サイトのトップ（例: `http://localhost:8000/`） |
| `function() {}` | `{}` 内に直接処理を返す。無名関数（その場だけ使う処理） |
| `メソッド(パス, 処理)` | パスへアクセスが来たら処理を実行 |
| `[クラス, メソッド]` | クラスのメソッドを実行 |

↓

## 4. コントローラ — `ProductController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller // Controller クラスを親クラスとして ProductController クラスを作成
{
    public function index(): View // index メソッドを定義、戻り値は画面
    {
        $products = Product::orderBy('id')->get();
        // $products 変数 = Product クラスを id 順に並べ取得する と定義

        return view('products.index', compact('products'));
        // products/index.blade.php 画面を返す
        // ($products を画面 index.blade.php でも $products として使えるようにする)
    }
}
```

### 用語

| 用語 | 意味 |
|------|------|
| `public` | 外から（ルートで）呼べる |
| `compact()` | 変数を画面でも使えるようにする（PHP の変数を「連想配列（キーと値のセット）」に変換する） |

↓

## 5. モデル — `Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sku', 'price', 'category', 'unit'])]
// そのままの内容で一括で DB へ保存して良い項目を定義

class Product extends Model
// 自動で products テーブルと接続
// (class Products と定義する ←→ DB に products という名前でテーブルを作る)
{
    protected function casts(): array // 変換して配列で返すメソッドを定義
    {
        return [
            'price' => 'integer', // price のみ整数にする
        ];
    }
}
```

### 用語

| 用語 | 意味 |
|------|------|
| `Fillable[]` | `[]` の中身は一括で代入を許可する |
| `casts()` | 変換する |

↓

## 6. マイグレーション — `2026_06_09_050217_create_products_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration // Migration クラスを親として new class を作成し返す
{
    public function up(): void // データを追加、返り値は無し
    {
        Schema::create('products', function (Blueprint $table) {
            // products テーブルを作成、テーブルの中身を $table の内容で定義する
            $table->id();                        // id カラムを定義
            $table->string('name');              // name（商品名）カラムを短い文字列で
            $table->string('sku')->unique();     // sku（商品コード）カラムを重複禁止、短い文字列で
            $table->unsignedInteger('price');    // price（価格）カラムを 0 以上の整数で
            $table->string('category');            // category カラムを短い文字列で
            $table->string('unit');              // unit（単位）カラムを短い文字列で
            $table->timestamps();                // created_at, updated_at を入力
        });
    }

    public function down(): void // データの削除、返り値は無し
    {
        Schema::dropIfExists('products'); // products というテーブルを消す
    }
};
```

### 用語

| 用語 | 意味 |
|------|------|
| `Schema` | データベースの設計図を PHP で操作する |
| `Schema::create(a, b)` | a というテーブルを作る |
| `Schema::dropIfExists(a)` | a というテーブルを消す |
| `Blueprint` | テーブルの中身を ~ の内容で定義 |
| `unique()` | 同じコードは 2 つ登録できない |

↓

## 7. 画面表示の流れ（再確認）

```
Product.php
    ↓
ProductController.php
    ↓
products.index（products/index.blade.php）
```

### ビュー — `products/index.blade.php`

```blade
@extends('layouts.app') {{-- layouts.app（layouts/app.blade.php）を雛形とする --}}

@section('title', '商品一覧') {{-- タイトル --}}

@section('content') {{-- 内容本文開始 --}}
    <h1>商品一覧</h1>

    @if ($products->isEmpty()) {{-- $products（products テーブル）が空の場合 --}}
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
                        <td>{{ number_format($product->price) }}円</td> {{-- カンマ付きで表示 --}}
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
```

### 用語

| 用語 | 意味 |
|------|------|
| `@foreach ($配列 as $変数)` | 配列を一つずつ取り出して変数へ入れる |
| `{{ }}` | PHP の値を HTML で出力する |

**出力例:**

```html
<td>{{ $product->id }}</td>   → <td>1</td>
<td>{{ $product->name }}</td> → <td>りんご</td>
```

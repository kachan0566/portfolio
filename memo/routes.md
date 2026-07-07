この1行は、**「売上・粗利の詳細」ボタンを押したら、売上一覧ページ（**`/sales`**）へ飛ばすリンク** です。

## 分解して読む

```12:14:resources/views/dashboard.blade.php
        <a href="{{ route('sales.index') }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'chart']) 売上・粗利の詳細
        </a>
```


| 部分                          | 意味                                |
| --------------------------- | --------------------------------- |
| `<a href="...">`            | リンク（クリックで別ページへ移動）                 |
| `{{ ... }}`                 | Blade の「ここに PHP の結果を埋め込む」記法       |
| `route('sales.index')`      | 名前付きルート `sales.index` の URL を自動生成 |
| `class="btn btn-secondary"` | ボタン風の見た目用 CSS                     |


ブラウザに渡る HTML はだいたいこうなります。

```html
<a href="http://あなたのサイト/sales" class="btn btn-secondary">…</a>
```



## `route()` は何をしている？

`route('sales.index')` は **「**`sales.index` **という名前のルートの URL を教えて」** という Laravel のヘルパー関数です。

実際のルート定義は **Blade ファイルではなく** `routes/web.php` にあります。

```81:81:routes/web.php
Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
```

これを日本語にすると：

- **URL** … `/sales` にアクセスしたら
- **処理** … `SalesController` の `index` メソッドを実行
- **名前** … そのルートを `sales.index` と呼ぶ（`->name('sales.index')`）

Blade 側は名前 `sales.index` だけ知っていれば、URL `/sales` を直接書かなくてもリンクが作れます。

## `routes` を使わずにルート定義できる？

**結論：Blade ファイルの中でルート定義はできません。** ルートは必ずどこか（通常は `routes/web.php`）で登録する必要があります。

ただし、`route()` **を使わない書き方** はできます。

### 方法1: URL を直接書く

```blade
<a href="/sales">売上・粗利の詳細</a>
```

動きますが、URL を `/sales-summary` などに変えたとき、`/sales` **と書いた箇所を全部直す**必要があります。

### 方法2: `route()` を使う（このプロジェクトのやり方）

```blade
<a href="{{ route('sales.index') }}">…</a>
```

URL が変わっても `routes/web.php` の1行を直せば、`route('sales.index')` **を使っているリンクは全部自動で追従**します。サイドバーも同じ名前を使っています。

```28:28:resources/views/layouts/app.blade.php
            ['route' => 'sales.index',      'label' => '売上・粗利',   'icon' => 'chart'],
```



## 例えで理解する

- `routes/web.php` … 住所録（「売上一覧 = `/sales`」と登録）
- `route('sales.index')` … 住所録を見て URL を調べる
- **Blade の** `<a href="...">` … その URL への道しるべを貼る

住所録（ルート定義）がないのに `route('sales.index')` だけ書いても、Laravel は「そんな名前のルートはない」とエラーになります。

---

**まとめ：** 12行目は「`sales.index` という名前のページへのリンクを作る」コードです。ルート自体は `routes/web.php` で定義し、Blade では `route()` でその URL を取り出して使う、という役割分担になっています。

# orders画面の流れ



## 結論から

12行目の `route('orders.create')` は、Laravel が `orders.create` **という名前で登録した URL**（`/orders/create`）を自動で作ってくれる書き方です。

「受注を登録」ボタンは **登録フォーム画面** に飛ばしたいので、`orders.create` という名前を使うのが正しい、という意味です。

---



## 3つの仕組みがつながっている



### 1. `web.php` でルートが登録されている

```49:49:routes/web.php
Route::resource('orders', OrderController::class);
```

```
// 一覧
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

// 新規作成フォーム
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');

// 新規登録
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

// 詳細
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

// 編集フォーム
Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');

// 更新（PUT と PATCH の両方）
Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');

// 削除
Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
```

`Route::resource` は、受注管理に必要な URL を **まとめて7本** 作ります。そのとき **それぞれに名前（ルート名）** も自動で付きます。


| ルート名             | URL                 | HTTP      | コントローラー     | 画面の意味      |
| ---------------- | ------------------- | --------- | ----------- | ---------- |
| `orders.index`   | `/orders`           | GET       | `index()`   | 一覧（今いるページ） |
| `orders.create`  | `/orders/create`    | **GET**   | `create()`  | **登録フォーム** |
| `orders.store`   | `/orders`           | POST      | `store()`   | 登録送信       |
| `orders.show`    | `/orders/{id}`      | GET       | `show()`    | 詳細         |
| `orders.edit`    | `/orders/{id}/edit` | GET       | `edit()`    | 編集フォーム     |
| `orders.update`  | `/orders/{id}`      | PUT/PATCH | `update()`  | 更新送信       |
| `orders.destroy` | `/orders/{id}`      | DELETE    | `destroy()` | 削除         |


12行目のボタンは「新規登録フォームを見せて」なので、**GET の** `orders.create` が対応します。

---



### 2. `route('orders.create')` が URL を作る

Blade のこの部分:

```12:14:resources/views/orders/index.blade.php
        <a href="{{ route('orders.create') }}" class="btn btn-primary">
            @include('partials.icon', ['name' => 'plus']) 受注を登録
        </a>
```

`route('名前')` は Laravel の **ヘルパー関数**（便利な組み込み関数）です。

- 入力: ルート名 `'orders.create'`
- 出力: 実際の URL 文字列 `/orders/create`

HTML としてはこうなります:

```html
<a href="/orders/create" class="btn btn-primary">...</a>
```

---



### 3. クリックすると `create()` が動く

`/orders/create` にアクセスすると:

```
GET /orders/create
  → OrderController@create
  → view('orders.create', ...)
  → orders/create.blade.php が表示される
```

つまり「受注を登録」ボタン → 登録フォーム、という流れがコード上も一致しています。

---



## なぜ `/orders/create` と直書きしないのか

次の2つを書いているのは同じ結果になります:

```blade
{{-- 方法A: ルート名を使う（このプロジェクトの書き方） --}}
<a href="{{ route('orders.create') }}">

{{-- 方法B: URLを直書き --}}
<a href="/orders/create">
```

見た目は同じですが、**方法Aの方が安全で保守しやすい**です。

- URL の prefix（先頭）を変えても、`route()` なら自動で追従する
- 「このリンクは登録画面」だと **名前で意図が分かる**
- タイポで存在しないルート名を書くとエラーになり、気づきやすい

---



## 「正しい」とは何が正しいのか

ここでの「正しい」は3点です。

1. **名前が存在する** … `Route::resource('orders', ...)` が `orders.create` を作っている
2. **HTTP メソッドが合う** … リンク（`<a>`）は GET。`orders.create` も GET
3. **画面の目的が合う** … ボタンは「登録フォームへ」。`create()` は登録フォームを返す

逆に、例えば `route('orders.store')` を `<a href>` に使うのは **間違い** です。`store` は POST 専用（フォーム送信）だからです。

---



## 覚え方（例え）

ルート名は **住所の「呼び名」** だと思うと分かりやすいです。


| 呼び名（ルート名）       | 実際の住所（URL）       | 用途       |
| --------------- | ---------------- | -------- |
| `orders.index`  | `/orders`        | 一覧       |
| `orders.create` | `/orders/create` | 新規登録フォーム |
| `orders.show`   | `/orders/5`      | 5番の詳細    |


`route('orders.create')` は「`orders.create` という呼び名の住所を教えて」と Laravel に頼んでいる、というイメージです。

---



## 自分で確認する方法

ターミナルで次を実行すると、名前と URL の対応が一覧できます:

```bash
php artisan route:list --name=orders
```

出力に `orders.create` と `GET|HEAD orders/create` が並んでいれば、12行目のリンクはその定義どおりに動いています。

---

同じページ内では、67行目の `route('orders.show', $o->id)` や111行目の `route('orders.edit', $o->id)` も同じ仕組みです。2番目の引数 `{id}` を渡すと `/orders/5` のように **個別の受注** への URL になります。

## .create画面（登録画面への遷移）

1.index.blade.php

```
<a href="{{ route('orders.create') }}" class="btn btn-primary">
```

↓
2.web.php
でルーティングした

```
Route::resource('orders', OrderController::class);
```

→ルート名orders.createは/orders/createとGETメソッドをサーバに送り、[OrderController::class, 'create']を起動
↓
3.OderContoroller.php

```
public function create(): View
    {
        return view('orders.create', [
            'customers' => DemoData::customers(),
            'products' => DemoData::products(),
        ]);
    }
```

↓
4.orders.create.blade

再掲

```
// 一覧
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

// 新規作成フォーム
Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');

// 新規登録
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

// 詳細
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

// 編集フォーム
Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');

// 更新（PUT と PATCH の両方）
Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');

// 削除
Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
```



# 変数マップ

| 変数 | 型イメージ | 作る場所 | 使う場所 |
| orders | Collection | index() | index.blade @foreach |
| search | array | ListSearch::params | list-search partial |
| customers | Collection | create() | create.blade select |

どこかの画面で@extends(layout.app)により、

```
['route' => 'orders.index',     'label' => '受注管理',     'icon' => 'cart'],

<a href="{{ route($item['route']) }}"
                       class="nav-link {{ request()->routeIs(str_replace('.index', '', $item['route']) . '*') ? 'is-active' : '' }}">
                        <span class="nav-link__icon">@include('partials.icon', ['name' => $item['icon']])</span>
                        {{ $item['label'] }}
                    </a>
```

{{ $item['label'] }}=受注管理　を押す
↓
web.php

```
Route::resource('orders', OrderController::class);
(Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');)
```

↓
ordercontoroller

```
 public function index(Request $request): View
    {
        $search = ListSearch::params($request);
        $orders = ListSearch::filter(DemoData::orders(), $search, [
            'status_resolver' => function ($order, $status) {
                if ($status === '出荷残あり') {
                    return $order->shipped < $order->qty;
                }

                return $order->status === $status;
            },
        ])->sortBy([
            ['order_date', 'desc'],
            ['id', 'desc'],
        ])->map(function ($order) {
                $allocation = StockAllocation::statusForOrder($order);
                $order->allocation_status = $allocation['status'];
                $order->allocation_badge = $allocation['badge_class'];
                $order->shippable_status = $allocation['shippable_status'];
                $order->shippable_badge = $allocation['shippable_badge'];
                $order->allocated = $allocation['allocated'];
                $order->stock_allocated = $allocation['stock_allocated'];
                $order->po_allocated = $allocation['po_allocated'];
                $order->remaining = $allocation['remaining'];
                $order->shippable = $allocation['shippable'];
                $order->price = DemoData::findProduct($order->product_id)?->price ?? 0;

                return $order;
            });

        return view('orders.index', [
            'orders' => $orders,
            'search' => $search,
        ]);
    }
```


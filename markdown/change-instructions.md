# 変更指示書（AIモデル向け）

このファイルは、プロジェクト初期に別のAIモデルへ渡した「作業指示書」です。
**上から順にそのまま実行しないでください。** 多くのセクションはすでに実装済みで、発注まわりは現行設計に置き換わっています。

### ドキュメントの位置づけ（2026-07 整理）

| 区分 | 内容 |
| --- | --- |
| **実装済み・変更不要** | セクション 1-1〜1-4、2〜7、10 |
| **廃止済み（実行禁止）** | セクション 1-5、8、9 の旧「8段階進捗」手順 → **付録A** に退避 |
| **現行の正** | 発注工程・DB設計 → **付録B**、`memo/DB.md`、`app/Support/PurchaseOrder*.php` |

新しい作業を始める前に、対象画面が **DemoData か DB か** をコードで確認してください。

---

## 0. 最初に必ず読む（前提とルール）

### このアプリの作り（重要）

- データの置き場所は **ハイブリッド** です。
  - **まだデモデータ** … 発注・仕入先・レシピなど（`app/Support/DemoData.php`、一部は `DemoState` で上書き）
  - **すでにDB化** … 製品・原材料・受注・在庫予測など（`database/migrations/`、`app/Models/`）
- DB の設計・移行方針は **`memo/DBplan.md`（地図）** と **`memo/DB.md`（列定義）** を参照します。
- 画面は `resources/views/**/*.blade.php`、ルートは `routes/web.php`、データ渡しは `app/Http/Controllers/*Controller.php` です。
- CSSは `public/css/app.css`。既存クラス（`card` `data` `badge` `btn` など）を再利用し、必要なときだけ足します。

### 守ってほしいルール
1. **`DemoData::materials()`（原材料データ本体）は絶対に消さない。** 「原材料管理画面」は消しますが、原材料のデータ自体はレシピや製造コスト計算で使われています。消すと製造コストが0になって壊れます。
2. 1コミット＝1つの意味のある変更。セクション単位でこまめに区切ると安全です。
3. 作業後は必ず動作確認（このファイルの最後「12. 動作確認」を参照）。
4. 既存のデザイン（クラス名・配色）に合わせる。勝手に色や余白を大きく変えない。

### 作業のおすすめ順番（歴史的メモ）

初期実装時は 1 → 2 → … → 12 の順で進めました。**いま新規に同じ作業をする必要はありません。**
発注・工程の変更は **付録B** と `memo/DB.md` を正としてください。

---

## 1. データ定義の変更（`app/Support/DemoData.php`）

> ✅ **セクション 1-1〜1-4 は実装済み。** 以下は当時の手順の記録です。コードを戻すときの参考にしてください。

### 1-1. 生機品番（親）の一覧を新規追加

`products()` メソッドのすぐ上あたりに、新しいメソッドを追加します。
「生機（きばた）」＝染める前の生地のことで、これが **親品番** です。

```php
/** 生機品番（親品番）一覧 */
public static function greiges(): \Illuminate\Support\Collection
{
    return collect([
        (object) ['id' => 1, 'sku' => 'KB-A', 'name' => '生機A',        'category' => '生地', 'unit' => 'm'],
        (object) ['id' => 2, 'sku' => 'KB-B', 'name' => '生機B',        'category' => '生地', 'unit' => 'm'],
        (object) ['id' => 3, 'sku' => 'KB-T', 'name' => 'Tシャツ生機',  'category' => '生地', 'unit' => 'm'],
        (object) ['id' => 4, 'sku' => 'KB-C', 'name' => '裏地C生機',    'category' => '生地', 'unit' => 'm'],
        (object) ['id' => 5, 'sku' => 'KB-D', 'name' => 'デニム生機',   'category' => '生地', 'unit' => 'm'],
    ]);
}
```

### 1-2. 製品品番（子）に「親の生機品番」と「カラー」を追加

既存の `products()` の中身を、以下のブロックで **丸ごと置き換え** ます。
- `greige_sku` = 親（生機品番）
- `color` = カラー
- `price` = 販売価格（製品品番ごと）
- **id 1〜5 はそのまま残す**（他の画面が id で参照しているため）。id 6・7 は「1つの生機に複数カラーがぶら下がる」例として追加。

```php
/** 製品品番（子品番）一覧。1つの生機品番に複数の製品品番（カラー違い）がぶら下がる */
public static function products(): \Illuminate\Support\Collection
{
    return collect([
        (object) ['id' => 1, 'sku' => 'FAB-A-BK', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ブラック',   'price' => 1200, 'category' => '生地', 'unit' => 'm', 'stock' => 320, 'stock_min' => 100],
        (object) ['id' => 2, 'sku' => 'FAB-B-NV', 'greige_sku' => 'KB-B', 'greige_name' => '生機B',       'color' => 'ネイビー',   'price' => 1500, 'category' => '生地', 'unit' => 'm', 'stock' => 80,  'stock_min' => 100],
        (object) ['id' => 3, 'sku' => 'FAB-T-WH', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ホワイト',   'price' => 900,  'category' => '生地', 'unit' => 'm', 'stock' => 540, 'stock_min' => 150],
        (object) ['id' => 4, 'sku' => 'LIN-C-BE', 'greige_sku' => 'KB-C', 'greige_name' => '裏地C生機',   'color' => 'ベージュ',   'price' => 700,  'category' => '生地', 'unit' => 'm', 'stock' => 60,  'stock_min' => 80],
        (object) ['id' => 5, 'sku' => 'DEN-D-IN', 'greige_sku' => 'KB-D', 'greige_name' => 'デニム生機',   'color' => 'インディゴ', 'price' => 1800, 'category' => '生地', 'unit' => 'm', 'stock' => 210, 'stock_min' => 100],
        (object) ['id' => 6, 'sku' => 'FAB-A-WH', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ホワイト',   'price' => 1250, 'category' => '生地', 'unit' => 'm', 'stock' => 140, 'stock_min' => 80],
        (object) ['id' => 7, 'sku' => 'FAB-T-BK', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ブラック',   'price' => 950,  'category' => '生地', 'unit' => 'm', 'stock' => 300, 'stock_min' => 150],
    ]);
}
```

> id 6・7 を足したので、製造コスト計算用の `recipes()` にも 6・7 の行を足します（足さないとコストが0になるだけで、エラーにはなりません）。
> `recipes()` の `$data` 配列に次の2行を追加してください。
> ```php
> 6 => [[1, 2.0], [3, 0.3], [4, 0.1]], // 親(id1)と同じ配合
> 7 => [[1, 1.8], [4, 0.2]],           // 親(id3)と同じ配合
> ```

### 1-3. 原材料に「品番」を追加（後述の月別価格画面で使う）

`materials()` の各行に `sku`（原材料の品番）を足します。

```php
public static function materials(): \Illuminate\Support\Collection
{
    return collect([
        (object) ['id' => 1, 'sku' => 'RM-001', 'name' => '綿糸',         'unit' => 'kg'],
        (object) ['id' => 2, 'sku' => 'RM-002', 'name' => 'ポリエステル糸', 'unit' => 'kg'],
        (object) ['id' => 3, 'sku' => 'RM-003', 'name' => '染料',         'unit' => 'kg'],
        (object) ['id' => 4, 'sku' => 'RM-004', 'name' => '仕上げ剤',      'unit' => 'L'],
    ]);
}
```

`materialPrices()` の中で各行を作っている `$result->push((object) [ ... ])` に、原材料の品番を1行足します。

```php
$result->push((object) [
    'id' => $id++,
    'material_sku' => $material->sku, // ← 追加
    'material' => $material->name,
    'unit' => $material->unit,
    'ym' => $ym,
    'price' => $price,
]);
```

### 1-4. 出荷データに「カラー・納期・出荷先・備考」を追加

`shipments()` の各行に `color` `due_date` `ship_to` `note` を足します。
さらに map の中で製品のカラーを補完します。下記のように差し替えてください。

```php
public static function shipments(): \Illuminate\Support\Collection
{
    $rows = [
        ['id' => 1, 'code' => 'SH-2606-001', 'order_code' => 'SO-2606-001', 'customer' => '東レ商事',        'product_id' => 1, 'qty' => 120, 'date' => '2026-06-11', 'due_date' => '2026-06-12', 'ship_to' => '東レ商事 滋賀倉庫',     'note' => '時間指定 午前中'],
        ['id' => 2, 'code' => 'SH-2606-002', 'order_code' => 'SO-2606-004', 'customer' => 'ユニフォーム製作所', 'product_id' => 2, 'qty' => 60,  'date' => '2026-06-12', 'due_date' => '2026-06-15', 'ship_to' => 'ユニフォーム製作所 本社', 'note' => ''],
        ['id' => 3, 'code' => 'SH-2606-003', 'order_code' => 'SO-2606-002', 'customer' => 'アパレル東京',    'product_id' => 3, 'qty' => 80,  'date' => '2026-06-14', 'due_date' => '2026-06-18', 'ship_to' => 'アパレル東京 物流センター', 'note' => '分納の1回目'],
        ['id' => 4, 'code' => 'SH-2606-004', 'order_code' => 'SO-2606-006', 'customer' => 'アパレル東京',    'product_id' => 1, 'qty' => 40,  'date' => '2026-06-15', 'due_date' => '2026-06-28', 'ship_to' => 'アパレル東京 物流センター', 'note' => ''],
    ];

    return collect($rows)->map(function ($r) {
        $product = self::findProduct($r['product_id']);
        $r['product'] = $product->name ?? '';
        $r['sku'] = $product->sku;
        $r['color'] = $product->color;   // ← 追加（カラー）
        $r['unit'] = $product->unit;
        $r['price'] = $product->price;
        $r['amount'] = $product->price * $r['qty'];
        return (object) $r;
    });
}
```

### 1-5. 発注データ（旧「8段階進捗」）

> ⚠️ **廃止済み。** `DemoData::PO_STAGES` と `schedule` 連想配列は削除済みです。
> 現行は **糸・生機・製品の3種発注** と **種別ごとの工程**（付録B）です。当時の手順は **付録A** を参照してください。

---

## 2. 商品管理（親子品番＋カラー＋製品ごとの販売価格）

### 対象ファイル
- `resources/views/products/index.blade.php`（一覧）
- `resources/views/products/create.blade.php`（登録フォーム）
- `resources/views/products/edit.blade.php`（編集フォーム）

### やること（一覧）
生機品番（親）ごとにグループ表示し、その下に製品品番（子）の「製品品番・カラー・販売価格・単位・現在庫」を並べます。

`products/index.blade.php` の `<div class="card__body card__body--flush"> ... </div>` の中身を、次の形に置き換えます。

```blade
@php $groups = $products->groupBy('greige_sku'); @endphp
@foreach ($groups as $greigeSku => $items)
    <div class="card" style="margin:16px;">
        <div class="card__head">
            <h2 class="card__title">
                <span class="code-cell" style="font-size:15px;">{{ $greigeSku }}</span>
                <span class="t-muted" style="font-weight:500;">（生機：{{ $items->first()->greige_name }}）</span>
            </h2>
            <span class="badge badge-indigo badge--plain">製品 {{ $items->count() }} 品番</span>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>製品品番</th>
                            <th>カラー</th>
                            <th class="num">販売価格</th>
                            <th>単位</th>
                            <th class="num">現在庫</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $product)
                            <tr>
                                <td class="code-cell t-strong">{{ $product->sku }}</td>
                                <td>{{ $product->color }}</td>
                                <td class="num mono">{{ number_format($product->price) }} 円</td>
                                <td class="t-muted">{{ $product->unit }}</td>
                                <td class="num mono">
                                    {{ number_format($product->stock) }}
                                    @if ($product->stock < $product->stock_min)
                                        <span class="badge badge-rose" style="margin-left:6px;">不足</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach
```

### やること（登録・編集フォーム）
`create.blade.php` と `edit.blade.php` の入力欄を「生機品番（選択）＋カラー（テキスト）＋販売価格」に変更します。
- 生機品番・カテゴリは `MasterCatalog` 経由（DB正本）で選択肢を渡す。
- カラー欄：`<input name="color">`
- 販売価格欄：既存のまま（製品ごとの価格）

`ProductController` の create/edit に追記する例：
```php
public function create(): \Illuminate\View\View
{
    return view('products.create', [
        'categories' => MasterCatalog::categoryOptions(),
        'greiges' => MasterCatalog::greiges(),
    ]);
}
```
（edit も同様に `MasterCatalog` を使う）

フォームの選択肢の例：
```blade
<div class="field">
    <label class="label" for="greige">生機品番（親）<span class="req">*</span></label>
    <select class="select" id="greige" name="greige_sku">
        @foreach ($greiges as $g)
            <option value="{{ $g->sku }}">{{ $g->sku }}（{{ $g->name }}）</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label class="label" for="color">カラー<span class="req">*</span></label>
    <input class="input" type="text" id="color" name="color" placeholder="例：ブラック">
</div>
```

### 完成イメージ
生機品番ごとにカードが分かれ、その中にカラー違いの製品品番と販売価格が並ぶ。

---

## 3. 原材料管理の削除

### やること
「原材料管理」という **画面（メニュー）だけ** を消します。原材料のデータ（`DemoData::materials()`）は消しません。

1. **メニューから消す**：`resources/views/layouts/app.blade.php` の `$nav` 配列から、次の1行を削除。
   ```php
   ['route' => 'materials.index',  'label' => '原材料管理',   'icon' => 'layers'],
   ```
2. **ルートを消す**：`routes/web.php` の次の行を削除。
   ```php
   Route::resource('materials', MaterialController::class)->except(['show']);
   ```
   さらに先頭の `use App\Http\Controllers\MaterialController;` も削除。
3. **不要ファイルを削除**：
   - `app/Http/Controllers/MaterialController.php`
   - `resources/views/materials/`（フォルダごと）
4. **確認**：月別原材料価格・商品レシピ・売上の画面が今まで通り動くこと（`DemoData::materials()` を残しているのでコスト計算は壊れません）。

---

## 4. 月別原材料価格（操作なし＋品番欄を追加）

### 対象ファイル
- `resources/views/prices/index.blade.php`
- `routes/web.php`
- `app/Http/Controllers/MaterialPriceController.php`

### やること
1. **「操作なし」にする**：
   - `prices/index.blade.php` の右上にある「価格を登録」ボタン（`route('prices.create')` のリンク）を削除。
   - `routes/web.php` の `prices.create` と `prices.store` の2行を削除。
   - `MaterialPriceController` の `create()` と `store()` メソッドを削除。
   - `resources/views/prices/create.blade.php` を削除。
2. **品番欄を「原材料」の左に追加**：
   - 単価マトリクス表のヘッダーに、`<th>原材料</th>` の **左** に `<th>品番</th>` を追加。
   - 各行で、原材料名セルの左に `material_sku` を出すセルを追加。
     `$byMonth->first()->material_sku` で取り出せます。
   - 下の「登録履歴」表にも同様に、`<th>原材料</th>` の左へ `<th>品番</th>` を追加し、各行に `{{ $p->material_sku }}` を表示。

マトリクス表の例（ヘッダーと行の冒頭だけ）：
```blade
<thead>
    <tr>
        <th>品番</th>          {{-- ← 追加（原材料の左） --}}
        <th>原材料</th>
        <th>単位</th>
        ...
    </tr>
</thead>
...
<tr>
    <td class="code-cell">{{ $byMonth->first()->material_sku }}</td>  {{-- ← 追加 --}}
    <td class="t-strong">{{ $material }}</td>
    ...
</tr>
```

### 完成イメージ
表の一番左に原材料の品番（RM-001 など）が並び、登録ボタンは無い。

---

## 5. 出荷処理（入力項目を増やし、履歴で一目で分かる）

### 対象ファイル
- `resources/views/shipments/create.blade.php`（出荷登録フォーム）
- `resources/views/shipments/index.blade.php`（出荷履歴）

データ側は「1-4」で対応済みです。

### やること（出荷登録フォーム）
出荷内容フォームに次の入力欄を用意します：**受注元・品番・納期・カラー・数量・出荷先・備考**。
- 受注元：未出荷の受注（`$pending`）から選ぶ `<select name="order_id">`（既存のまま）。選択肢に品番とカラーも表示すると分かりやすい：
  ```blade
  <option value="{{ $o->id }}">{{ $o->code }} ／ {{ $o->sku }} ／ {{ $o->customer }}（残 {{ $o->qty - $o->shipped }}）</option>
  ```
- 品番・カラー・納期：受注元に紐づく値だが、デモなので手入力欄として用意してOK。
  - 品番：`<input name="sku">`、カラー：`<input name="color">`、納期：`<input type="date" name="due_date">`
- 数量：`<input type="number" name="qty">`（既存）
- 出荷先：`<input type="text" name="ship_to" placeholder="例：○○倉庫">`
- 備考：`<textarea name="note" rows="2">`

### やること（出荷履歴）
`shipments/index.blade.php` の表ヘッダーと各行に列を追加し、一目で分かるようにします。
列の並び（おすすめ）：**出荷番号／受注番号／得意先／品番／カラー／数量／納期／出荷日／出荷先／備考**

各行の追加分の例：
```blade
<td class="code-cell t-strong">{{ $s->sku }}</td>
<td>{{ $s->color }}</td>
<td class="num mono">{{ number_format($s->qty) }} {{ $s->unit }}</td>
<td class="mono">{{ $s->due_date }}</td>
<td class="mono t-muted">{{ $s->date }}</td>
<td>{{ $s->ship_to }}</td>
<td class="t-muted" style="font-size:12px;">{{ $s->note }}</td>
```
（合計行 `tfoot` の `colspan` がある場合は、列数を増やした分だけ数値を直すこと）

---

## 6. 得意先（得意先ごとの受注履歴）

### やること
得意先名をクリックすると、その得意先の受注履歴が見える詳細画面を作ります（在庫詳細と同じパターン）。

1. **ルート追加**（`routes/web.php`、`customers.create`/`store` の後ろに）：
   ```php
   Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
   ```
2. **コントローラ追加**（`CustomerController` に `show` を追加）：
   ```php
   public function show(int $customer): \Illuminate\View\View
   {
       $target = DemoData::customers()->firstWhere('id', $customer) ?? abort(404);
       return view('customers.show', [
           'customer' => $target,
           'orders' => DemoData::orders()->where('customer', $target->name)->values(),
       ]);
   }
   ```
3. **一覧でリンク化**（`customers/index.blade.php`）：得意先名セルをリンクに。
   ```blade
   <td class="t-strong"><a href="{{ route('customers.show', $c->id) }}" class="link-strong">{{ $c->name }}</a></td>
   ```
4. **詳細ビュー新規作成**（`resources/views/customers/show.blade.php`）：得意先情報＋受注一覧（受注番号・品番・数量・納期・ステータス）。在庫詳細 `resources/views/inventory/show.blade.php` の表部分を真似して作るとよい。

---

## 7. 仕入先（仕入先ごとの発注履歴）

「6. 得意先」と同じ作りを、仕入先側でも作ります。

1. **ルート追加**：
   ```php
   Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
   ```
2. **コントローラ追加**（`SupplierController` に `show`）：
   ```php
   public function show(int $supplier): \Illuminate\View\View
   {
       $target = DemoData::suppliers()->firstWhere('id', $supplier) ?? abort(404);
       return view('suppliers.show', [
           'supplier' => $target,
           'purchases' => DemoData::purchaseOrders()->where('supplier', $target->name)->values(),
       ]);
   }
   ```
3. **一覧でリンク化**（`suppliers/index.blade.php`）：仕入先名をリンクに。
   ```blade
   <td class="t-strong"><a href="{{ route('suppliers.show', $s->id) }}" class="link-strong">{{ $s->name }}</a></td>
   ```
4. **詳細ビュー新規作成**（`resources/views/suppliers/show.blade.php`）：仕入先情報＋発注一覧（発注番号・品番・数量・進捗段階・入荷予定）。

---

## 8. 発注管理

> ⚠️ **このセクション本文は旧「8段階進捗」仕様です（実行しない）。**
>
> **現行の要点（2026-07）**
> - 発注は **糸 / 生機 / 製品** の3種別（`PurchaseOrderType`）
> - 一覧の **「工程」列** は `PurchaseOrderDisplay::label()` の1バッジ（`工程名（一部入荷）` など）
> - 編集で手動選択できるのは **生機＝織編機投入済**、**製品＝染機投入済** のみ
> - 入荷予定日は行ごと1入力（糸・生機＝`due_date`、製品＝`finish_date`）。工程別予定日フォームは **ない**
> - 詳細は **付録B**

### 対象ファイル（旧手順・参考）
- `resources/views/purchases/index.blade.php`（一覧）
- `resources/views/purchases/edit.blade.php`（編集）
- `app/Http/Controllers/PurchaseOrderController.php`

### やること（一覧 `purchases/index.blade.php`）【旧・実行しない】
1. **「商品」列を品番に**：`{{ $po->product }}` → `{{ $po->sku }}`（`code-cell` クラス推奨）。
2. **得意先列を追加してリンク**：得意先名をクリックで得意先詳細へ。
   ```blade
   <td><a href="{{ route('customers.show', \App\Support\DemoData::customers()->firstWhere('name', $po->customer)->id) }}" class="link-strong">{{ $po->customer }}</a></td>
   ```
   （※ シンプルにしたい場合は、まずリンクなしで `{{ $po->customer }}` を表示するだけでもOK）
3. **進捗を8段階バッジで表示**：今までの「受入数/数量バー」を、現在の段階バッジに置き換え。
   ```blade
   <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
   ```
4. **上がり予定日・先方連絡予定日の列を追加**：`{{ $po->finish_date }}` と `{{ $po->contact_date }}` を表示。

### やること（編集 `purchases/edit.blade.php`）
1. **得意先の選択欄を追加**（`DemoData::customers()` を選択肢に）。そのため `PurchaseOrderController@edit`（と `@create`）で `'customers' => DemoData::customers()` を view に渡す1行を追加。
2. **商品の選択肢を品番に**（既に品番表示済みのはず。`{{ $p->sku }}`）。
3. **進捗段階の選択欄**：8段階から選ぶ。
   ```blade
   <div class="field">
       <label class="label" for="stage">進捗段階</label>
       <select class="select" id="stage" name="stage">
           @foreach (\App\Support\DemoData::PO_STAGES as $st)
               <option value="{{ $st }}" @selected($st === $purchase->stage)>{{ $st }}</option>
           @endforeach
       </select>
   </div>
   ```
4. **各段階ごとの「次工程の予定日」入力欄**：8段階それぞれに日付入力を並べる。
   ```blade
   <div class="field">
       <label class="label">各工程の予定日</label>
       @foreach (\App\Support\DemoData::PO_STAGES as $st)
           <div class="form-row" style="align-items:center;margin-bottom:8px;">
               <span>{{ $st }}</span>
               <input class="input" type="date" name="schedule[{{ $st }}]" value="{{ $purchase->schedule[$st] ?? '' }}">
           </div>
       @endforeach
   </div>
   ```
5. **納期の下に「上がり予定日」「先方連絡予定日」**：納期入力欄のすぐ下に2つの日付欄を追加。
   ```blade
   <div class="form-row">
       <div class="field">
           <label class="label" for="finish_date">上がり予定日</label>
           <input class="input" type="date" id="finish_date" name="finish_date" value="{{ $purchase->finish_date }}">
       </div>
       <div class="field">
           <label class="label" for="contact_date">先方連絡予定日</label>
           <input class="input" type="date" id="contact_date" name="contact_date" value="{{ $purchase->contact_date }}">
       </div>
   </div>
   ```

> 注意：保存処理（`update`）はデモのため実際には保存しません。フォームに値が表示されればOKです。

### 完成イメージ（旧）
発注一覧で得意先・品番・8段階・各工程予定日を表示する仕様。**現行では付録Bの工程表示に置き換え済み。**

---

## 9. 在庫管理（生産中の品番・数量を発注履歴と紐付けて表示）

> ⚠️ **「生産中」の絞り込みは旧 `PO_STAGES` 基準です。**
> 現行は **製品発注タブ** で `PurchaseOrderDisplay::filterOptions()` による工程フィルタを使います（`InventoryController`）。

### 対象ファイル
- `app/Http/Controllers/InventoryController.php`
- `resources/views/inventory/index.blade.php`

### やること【旧・実行しない】
「在庫一覧」の下に **「生産中（発注済み）」** のセクションを追加し、まだ完成・出荷していない発注（生産中）を一覧表示します。各行から発注管理へリンクします。

1. **コントローラ**：`index()` の中で「生産中」の発注を絞り込み、viewに渡す。
   「生産中」＝段階が「原材料未発注」と「製品出荷済」以外、と定義します。
   ```php
   // 生産中（発注済みで、まだ出荷完了していない）の発注を抽出
   $inProduction = DemoData::purchaseOrders()
       ->whereNotIn('stage', ['原材料未発注', '製品出荷済'])
       ->values();
   ```
   これを `view('inventory.index', [...])` の配列に `'inProduction' => $inProduction,` として追加。
2. **ビュー**：在庫一覧カードの後ろに、次のカードを追加。
   ```blade
   <div class="card" style="margin-top:16px;">
       <div class="card__head">
           <h2 class="card__title">生産中（発注済み）</h2>
           <span class="badge badge-amber badge--plain">{{ $inProduction->count() }} 件</span>
       </div>
       <div class="card__body card__body--flush">
           <div class="table-wrap">
               <table class="data">
                   <thead>
                       <tr><th>品番</th><th class="num">数量</th><th>進捗段階</th><th>上がり予定</th><th>発注番号</th></tr>
                   </thead>
                   <tbody>
                       @forelse ($inProduction as $po)
                           <tr>
                               <td class="code-cell t-strong">{{ $po->sku }}</td>
                               <td class="num mono">{{ number_format($po->qty) }} {{ $po->unit }}</td>
                               <td><span class="badge badge-indigo badge--plain">{{ $po->stage }}</span></td>
                               <td class="mono">{{ $po->finish_date }}</td>
                               <td><a href="{{ route('purchases.edit', $po->id) }}" class="link-strong code-cell">{{ $po->code }}</a></td>
                           </tr>
                       @empty
                           <tr><td colspan="5" class="empty">生産中の発注はありません。</td></tr>
                       @endforelse
                   </tbody>
               </table>
           </div>
       </div>
   </div>
   ```

### 完成イメージ（旧）
在庫管理画面で生産中の品番・数量を表示する仕様。**現行は製品タブ＋工程フィルタ（付録B）。**

---

## 10. 他画面への波及チェック（品番・カラー対応）

データ構造を変えたので、`product` 名を表示していた他画面が崩れていないか確認します。
基本は「商品名 → 品番（sku）」に統一済みのはずですが、カラーが増えたので必要に応じて表示します。

- 受注一覧・受注詳細・売上・ダッシュボード：`sku` 表示のままでOK。カラーも見せたい場合は `{{ $o->color ?? '' }}` を補助表示。
- もし `->name` を画面でそのまま使っている箇所があれば、`->sku`（必要ならカラーも）に直す。

確認コマンド（ターミナル）：
```bash
# views の中で商品名らしき表示が残っていないかざっと確認
grep -rn "->product\b\|->name" resources/views
```

---

## 11. ドキュメントの追従（任意）

関連ドキュメントを揃えるときの参照先です。

| トピック | 参照先 |
| --- | --- |
| 発注・工程・DB | `memo/DB.md`（付録B と同内容） |
| 全体のDB方針 | `memo/DBplan.md` |
| 反数・数量 | `markdown/qty-tan-spec.md` |
| ポートフォリオ説明 | `markdown/portfolio.md` |

> 本ファイルの「8段階進捗」記述は `portfolio.md` に残っている場合があります。発注まわりは **付録B** を正としてください。

---

## 12. 動作確認（毎回やる）

1. **PHP構文チェック**（編集したPHPファイルごと）：
   ```bash
   php -l app/Support/DemoData.php
   php -l app/Http/Controllers/PurchaseOrderController.php
   # 編集した他のControllerも同様に
   ```
   `No syntax errors detected` が出ればOK。

2. **開発サーバーを起動**（すでに起動している場合は不要）：
   ```bash
   php artisan serve
   ```

3. **各ページが開けるか確認**（別ターミナルで）：
   ```bash
   for p in / /products /prices /orders /purchases /purchases/1/edit \
            /shipments /shipments/create /receivings /inventory \
            /inventory/2 /sales /customers /customers/1 /suppliers /suppliers/1; do
     echo "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000$p)  $p"
   done
   ```
   すべて `200` ならOK。`500` が出たらそのページのコントローラ／ビューを見直す。

4. **目視確認のポイント**：
   - 商品管理：生機品番ごとにまとまり、カラーと販売価格が出ているか。
   - 月別原材料価格：左端に原材料の品番、登録ボタンが無い。
   - 出荷履歴：受注元・品番・カラー・納期・出荷先・備考が一目で見える。
   - 得意先／仕入先：名前クリックで履歴が出る。
   - 発注管理：種別（糸/生機/製品）・品番・**工程バッジ1つ**・入荷予定日。編集は生機/製品の手動工程のみ。
   - 在庫管理：製品タブの生産中一覧・工程フィルタ。受注詳細に **生産状況**（糸→生機→製品）がある。

---

## 付録：今回の対応と元の要望の対応表

| 元の要望 | このファイルの該当セクション |
| --- | --- |
| 商品管理：親(生機品番)・子(製品品番)に分け、価格は製品ごと | 1-1, 1-2, 2 |
| 原材料管理：削除 | 3 |
| 月別原材料価格：操作なし／原材料の左に品番欄 | 1-3, 4 |
| 出荷処理：受注元・品番・納期・カラー・数量・出荷先・備考／履歴で一目 | 1-4, 5 |
| 得意先：得意先ごと受注履歴 | 6 |
| 仕入先：仕入先ごと発注履歴 | 7 |
| 発注管理（旧8段階） | 1-5, 8, **付録A**（廃止） |
| 発注管理（現行・種別ごと工程） | **付録B**, `memo/DB.md` |
| 在庫管理：生産中表示 | 9（旧）、現行は付録B＋`InventoryController` |

---

## 付録A：旧「8段階進捗」仕様（2026年初期・廃止）

製品発注1件にサプライチェーン全体（糸〜出荷）を載せるモデル。**2026-07 に廃止。** コードベースに戻さないこと。

### 旧定数 `DemoData::PO_STAGES`

```
原材料未発注 → 原材料発注済 → 原材料出荷済 → 織編機投入済
→ 生機出荷済 → 染機投入済 → 製品在庫中 → 製品出荷済
```

### 旧デモデータの持ち方

- 製品発注のみ `stage`（上記8つのどれか）
- `schedule` … 工程名 → 予定日の連想配列（**削除済み**）
- `progress` … 8段階の何番目かから%を算出

### 旧UIの特徴

- 発注編集で8段階すべてを手動選択
- 各工程ごとに予定日入力欄を8個並べる
- 在庫「生産中」は `原材料未発注` と `製品出荷済` 以外を表示

廃止理由：糸・生機・製品が **別発注** になったため、1本の8段階バーは業務と合わない。

---

## 付録B：発注工程・表示（現行・2026-07）

**正：** `memo/DB.md` の「発注工程」節。実装は以下のクラスに集約。

| クラス | 役割 |
| --- | --- |
| `PurchaseOrderStages` | 種別ごとの工程名一覧・手動選択肢 |
| `PurchaseOrderDisplay` | 画面用ラベル1本化（`label()`） |
| `GreigeYarnReadiness` | 生機の「糸入荷済」自動判定 |
| `OrderProductionStatus` | 受注詳細の生産状況行 |

### 表示ルール（全画面共通）

| 条件 | 表示 |
| --- | --- |
| `cancelled` | キャンセル |
| `draft` | 下書き |
| `received` | 入荷完了 |
| 進行中 | **工程名** |
| 進行中＋分納 | **工程名（一部入荷）** |

裏では `status`（入荷の事実）と手動 `stage` を分けて保持。画面は `PurchaseOrderDisplay::label()` のみ使う。

### 種別ごとの工程

| 種別 | 工程 | 手動/自動 |
| --- | --- | --- |
| **糸** | 糸発注済 → 糸出荷済 → 織工場への糸入荷済 | すべて自動 |
| **生機** | 発注済 → 糸入荷済 → 織編機投入済 → 生機出荷済 | 糸入荷済・生機出荷済は自動。**織編機投入済のみ手動** |
| **製品** | 染機投入済 → 製品在庫中 → 入荷完了 | **染機投入済のみ手動**。在庫中は一部入荷で自動 |

### 画面

| 画面 | 現行の要点 |
| --- | --- |
| 発注一覧 | **工程**列（1バッジ）。出荷先列は維持。旧「状態」列は統合 |
| 発注編集 | 生機＝織編機投入済、製品＝染機投入済のみセレクト |
| 受注詳細 | **生産状況** … 関連する糸→生機→製品発注を並べる |
| 在庫・仕入先 | 工程フィルタは `PurchaseOrderDisplay::filterOptions()` |

### 入荷予定日

| 種別 | 保存先 |
| --- | --- |
| 糸・生機 | `due_date` |
| 製品 | `finish_date` |

`purchase_order_schedule_events` テーブルは **作らない**（工程別予定日UIも作らない）。

### DB移行時（未実装・設計のみ）

```
purchase_orders          … 共通ヘッダ（type, status, due_date など）
├── yarn_purchase_orders
├── greige_purchase_orders   … stage（織編機投入済のみ手動）
└── product_purchase_orders  … stage（染機投入済のみ手動）, finish_date
```

詳細列定義は `memo/DB.md` の段階4を参照。

### 触るときのファイル例

- `app/Support/DemoData.php` … デモ発注データ
- `app/Http/Controllers/PurchaseOrderController.php`
- `resources/views/purchases/*.blade.php`
- `resources/views/orders/show.blade.php`（生産状況）
- `tests/Unit/PurchaseOrderDisplayTest.php`


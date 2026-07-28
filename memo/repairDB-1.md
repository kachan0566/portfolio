# これは「repairDB.md」の"作業のおすすめ順 1.フォールバック廃止・MasterCatalog 統一（他の修正の土台）の実装メモ

「フォールバック廃止・MasterCatalog 統一」が **完了した** と言える状態を、作業内容・確認方法・完了判定の3つに分けて整理します。

---

## この作業のゴール（1行）

> **実行時のマスタ参照は、DB（Eloquent）だけを正本にし、`DemoData` の固定配列やフォールバック経路を通らない。**

シード用の `base*Rows()` や `DemoData::products()`（シーダーが読む固定配列）は **残してよい** です。消すのは **画面・計算・APIが実行時に辿る経路** です。

---

## A. やること（作業チェックリスト）

### A-1. `MasterCatalog` からフォールバックを削除

現状は「テーブルに1行でもあればDB、なければ固定配列」です。

```19:24:app/Support/MasterCatalog.php
    public static function products(): Collection
    {
        return self::tableHasRows('products')
            ? Product::displayList()
            : DemoData::products();
    }
```

**完了後のイメージ:**
- `tableHasRows()` を削除（または未使用にする）
- 各メソッドが **常に** `Product::displayList()` / `Greige::displayList()` などを呼ぶだけ
- `DemoData` への参照が `MasterCatalog` 内に **0件**

対象メソッド（すべて）:
- `products`, `findProduct`, `findProductOrFail`
- `greiges`, `findGreige`, `findGreigeByProductId`
- `yarnMaterials`, `findMaterial`
- `categoryOptions`
- `findSupplier`, `findShipTo`

### A-2. 実行時コードの `DemoData` 固定マスタ参照を `MasterCatalog` に寄せる

**アプリ本体（`app/`）で、実行時に残してはいけない参照:**

| ファイル | 現状 | 直す先 |
|----------|------|--------|
| `QtyHelper.php` | `DemoData::greiges()` / `products()` フォールバック | `MasterCatalog` または `Greige` / `Product` モデル |
| `DemoData.php` 内の実行時メソッド | `self::findProduct()` など固定配列 | `MasterCatalog::findProduct()` など |
| `OrderController.php` | `Product::query()->find()` を直接 | `MasterCatalog::findProduct()` |

`DemoData` 内で特に直す実行時メソッド:
- `unitCostBreakdown()` / `greigeUnitCostBreakdown()` / `greigeRecipes()`
- `unitProfitSummary()`（`?? self::findProduct()` のフォールバック）
- `stockMovements()` / `monthlySalesByProduct()` / `dashboard()` の `findProduct` / `products()`

※ `enrichPurchaseOrder()` など **シーダー専用・未使用** なら、シード用として分離するか、そのまま残しても実行経路に入れなければよい。

### A-3. `categories` の扱いを決める

フォールバック廃止後は `categoryOptions()` は常に `Product::categoryOptions()` になります。

- `DemoData::categories()` は **シード専用** に降格、または定数化
- 実行時に `DemoData::categories()` を呼ばない

### A-4. テストの更新

| テスト | 変更の方向 |
|--------|------------|
| `MasterCatalogTest` | 「DB空でも固定配列が返る」系があれば削除・変更 |
| 各 Feature テスト | マスタが必要なテストは `MasterCatalogSeeder`（または `MasterFoundationSeeder`）を必ず実行 |
| `DemoData::products()` をテスト内で参照 | シード後の期待値取得なら `MasterCatalog::products()` または `Product::query()` に置換 |

`MasterCatalogTest` は現状、シード後のDB整合を見るテストが中心なので、**「シードなしで固定データに落ちない」テストを追加** すると完了判定が明確になります。

### A-5. ドキュメント更新

- `MasterCatalog` のクラスコメントから「なければ DemoData」と書いてある部分を削除
- `DemoData` のコメントを「シード用・一部集計用。マスタ参照は MasterCatalog を使う」と明記

---

## B. 確認方法

### B-1. コード検索（機械的チェック）

作業後、次が **0件**（またはシーダー・テストのシード比較だけ）であること。

```bash
# MasterCatalog 内に DemoData 参照がない
rg "DemoData" app/Support/MasterCatalog.php

# 実行時コードが固定マスタを直接読んでいない
rg "DemoData::(findProduct|findGreige|findMaterial|products|greiges|materials|suppliers|shipTos|categories)" app/

# フォールバック判定が残っていない
rg "tableHasRows" app/
```

`QtyHelper.php` と `DemoData.php`（実行時メソッド部分）も同様に確認。

**許容される残り:**
- `database/seeders/` 内の `DemoData::base*()` / `DemoData::products()` 等
- テストで「シード元データとの件数比較」としての `DemoData::products()->count()`

### B-2. テスト実行

```bash
php artisan test
```

特にマスタに依存するもの:
- `MasterCatalogTest`
- `RecipeTest` / `ManufacturingCostTest` / `CostScreenTest`
- `InventoryTabTest` / `InventoryForecastTest`
- `PurchaseOrderTest` / `GreigeRecipeTest` / `YarnPriceTest`

**全部通過** = シード済み環境では挙動が壊れていない。

### B-3. シードなしの挙動確認（フォールバック廃止の本丸）

```bash
php artisan migrate:fresh
# シードは実行しない
php artisan test --filter=MasterCatalogTest  # 新規テスト含む
```

**期待する挙動（どちらかをチームで決めて統一）:**

| 方針 | シードなしのとき |
|------|------------------|
| **A: 空を返す** | `MasterCatalog::products()` → 空のコレクション。画面は「データなし」 |
| **B: 明示的エラー** | マスタ未投入時は例外 or 503。「シードを実行してください」 |

どちらでもよいですが、**固定デモデータに silently フォールバックしない** ことが完了条件です。

### B-4. シードありの手動確認（主要画面）

`php artisan migrate:fresh --seed`（または普段使っているシーダー一式）のあと:

| 画面 | 確認ポイント |
|------|-------------|
| 製品一覧 | 品番・色がDBどおり表示される |
| レシピ・原価 | 製品名・生機名・糸名が正しい（固定配列とズレていない） |
| 在庫一覧 | 製品タブの品番・在庫が表示される |
| 発注登録 | 仕入先・生機・製品の選択肢がDBから出る |
| 受注詳細 | 製品情報・単価が正しい |

**以前と同じデータが見える** = 統一後も正しくDBを読めている。

### B-5. 回帰の焦点（バグが出やすい所）

フォールバック廃止で特に確認したい点:

1. **原価計算** … `DemoData::unitCostBreakdown()` が `MasterCatalog` 経由になったか（製品IDと生機SKUの対応）
2. **反↔m換算** … `QtyHelper::metersPerTan()` がDBの `meters_per_tan` を読むか
3. **生機在庫** … `GreigeInventory` が `MasterCatalog::findGreige()` で名前解決できるか
4. **バリデーション** … 発注・レシピ登録の `Rule::in(MasterCatalog::...)` が空DBでどう振る舞うか（意図どおりか）

---

## C. 完了判定（これが全部 YES なら完了）

| # | 完了条件 |
|---|----------|
| 1 | `MasterCatalog` に `DemoData` 参照・`tableHasRows` がない |
| 2 | `app/` の実行時コードに `DemoData::findProduct` 等の固定マスタ参照がない |
| 3 | `QtyHelper` に `DemoData::products/greiges` フォールバックがない |
| 4 | `DemoData` の原価・集計系がマスタ解決に `MasterCatalog` を使っている |
| 5 | シードなしでは固定デモマスタが **表示されない**（空 or エラー） |
| 6 | シードありでは `php artisan test` が全通過 |
| 7 | 主要画面の手動確認で、マスタ名・品番・原価に異常がない |
| 8 | テストが「フォールバック前提」ではなく「DB正本前提」に更新されている |

---

## D. この作業の「スコープ外」（次の作業に回す）

完了判定に **含めなくてよい** もの（混同しやすいので明示）:

| 項目 | 理由 |
|------|------|
| JSON（予想スナップショット等）のDB化 | 別タスク |
| `stockMovements()` 固定配列のDB化 | 別タスク |
| `CURRENT_YM` / `today()` の設定化 | カテゴリ4（設定の整理） |
| `OrderOverlay` 削除 | カテゴリ4 |
| `GreigeRoll` 内の `Greige::findBySku()` | 永続化層の直接モデル参照。厳密統一は任意 |
| シード用 `DemoData::products()` 配列そのもの | カテゴリ2（シード材料として残す） |

---

## E. 追加するとよいテスト（完了の証拠）

```php
// 例: シードなしでは固定マスタに落ちない
public function test_master_catalog_returns_empty_without_seed(): void
{
    $this->assertTrue(MasterCatalog::products()->isEmpty());
    $this->assertNull(MasterCatalog::findProduct(1));
}

// 例: シード後はDBの値が返る
public function test_master_catalog_reads_from_database_after_seed(): void
{
    $this->seed(MasterFoundationSeeder::class);
    $this->seed(MasterCatalogSeeder::class);

    $product = MasterCatalog::findProduct(1);
    $this->assertNotNull($product);
    $this->assertSame('FAB-A-BK', $product->sku);
}
```

この2つが通れば、「フォールバック廃止」と「DB正本化」がテストで担保されます。

---

Agentモードに切り替えてもらえれば、このチェックリストに沿って実装を進められます。
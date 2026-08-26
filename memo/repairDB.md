「全ての取得はDB参照」という最終目標に対して、**まだDBを見ていない（またはDBと別のデータ源を見ている）部分**を整理しました。

---

## 結論

大きく分けると **4種類** 残っています。

1. **固定配列**（`DemoData` 内のハードコード）
2. **JSONファイル**（`storage/app/*.json`）
3. **マスタのフォールバック**（DBが空のとき固定データに戻る）
4. **DBテーブルはあるのに、コードがまだJSONを読んでいる**（移行の取りこぼし）

---

## 1. 固定配列（完全にDB未使用）

### マスタ系（`DemoData` 内）

| データ | メソッド | 主な利用箇所 |
|--------|----------|-------------|
| カテゴリ | `categories()` | `MasterCatalog`（DB空時） |
| 生機マスタ | `greiges()` | 同上 + `QtyHelper` のフォールバック |
| 製品マスタ | `products()` | 同上 + **ダッシュボードの在庫不足判定** |
| 原材料マスタ | `materials()` | `MasterCatalog`（DB空時）+ 原価計算の `findMaterial()` |
| 得意先 | `customers()` | シーダーのみ（画面は `Customer` モデルでDB参照） |
| 仕入先 | `suppliers()` | `MasterCatalog`（DB空時） |
| 出荷先 | `shipTos()` | `MasterCatalog`（DB空時） |
| 糸の初期在庫 | `yarnStockBase()` / `yarnStockKg()` | シーダーのみ（実在庫は `yarn_stock_movements`） |

※ 画面のマスタ一覧は `MasterCatalog` 経由で **DBにデータがあればDB** ですが、**テーブルが空だと上記の固定配列** に戻ります。

### トランザクション・集計系

| データ | メソッド | 利用箇所 |
|--------|----------|----------|
| **在庫移動履歴** | `stockMovements()` | ダッシュボード、在庫画面の「移動履歴」タブ |
| **ダッシュボードの売上推移（1〜5月）** | `dashboard()` 内の固定配列 | ダッシュボードのグラフ |
| **ダッシュボードの在庫不足** | `dashboard()` → `products()` の `stock` 列 | 製品マスタの固定在庫数を使用（`product_rolls` ではない） |

```1298:1324:app/Support/DemoData.php
        $lowStock = self::products()->filter(fn ($p) => $p->stock < $p->stock_min)->values();
        ...
            'trend' => collect([
                ['ym' => '2026-01', 'sales' => 980000,  'profit' => 320000],
                ...
                ['ym' => '2026-06', 'sales' => $salesByProduct->sum('sales'), 'profit' => $calculableRows->sum('profit')],
            ]),
```

### 固定値・日付（DBではないがアプリ設定として残っている）

| 項目 | 場所 | 用途 |
|------|------|------|
| `CURRENT_YM = '2026-06'` | `DemoData` | 各画面の「今月」 |
| `today()` | `DemoData` | 発注日デフォルト、長期在庫の基準日など |
| `METERS_PER_TAN_*` | `DemoData` / `QtyHelper` | 反↔m 換算のデフォルト |

---

## 2. JSONファイル（DB未使用）

`storage/app/` 配下のJSONを直接読み書きしています。

| ファイル | クラス | 用途 | 利用画面・処理 |
|----------|--------|------|----------------|
| `greige_forecast_manual_adjustments.json` | `GreigeForecastManualAdjustment` | 生機予想の手動調整 | 生機予想タブ |

**済（repairDB 2d・2026-08）:** `allocation_conversions.json` → `allocation_conversions` テーブル、`po_order_links.json` → `purchase_orders.order_id`

---

## 3. 在庫予想のDB移行状況

製品・生機・統合の提出版と、製品予想の手動調整はDB参照へ切り替え済みです。

| DBテーブル | 参照モデル | 状態 |
|-------------|------------|------|
| `forecast_manual_adjustments` | `ForecastManualAdjustment` | **DB**（済） |
| `month_end_forecasts` / `month_end_forecast_lines` | `MonthEndForecast` | **DB**（済） |
| `greige_month_end_forecasts` / `greige_month_end_forecast_lines` | `GreigeMonthEndForecast` | **DB**（済） |
| `combined_month_end_forecasts` | `CombinedMonthEndForecast` | **DB**（済） |
| `shipment_plans` | `ShipmentPlan` | **DB**（済） |
| `sales_forecasts` / `sales_forecast_lines` | `SalesForecast` | **DB**（済） |

生機予想でJSONに残っているのは、手動調整の `greige_forecast_manual_adjustments.json` です。

### 使われていないDBテーブル（取得経路なし）

マイグレーションで作られたが、アプリから読まれていません。

| テーブル | 想定用途 | 実際 |
|----------|----------|------|
| `inbound_lots` | 入庫ロット管理 | `FifoLotSimulator` は `product_rolls` を使用 |
| `shipment_lot_consumptions` | 出荷時のロット消費 | 未使用（`shipment_roll_allocations` 等を使用） |

---

## 4. 隠れた問題：DB移行済みなのに内部で固定マスタを参照

レシピ・糸価格などはDBから読んでいますが、**製品名・生機名の解決に `DemoData::findProduct()` / `findGreige()` / `findMaterial()` を使っており、これらは固定配列** です。

```95:113:app/Support/DemoData.php
    public static function findProduct(int $id): ?object
    {
        return self::products()->firstWhere('id', $id);
    }

    public static function findGreige(string $sku): ?object
    {
        return self::greiges()->firstWhere('sku', $sku);
    }
```

影響する処理：

- `unitCostBreakdown()` / `unitCost()`（原価計算）
- `greigeRecipes()` / `greigeUnitCostBreakdown()`
- `stockMovements()` の製品名付与
- `monthlySalesByProduct()` の製品情報

画面側は `MasterCatalog` を使っていても、**原価・予想の計算ロジック内部では固定マスタを見る** 可能性があります。

同様に `QtyHelper` もDB検索失敗時に `DemoData::greiges()` / `products()` にフォールバックします。

```45:48:app/Support/QtyHelper.php
            if ($greigeSku !== null) {
                $legacyGreige = DemoData::greiges()->firstWhere('sku', $greigeSku);
```

---

## 5. 画面・機能ごとの「未DB化」一覧

| 画面 / 機能 | まだDB以外を見ている部分 |
|-------------|--------------------------|
| **ダッシュボード** | 移動履歴（固定）、在庫不足（固定 `stock`）、売上推移1〜5月（固定） |
| **在庫一覧** | 移動履歴タブ（固定） |
| **在庫詳細** | — |
| **受注詳細** | — |
| **発注詳細** | — |
| **在庫予想（生機）** | 手動調整（JSON） |
| **マスタ全般** | テーブル空時のフォールバック（固定配列） |
| **日付・今月** | `CURRENT_YM` / `today()` 固定値 |

---

## 6. 参考：すでにDB参照できているもの

最終目標に対して **取得はDB化済み** の主なデータです。

| データ | 参照経路 |
|--------|----------|
| 受注 | `Order::displayList()` |
| 発注 | `PurchaseOrder::displayList()` |
| 入荷 | `Receiving::displayList()` |
| 出荷 | `Shipment::displayList()` |
| 製品・生機在庫（反） | `product_rolls` / `greige_rolls` |
| 引当 | `order_allocations` |
| 引当変換履歴 | `allocation_conversions`（入荷時の po→stock 変換） |
| 発注↔受注リンク（生産意図） | `purchase_orders.order_id` |
| 糸在庫 | `yarn_stock_movements` |
| 糸引当 | `yarn_allocations` |
| 製品レシピ | `product_recipes` |
| 生機レシピ | `greige_recipes` + 明細 |
| 糸単価 | `material_prices` |
| 出荷予定 | `shipment_plans` |
| 製品月末予想（手動調整・提出版） | `forecast_manual_adjustments` / `month_end_forecasts` / `month_end_forecast_lines` |
| 生機月末予想（提出版） | `greige_month_end_forecasts` / `greige_month_end_forecast_lines` |
| 製品＋生機の統合予想（提出版） | `combined_month_end_forecasts` |
| 売上見通し（提出版） | `sales_forecasts` / `sales_forecast_lines` |
| 得意先一覧 | `customers` テーブル |
| 仕入先一覧 | `suppliers` テーブル |

---

## 移行の優先順位（おすすめ）

1. **残るJSONのDB化** … 生機予想の手動調整（`greige_forecast_manual_adjustments.json`）
2. **固定配列の廃止** … `stockMovements()`、ダッシュボードの `lowStock` / `trend`
3. **内部の `findProduct` 等を `MasterCatalog` に統一** … 原価計算の正確性
4. **フォールバック廃止** … `MasterCatalog` の「DB空なら固定データ」をやめ、シード必須にする

在庫予想の提出版は製品・生機・統合ともDB化済みです。**引当変換履歴・発注↔受注リンクも DB 化済み（repairDB 2d・2026-08）**。次のギャップは、生機予想の手動調整の JSON 保存です。

特定の領域（例：在庫予想だけ、JSON一覧だけ）に絞った移行手順が必要なら、その範囲で詳しく整理できます。

「全部DB」という目標に対して、**無理にDB化しなくていい／DB化の対象ではない**と判断できるものを整理します。

---

## 1. そもそも「データ」ではないもの（DBの対象外）

これらは **保存する情報** ではなく、**ルール・計算・設定** です。

| 対象 | 根拠 |
|------|------|
| `expectedArrivalDate()` など発注の日付・数量の計算 | すでにDBから取った発注オブジェクトを **加工しているだけ**。DBに「入荷予定日テーブル」を作る必要はない |
| `unitCostBreakdown()` の計算ロジック部分 | レシピ・単価・加工費を **組み合わせる式**。式そのものはコードでよい（参照するマスタはDB化すべき） |
| `salesTrendMonths()` など月リスト生成 | 日付の計算処理。データ取得ではない |
| `orderProgressStatus()` などステータス判定 | 「未出荷／一部出荷」の **判定ルール**。状態は受注テーブルにあり、表示ラベルはコードで決められる |
| `QtyHelper` の反↔m換算 | **換算ルール**。マスタに `meters_per_tan` があればそちらを優先し、無いときのデフォルト値だけ定数でよい |

例えるなら、レシピの材料はDBに置くが、「1反＝何mか」の計算式はアプリ側のルール、というイメージです。

---

## 2. シード用の「材料」（実行時の取得対象ではない）

| 対象 | 根拠 |
|------|------|
| `baseOrderRows()` / `basePurchaseOrderRows()` / `baseReceivingRows()` / `baseAllocationRows()` | **DBに入れる前の初期データ**。シーダーが使うだけで、画面は `Order::displayList()` など **DB経由** |
| `ShipmentPlan::demoRows()` / `ShipmentRegistrar::demoRows()` | 同上（出荷・出荷予定のシード用） |
| `yarnStockBase()` | `CostFoundationSeeder` だけが使う **初期在庫の種**。実運用の在庫は `yarn_stock_movements` |

これらをDBテーブルに移すと、「本番データ」と「シード用サンプル」が二重管理になり、かえって分かりにくくなります。  
やるべきことは **実行時に読まないこと** であって、別テーブル化ではありません。

---

## 3. マスタDB化が過剰と判断できるもの

| 対象 | 根拠 |
|------|------|
| `categories()`（生地・糸・製品の3種） | `memo/DB.md` にも **categories テーブルは無い**。種類が固定で、製品マスタの属性として足りるなら、専用テーブルは不要 |
| `METERS_PER_TAN_PRODUCT` などの定数 | 製品・生機マスタに `meters_per_tan` がある。**マスタ未登録時の安全な初期値** としてコード定数で十分 |

---

## 4. DB化より「別の直し方」が正しいもの

| 対象 | 根拠 |
|------|------|
| `MasterCatalog` のフォールバック（DB空→固定配列） | 固定配列を **もう一つのDBの代わり** にするのは本末転倒。最終目標なら「シード必須」にしてフォールバックを **消す** のが正しい |
| `DemoData::findProduct()` など固定マスタ参照 | 同上。DB化というより **`MasterCatalog` / Eloquent に統一** すべき |
| `OrderOverlay`（セッション） | 受注はすでにDB化済みで、実行時にはほぼ使われていない。**別DBテーブルにする必要はなく、削除候補** |
| `CURRENT_YM` / `today()` | ビジネスデータではなく **「デモ用の今日」**。本番では `now()` や設定ファイルでよく、DBテーブルは不要 |

---

## 5. デモ用途ならDB化を急がなくてよいもの（任意）

| 対象 | 根拠 |
|------|------|
| ダッシュボードの1〜5月の固定売上推移 | **見た目用のダミー**。6月だけ出荷DBから計算している。ポートフォリオのグラフ用なら固定でもよい |
| | ただし本番運用なら、過去月も `shipments` から集計する方が一貫する |

「DB化しない」と「固定のまま残す」は別問題で、**本番を目指すならここも出荷実績から計算に寄せた方がよい** です。

---

## 対比：DB化すべきと判断するもの（簡潔に）

逆に、次は **DB化が妥当** です。

| 対象 | なぜDB化すべきか |
|------|------------------|
| JSON（生機予想の手動調整） | 業務データがファイルに散らばり、複数人・本番運用に向かない |
| `stockMovements()` | 入荷・出荷と別ソースだと **在庫数と履歴が食い違う** |
| ダッシュボードの `lowStock`（固定 `stock` 列） | 実在庫は `product_rolls` にあるのに、古い固定値を見ている |

---

## まとめ（判断の軸）

DB化 **不要** と言えるのは、だいたい次の3つです。

1. **計算・ルール・設定**（データではない）
2. **シード専用の初期値**（実行時には読まない）
3. **固定で少ない区分**（専用テーブルが過剰）

一方、JSONや固定配列で **業務の事実**（在庫履歴、引当履歴、予想の提出版など）を持っている部分は、最終目標に沿ってDB化するのが自然です。



方針、次の整理で合っています。

---

## 5 → DB化する

**対象:** ダッシュボードの売上推移（1〜5月の固定値）

**やること:**
- `DemoData::dashboard()` 内の固定配列をやめる
- `shipments`（必要なら原価計算用にレシピ・単価も）から月別に集計する
- 6月と同様に `monthlySalesByProduct()` / `salesTrend()` のロジックを使えば、**1つのデータ源**に統一できる

**効果:** グラフと実際の出荷データが一致する

---

## 1 → コードのまま（計算・ルール・設定）

**対象:** `expectedArrivalDate()`、原価の計算式、`salesTrendMonths()`、ステータス判定、`QtyHelper` の換算など

**やること:**
- 新しいDBテーブルは作らない
- 参照する **マスタ・単価・受注・発注** だけDBから取る
- 計算ロジックはサービスクラスやヘルパーに残す

**注意:** `unitCostBreakdown()` 内の `findProduct()` など、まだ固定マスタを見ている部分は **4の対応**（`MasterCatalog` 統一）とセットで直す

---

## 2 → シード専用のまま（実行時には読まない）

**対象:** `baseOrderRows()`、`basePurchaseOrderRows()`、`demoRows()`、`yarnStockBase()` など

**やること:**
- シーダーの入力データとして `database/seeders/` 側に置く（または現状維持）
- 画面・APIから **直接呼ばない** ことを確認・整理する
- `DemoData` という名前のまま残すか、`Database/Seeders/Data/` などに移すかは好み（本質は「実行時取得経路に入れない」）

---

## 3 → 定数・列挙で十分（専用テーブルは作らない）

**対象:** `categories()`（生地・糸・製品）、`METERS_PER_TAN_*` のデフォルト値

**やること:**
- `categories` は PHP の定数・列挙型（enum）か、製品マスタの属性として扱う
- `meters_per_tan` は **製品・生機マスタの列を正** とし、定数は「マスタ未登録時のフォールバック」だけ残す
- `memo/DB.md` にも categories テーブルは無いので、この判断で問題なし

---

## 4 → 削除・置き換え（DBの二重管理をやめる）

**対象:** `MasterCatalog` フォールバック、`DemoData::findProduct()` 系、`OrderOverlay`、`CURRENT_YM` / `today()`

| 項目 | 対応 |
|------|------|
| `MasterCatalog` フォールバック | DB空時の固定配列を廃止。シード必須にする |
| `findProduct()` / `findGreige()` / `findMaterial()` | `MasterCatalog` または Eloquent に統一 |
| `OrderOverlay` | 未使用なら削除。使うなら受注のDB保存に寄せる |
| `CURRENT_YM` / `today()` | 本番は `now()`、デモ固定が必要なら `.env` や `config/app.php` に移す |

---

## この方針の外（別途DB化が必要なもの）

1〜4と5以外で、前回「DB化すべき」としたものはそのまま残ります。

- JSON系（生機予想の手動調整）
- `stockMovements()` 固定配列
- ダッシュボードの `lowStock`（固定 `stock` 列 → `product_rolls` 集計）

---

## 作業のおすすめ順

1. **4** … フォールバック廃止・`MasterCatalog` 統一（他の修正の土台）
2. **JSON・固定配列の業務データ** … `stockMovements`、生機予想の手動調整
3. **5** … ダッシュボード推移を出荷DB集計に
4. **1・2・3** … 整理・リネーム・定数化（大きな挙動変更は少ない）


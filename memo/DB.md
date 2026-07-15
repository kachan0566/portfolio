# DB 具体設計（実装用）

このファイルは `[memo/DBplan.md](DBplan.md)` の設計地図を、**マイグレーションにそのまま落とし込めるレベル**まで具体化したものです。


| ファイル        | 役割                           |
| ----------- | ---------------------------- |
| `DBplan.md` | なぜそのテーブルがあるか・全体のつながり・実装の優先順位 |
| `DB.md`（本書） | 列名・型・制約・インデックス・移行手順          |


テーブル定義は実装前の設計メモも含む。**実装するときは「実装段階とマイグレーション順」の本線（0→10）に沿う。** 拡張（8a・8b）は必要になったら本線8の後に追加する。

**テーブルのつながり（ER図）** … [`DBplan.md` の「全体のつながり（図一覧）」](DBplan.md#全体のつながり図一覧) を参照（物の流れ + 領域別5枚）。

---

## 共通ルール

### 型の約束


| 概念           | Laravel の型                         | 精度・備考                              |
| ------------ | ---------------------------------- | ---------------------------------- |
| 主キー          | `id()`                             | 自動採番                               |
| 人間向け番号       | `string` + `unique`                | 例：`SO-2606-001`                    |
| 糸の量（kg）      | `decimal(12, 3)`                   | 小数第3位まで                            |
| 反数（在庫・入荷）    | `decimal(8, 2)`                    | **0.25 刻み**（`QtyHelper::TAN_STEP`） |
| 反数（受注・発注）    | `unsignedInteger`                  | **整数反のみ**                          |
| 実測メートル（m）    | `decimal(12, 2)`                   | 反ごとの実測。請求・納品の根拠                    |
| 見積メートル（m）    | `unsignedInteger`                  | 標準長 × 反数などの換算値                     |
| 金額（円）        | `unsignedInteger` または `bigInteger` | 小数なし                               |
| 単価（円/kg・円/m） | `unsignedInteger`                  | 小数なし                               |
| ロス率          | `decimal(5, 4)`                    | 例：0.0300 = 3%                      |
| 年月           | `string(7)`                        | `'2026-06'` 形式                     |
| 区分・状態        | `string(32)`                       | PHP の定数と同じ文字列（後述）                  |
| 日付           | `date`                             |                                    |
| 論理削除（任意）     | `softDeletes()`                    | マスタのみ検討                            |


### 外部キーの方針

- 参照先が消えたら困る列 → `constrained()` + `restrictOnDelete()`（デフォルト）
- 子明細が親と一緒に消えてよい列 → `cascadeOnDelete()`
- 任意の紐づけ（生産意図など）→ `nullable()` + `nullOnDelete()`

### 在庫の持ち方（再確認）


| マスタ            | 在庫列      | 実際の在庫の出どころ                              |
| -------------- | -------- | --------------------------------------- |
| `products`     | **持たない** | `product_rolls`（`status = in_stock`）の合計 |
| `greiges`      | **持たない** | `greige_rolls`（在庫あり status）の合計          |
| `materials`（糸） | **持たない** | `yarn_stock_movements` の合計              |


---

## 定数値一覧（DB に保存する文字列）

アプリの PHP 定数と **同じ文字列** を DB に入れる。マイグレーションでは `enum` 型は使わず `string(32)` で統一（将来の追加が楽）。

### 発注種別 `purchase_orders.type`


| 値         | 意味   |
| --------- | ---- |
| `yarn`    | 糸発注  |
| `greige`  | 生機発注 |
| `product` | 製品発注 |


### 発注ステータス `purchase_orders.status`


| 値           | 意味    |
| ----------- | ----- |
| `draft`     | 下書き   |
| `ordered`   | 発注済   |
| `partial`   | 一部入荷  |
| `received`  | 入荷完了  |
| `cancelled` | キャンセル |


**画面表示との関係:** `status` は在庫・引当の判断用。一覧の **「工程」列** は `status` と種別ごとの進捗を **1ラベルにまとめた表示**（`PurchaseOrderDisplay::label()`）とする。DB に表示用列は持たない。

### 発注工程（種別ごと・画面表示）

旧 **8段階 `PO_STAGES`**（製品発注1件に全工程を載せるモデル）は廃止。糸・生機・製品の各発注が **自分の工程だけ** を持つ。

**アプリ側の定数・解決:** `PurchaseOrderStages`（工程一覧） / `PurchaseOrderDisplay`（表示ラベル） / `GreigeYarnReadiness`（生機の糸入荷済判定）

#### 表示ラベルの共通ルール


| 条件                         | 表示            |
| -------------------------- | ------------- |
| `status = cancelled`       | キャンセル         |
| `status = draft`           | 下書き           |
| `status = received`        | 入荷完了          |
| 進行中（`ordered` / `partial`） | **工程名**（下表）   |
| 進行中かつ分納                    | **工程名（一部入荷）** |


分納判定: `status = partial` または `received_qty < ordered_qty`（種別ごとの数量列で比較）。

入荷予定日の UI は **行ごと1入力のまま**（糸・生機＝`due_date`、製品＝`finish_date`）。工程別の予定日入力画面は作らない。

#### 糸発注（すべて自動。`stage` 列は持たない）


| 順   | 工程名       | 判定（概要）                           |
| --- | --------- | -------------------------------- |
| 1   | 糸発注済      | 織工場入荷 0                          |
| 2   | 糸出荷済      | 紡績出荷登録あり・入荷 0（将来。`shipped_at` 等） |
| 3   | 織工場への糸入荷済 | `received_qty_kg > 0`            |
| —   | 入荷完了      | `status = received`              |


#### 生機発注（糸入荷・生機出荷は自動、織編のみ手動）


| 順   | 工程名    | 手動/自動  | 判定（概要）                                          |
| --- | ------ | ------ | ----------------------------------------------- |
| 1   | 発注済    | 自動     | レシピ必要糸がまだすべて織工場入荷完了でない                          |
| 2   | 糸入荷済   | 自動     | 必要糸品番ごとに **糸発注がすべて入荷完了**（`GreigeYarnReadiness`） |
| 3   | 織編機投入済 | **手動** | `purchase_order_lines.stage` に保存                |
| 4   | 生機出荷済  | 自動     | 染工場への入荷開始（`received_qty_m > 0`）。手動工程より優先        |
| —   | 入荷完了   | 自動     | `status = received`                             |


#### 製品発注（染機投入のみ手動、以降は自動）


| 順   | 工程名   | 手動/自動  | 判定（概要）                                  |
| --- | ----- | ------ | --------------------------------------- |
| 1   | 染機投入済 | **手動** | `purchase_order_lines.stage`（未設定時もここ扱い） |
| 2   | 製品在庫中 | 自動     | 一部入荷あり（`partial` 等）                     |
| —   | 入荷完了  | 自動     | `status = received`                     |


`製品在庫中`・`生機出荷済` など **入荷から導出する工程は DB に保存しない**。

#### 受注詳細「生産状況」

`OrderProductionStatus::rowsForOrder()` で、受注の製品に関わる **糸 → 生機 → 製品** の進行中発注を並べる。

- 糸: 親生機レシピの `material_id` に一致する糸発注
- 生機: 製品の親 `greige_id` / SKU に一致する生機発注
- 製品: `purchase_orders.order_id` が当該受注

#### 発注一覧の列

- **出荷先** … 維持
- **状態** 列 → **工程** 列（上記表示ラベル）
- フィルタも工程ラベルベース（`PurchaseOrderDisplay::filterOptions()`）

#### 入荷予定日（`purchase_order_schedule_events` は作らない）

工程ごとの予定日を **別テーブルには持たない**。画面・在庫予想は次の列だけで足りる。


| 種別   | 保存先                                       | 一覧の「入荷予定日」 |
| ---- | ----------------------------------------- | ---------- |
| 糸・生機 | `purchase_orders.due_date`                | 同上         |
| 製品   | `purchase_order_lines.finish_date`（明細行ごと） | 同上         |


旧デモの `schedule` 連想配列（8段階キー）は **移行しない・削除**。

将来、工程別ガントや予定日編集が必要になったときだけ、テーブル追加または JSON 列を検討する。

### 受注数量モード `orders.order_qty_mode`


| 値        | 意味       |
| -------- | -------- |
| `tan`    | 反数受注（基本） |
| `meters` | メートル指定受注 |


### 引当種別 `order_allocations.allocation_type`


| 値       | 意味          |
| ------- | ----------- |
| `stock` | 現在庫から引当     |
| `po`    | 発注（未入荷）から引当 |


### 生機反ステータス `greige_rolls.status`


| 値                    | 意味        |
| -------------------- | --------- |
| `in_stock`           | 在庫あり      |
| `partially_consumed` | 染色で一部使用済み |
| `consumed`           | 使い切り      |


### 製品反ステータス `product_rolls.status`


| 値          | 意味   |
| ---------- | ---- |
| `in_stock` | 在庫あり |
| `shipped`  | 出荷済み |


### 仕入先種別 `suppliers.type`

`spinning` / `chemical` / `dye` / `weaving` / `dyeing`

### 納入先種別 `ship_tos.type`

`weaving` / `dyeing` / `warehouse`

### 原材料種別 `materials.type`

`yarn` / `dye` / `finishing`

### 糸入出庫 `yarn_stock_movements.movement_type`


| 値             | 意味       |
| ------------- | -------- |
| `receiving`   | 入荷       |
| `consumption` | 使用（織りなど） |
| `adjustment`  | 棚卸・調整    |


`reference_type` はポリモーフィック風に `receiving_line` / `purchase_order` / `manual` などを文字列で保持。入荷由来は `receiving_line` + `reference_id = receiving_lines.id`。

---

## テーブル定義

凡例：**状態** = 新規 / 既存・拡張 / 既存・廃止予定

---

### マスタ

#### `customers`（得意先）【新規】


| 列名           | 型           | NULL | デフォルト | 説明       |
| ------------ | ----------- | ---- | ----- | -------- |
| `id`         | bigint PK   | NO   | auto  |          |
| `name`       | string(100) | NO   |       | 会社名      |
| `contact`    | string(100) | YES  | null  | 担当者名     |
| `tel`        | string(30)  | YES  | null  | 電話番号     |
| `note`       | text        | YES  | null  | 備考       |
| `deleted_at` | timestamp   | YES  | null  | 論理削除（任意） |
| `created_at` | timestamp   | YES  |       |          |
| `updated_at` | timestamp   | YES  |       |          |


**インデックス:** `name`（検索用、任意）

**移行元:** `DemoData::customers()`

---

#### `suppliers`（仕入先）【新規】


| 列名           | 型           | NULL | デフォルト | 説明                |
| ------------ | ----------- | ---- | ----- | ----------------- |
| `id`         | bigint PK   | NO   | auto  |                   |
| `name`       | string(100) | NO   |       |                   |
| `contact`    | string(100) | YES  | null  |                   |
| `tel`        | string(30)  | YES  | null  |                   |
| `type`       | string(32)  | NO   |       | `SupplierType` の値 |
| `note`       | text        | YES  | null  |                   |
| `deleted_at` | timestamp   | YES  | null  |                   |
| `created_at` | timestamp   | YES  |       |                   |
| `updated_at` | timestamp   | YES  |       |                   |


**インデックス:** `type`

**移行元:** `DemoData::suppliers()`

---

#### `ship_tos`（納入先）【新規】


| 列名           | 型           | NULL | デフォルト | 説明              |
| ------------ | ----------- | ---- | ----- | --------------- |
| `id`         | bigint PK   | NO   | auto  |                 |
| `name`       | string(100) | NO   |       |                 |
| `type`       | string(32)  | NO   |       | `ShipToType` の値 |
| `note`       | text        | YES  | null  |                 |
| `deleted_at` | timestamp   | YES  | null  |                 |
| `created_at` | timestamp   | YES  |       |                 |
| `updated_at` | timestamp   | YES  |       |                 |


**インデックス:** `type`

**移行元:** `DemoData::shipTos()`

---

#### `greiges`（生機マスタ）【新規】


| 列名               | 型               | NULL | デフォルト | 説明                  |
| ---------------- | --------------- | ---- | ----- | ------------------- |
| `id`             | bigint PK       | NO   | auto  |                     |
| `sku`            | string(50)      | NO   |       | 品番（例：KB-A）          |
| `name`           | string(100)     | NO   |       | 表示名                 |
| `category`       | string(50)      | NO   |       | 例：生地                |
| `unit`           | string(10)      | NO   | `'反'` |                     |
| `meters_per_tan` | unsignedInteger | NO   | 100   | **標準長・見積用**（実測ではない） |
| `note`           | text            | YES  | null  |                     |
| `deleted_at`     | timestamp       | YES  | null  |                     |
| `created_at`     | timestamp       | YES  |       |                     |
| `updated_at`     | timestamp       | YES  |       |                     |


**ユニーク:** `sku`

**移行元:** `DemoData::greiges()`

---

#### `products`（製品マスタ）【既存・拡張】

**既存列（マイグレーション済み）**


| 列名                          | 型               | 備考           |
| --------------------------- | --------------- | ------------ |
| `id`                        | bigint PK       |              |
| `name`                      | string          | 表示名          |
| `sku`                       | string unique   | 品番           |
| `price`                     | unsignedInteger | 販売単価（円/m 想定） |
| `category`                  | string          |              |
| `unit`                      | string          | 例：反          |
| `created_at` / `updated_at` | timestamp       |              |


**追加する列**


| 列名               | 型               | NULL | デフォルト | 説明            |
| ---------------- | --------------- | ---- | ----- | ------------- |
| `greige_id`      | FK → `greiges`  | NO   |       | 親生機           |
| `color`          | string(50)      | YES  | null  | カラー名          |
| `meters_per_tan` | unsignedInteger | NO   | 50    | 標準長・見積用       |
| `stock_min_m`    | unsignedInteger | NO   | 0     | 最低在庫（m）。アラート用 |


**インデックス:** `greige_id`

**注意:** `stock` 列は **追加しない**。在庫は `product_rolls` から算出。

**移行元:** `DemoData::products()`（`greige_sku` → `greige_id` に変換）

---

#### `materials`（原材料マスタ）【既存・拡張】

**既存列:** `id`, `name`, `unit`, `timestamps`

**追加する列**


| 列名     | 型          | NULL | デフォルト | 説明                           |
| ------ | ---------- | ---- | ----- | ---------------------------- |
| `sku`  | string(50) | NO   |       | 品番（例：RM-001）                 |
| `type` | string(32) | NO   |       | `yarn` / `dye` / `finishing` |


**ユニーク:** `sku`

**移行元:** `DemoData::materials()`

---

### 取引

#### `orders`（受注）【新規】


| 列名                  | 型                | NULL | デフォルト   | 説明                       |
| ------------------- | ---------------- | ---- | ------- | ------------------------ |
| `id`                | bigint PK        | NO   | auto    |                          |
| `code`              | string(30)       | NO   |         | 受注番号                     |
| `customer_id`       | FK → `customers` | NO   |         | 得意先                      |
| `product_id`        | FK → `products`  | NO   |         | 製品品番                     |
| `order_qty_mode`    | string(16)       | NO   | `'tan'` | `tan` / `meters`         |
| `qty_tan`           | unsignedInteger  | NO   | 0       | 受注反数（整数）                 |
| `qty_meters`        | unsignedInteger  | NO   | 0       | 受注m（`meters` モード時はこちらが正） |
| `shipped_qty_tan`   | decimal(8,2)     | NO   | 0       | 出荷済み反数合計                 |
| `shipped_qty_m`     | unsignedInteger  | NO   | 0       | 出荷済み**実測m**合計            |
| `order_date`        | date             | NO   |         | 受注日                      |
| `due_date`          | date             | NO   |         | 納期                       |
| `planned_ship_date` | date             | YES  | null    | 出荷予定日                    |
| `ship_memo`         | text             | YES  | null    | 出荷メモ                     |
| `created_at`        | timestamp        | YES  |         |                          |
| `updated_at`        | timestamp        | YES  |         |                          |


**ユニーク:** `code`  
**インデックス:** `customer_id`, `product_id`, `order_date`, `due_date`

**完了判定（アプリ側ロジック）**

- `order_qty_mode = tan` → `shipped_qty_tan >= qty_tan`
- `order_qty_mode = meters` → `shipped_qty_m >= qty_meters`

**算出（DB列なし）:** `meters_overridden` は `Order::metersOverridden()` で算出（`meters` モード、または反数モードで標準換算mと不一致のとき `true`）。

**移行元:** `DemoData::orders()`（`customer` 文字列 → `customer_id`）

---

#### `purchase_orders`（発注・共通ヘッダ）【新規】


| 列名             | 型                | NULL | デフォルト       | 説明                            |
| -------------- | ---------------- | ---- | ----------- | ----------------------------- |
| `id`           | bigint PK        | NO   | auto        |                               |
| `code`         | string(30)       | NO   |             | 発注番号                          |
| `type`         | string(32)       | NO   |             | `yarn` / `greige` / `product` |
| `status`       | string(32)       | NO   | `'ordered'` |                               |
| `supplier_id`  | FK → `suppliers` | NO   |             | 仕入先                           |
| `ship_to_id`   | FK → `ship_tos`  | NO   |             | 納入先                           |
| `order_id`     | FK → `orders`    | YES  | null        | **生産意図**（引当とは別）               |
| `order_date`   | date             | NO   |             |                               |
| `due_date`     | date             | NO   |             | 納期・ETA                        |
| `arrival_memo` | text             | YES  | null        | 入荷・上がりメモ                      |
| `created_at`   | timestamp        | YES  |             |                               |
| `updated_at`   | timestamp        | YES  |             |                               |


**ユニーク:** `code`  
**インデックス:** `type`, `status`, `supplier_id`, `order_id`, `due_date`

**制約（アプリ）:** 親の `type` に応じて明細行の品目 FK が1つだけ NOT NULL。1発注に **複数明細行** 可（同一種別のみ。糸・生機・製品の混在はしない）。

**一覧の JOIN 方針**

```sql
SELECT po.*, pol.*
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
ORDER BY po.id, pol.line_no;
```

**移行元:** 旧 `yarn_` / `greige_` / `product_purchase_orders` を1行ずつ `line_no = 1` で統合。

---

#### `purchase_order_lines`（発注明細）【新規】


| 列名                  | 型                      | NULL | デフォルト   | 説明                               |
| ------------------- | ---------------------- | ---- | ------- | -------------------------------- |
| `id`                | bigint PK              | NO   | auto    | 入荷明細 `receiving_lines` の紐づけ先にも使う |
| `purchase_order_id` | FK → `purchase_orders` | NO   | cascade | 親発注                              |
| `line_no`           | unsignedSmallInteger   | NO   |         | 発注内の行番号（1始まり）                    |
| `material_id`       | FK → `materials`       | YES  | null    | 糸発注時                             |
| `greige_id`         | FK → `greiges`         | YES  | null    | 生機発注時                            |
| `product_id`        | FK → `products`        | YES  | null    | 製品発注時                            |
| `qty_kg`            | decimal(12,3)          | YES  | null    | 糸発注量                             |
| `received_qty_kg`   | decimal(12,3)          | YES  | null    | 糸入荷済み                            |
| `qty_tan`           | unsignedInteger        | YES  | null    | 発注反数（整数）                         |
| `meters_per_tan`    | unsignedInteger        | YES  | null    | 生機：発注時スナップショット                   |
| `qty_meters`        | unsignedInteger        | YES  | null    | 見積m                              |
| `received_qty_tan`  | decimal(8,2)           | YES  | null    | 入荷済み反数                           |
| `received_qty_m`    | unsignedInteger        | YES  | null    | 入荷済み実測m合計                        |
| `stage`             | string(50)             | YES  | null    | 生機・製品の手動工程（`織編機投入済` / `染機投入済`）   |
| `finish_date`       | date                   | YES  | null    | 製品：上がり予定日（一覧の入荷予定・在庫予想）          |
| `contact_date`      | date                   | YES  | null    | 製品：連絡日                           |
| `created_at`        | timestamp              | YES  |         |                                  |
| `updated_at`        | timestamp              | YES  |         |                                  |


**ユニーク:** `(purchase_order_id, line_no)`  
**インデックス:** `purchase_order_id`, `material_id`, `greige_id`, `product_id`

**制約（アプリ）:** 親 `purchase_orders.type` と整合。`yarn` なら `material_id` + `qty_kg` のみ使用、など。

**工程:** 糸は `stage` なし（入荷から自動計算）。生機・製品は旧子テーブルと同様 `PurchaseOrderDisplay` で表示。

**廃止:** `yarn_purchase_orders`, `greige_purchase_orders`, `product_purchase_orders`（2026-07 マイグレーションで `purchase_order_lines` に統合）

---

#### `order_allocations`（受注への引当）【新規】


| 列名                  | 型                      | NULL | デフォルト | 説明               |
| ------------------- | ---------------------- | ---- | ----- | ---------------- |
| `id`                | bigint PK              | NO   | auto  |                  |
| `order_id`          | FK → `orders`          | NO   |       | どの受注に            |
| `product_id`        | FK → `products`        | NO   |       | 製品品番             |
| `purchase_order_id` | FK → `purchase_orders` | YES  | null  | 発注引当時のみ          |
| `allocation_type`   | string(16)             | NO   |       | `stock` / `po`   |
| `qty_tan`           | decimal(8,2)           | NO   | 0     | 引当反数             |
| `qty_m`             | unsignedInteger        | NO   | 0     | 見積m（未入荷時は標準長換算可） |
| `created_at`        | timestamp              | YES  |       |                  |
| `updated_at`        | timestamp              | YES  |       |                  |


**インデックス:** `order_id`, `product_id`, `purchase_order_id`, `(order_id, allocation_type)`

**移行元:** `StockAllocation` JSON の `lines`

---

#### `receivings`（入荷ヘッダ）【新規】


| 列名              | 型          | NULL | デフォルト | 説明         |
| --------------- | ---------- | ---- | ----- | ---------- |
| `id`            | bigint PK  | NO   | auto  |            |
| `code`          | string(30) | NO   |       | 入荷番号（RC-…） |
| `received_date` | date       | NO   |       | 入荷日        |
| `note`          | text       | YES  | null  |            |
| `created_at`    | timestamp  | YES  |       |            |
| `updated_at`    | timestamp  | YES  |       |            |


**ユニーク:** `code`  
**インデックス:** `received_date`

**設計メモ:** 仕入先・品目は `receiving_lines` → `purchase_order_lines` → `purchase_orders` 経由で辿る。ヘッダに `purchase_order_id` / `supplier_id` は持たない（1回の入荷で複数発注明細行をまとめられるようにするため）。

**移行元:** `DemoData::receivings()` のヘッダ部分

---

#### `receiving_lines`（入荷明細）【新規】


| 列名                       | 型                           | NULL | デフォルト   | 説明                              |
| ------------------------ | --------------------------- | ---- | ------- | ------------------------------- |
| `id`                     | bigint PK                   | NO   | auto    |                                 |
| `receiving_id`           | FK → `receivings`           | NO   | cascade |                                 |
| `purchase_order_line_id` | FK → `purchase_order_lines` | NO   |         | どの発注明細に対する入荷か                   |
| `line_no`                | unsignedSmallInteger        | NO   |         | 入荷内の行番号（1始まり）                    |
| `qty_tan`                | decimal(8,2)                | NO   | 0       | 反数合計（表示用キャッシュ）                   |
| `qty_m`                  | unsignedInteger             | NO   | 0       | 実測m合計（表示用キャッシュ）                  |
| `qty_kg`                 | decimal(12,3)               | NO   | 0       | 糸kg合計（表示用キャッシュ。段階9まで直接セットも可）   |
| `created_at`             | timestamp                   | YES  |         |                                 |
| `updated_at`             | timestamp                   | YES  |         |                                 |


**ユニーク:** `(receiving_id, line_no)`  
**インデックス:** `purchase_order_line_id`

**制約（アプリ）:**

- **在庫の正**は `greige_rolls` / `product_rolls` / `yarn_stock_movements`（`reference_type = receiving_line`）
- `qty_*` は **手入力しない**。入荷登録・反明細更新時に `ReceivingLineTotals::sync()` で下位テーブルから自動同期する **表示用キャッシュ**
- 種別（糸/生機/製品）は紐づく `purchase_order_lines` と親 `purchase_orders.type` から導出する（`line_type` 列は持たない）
- 1入荷に **複数行** 可（同一種別の複数品番・複数発注明細行）。糸と製品の混在はしない

**移行元:** 入荷一覧の品目サマリ。反ごとの実測は `greige_rolls` / `product_rolls` へ。

---

#### `receiving_roll_amendments`（入荷反明細の変更履歴）【段階8b】

反明細（`greige_rolls` / `product_rolls`）修正時に、**どの反が変わったか**と**変更前の入荷明細合計**を残す。


| 列名                   | 型                      | NULL | 説明                                       |
| -------------------- | ---------------------- | ---- | ---------------------------------------- |
| `id`                 | bigint PK              | NO   |                                          |
| `receiving_line_id`  | FK → `receiving_lines` | NO   | どの入荷明細行か                                 |
| `roll_type`          | string(16)             | NO   | `greige_roll` / `product_roll`           |
| `roll_id`            | unsignedBigInteger     | NO   | 対象反の id                                  |
| `roll_code`          | string(30)             | NO   | 反番号（スナップショット）                            |
| `field`              | string(32)             | NO   | `tan_qty` / `actual_qty_m` など            |
| `old_value`          | decimal(12,3)          | NO   | 変更前（その反の値）                               |
| `new_value`          | decimal(12,3)          | NO   | 変更後（その反の値）                               |
| `line_qty_tan_before` | decimal(8,2)         | NO   | 変更前の入荷明細合計（反数）                           |
| `line_qty_m_before`  | unsignedInteger        | NO   | 変更前の入荷明細合計（実測m）                         |
| `line_qty_tan_after` | decimal(8,2)           | YES  | 変更後合計（照合用・任意）                            |
| `line_qty_m_after`   | unsignedInteger        | YES  | 変更後合計（照合用・任意）                            |
| `reason`             | text                   | YES  | 修正理由                                     |
| `changed_at`         | timestamp              | NO   |                                          |
| `created_at`         | timestamp              | YES  |                                          |
| `updated_at`         | timestamp              | YES  |                                          |


**運用:** 合計・明細画面は常に **最新**（`receiving_lines.qty_*` + rolls）。履歴画面で amendments を表示。反修正 UI 実装時にマイグレーション追加。

### 複数明細行 UI（段階8a・設計メモ）

DB は複数行対応済み。UI 拡張時の方針：

| 画面 | 現状（段階6） | 段階8a |
| --- | --- | --- |
| 入荷登録 | 発注1件 → `receiving_lines` 1行 | 発注明細行を複数選択 → `line_no` ごとに行作成 |
| 入荷一覧 | 1入荷1行（`lines->first()`） | 明細行ごとに1行、またはヘッダ＋ネスト表示 |
| 合計表示 | `receiving_lines.qty_*` | 行ごとの `qty_*`（B案のまま） |

**制約:** 1入荷に紐づく明細はすべて同一 `purchase_orders.type`。登録時にアプリで検証する。

---

#### `shipments`（出荷実績）【新規】


| 列名             | 型               | NULL | デフォルト | 説明              |
| -------------- | --------------- | ---- | ----- | --------------- |
| `id`           | bigint PK       | NO   | auto  |                 |
| `code`         | string(30)      | NO   |       | 出荷番号（SH-…）      |
| `order_id`     | FK → `orders`   | NO   |       |                 |
| `product_id`   | FK → `products` | NO   |       |                 |
| `qty_tan`      | decimal(8,2)    | NO   | 0     | 出荷反数合計          |
| `qty_m`        | unsignedInteger | NO   | 0     | 出荷**実測m**合計     |
| `shipped_date` | date            | NO   |       |                 |
| `ship_to_name` | string(200)     | YES  | null  | 届け先名称（スナップショット） |
| `note`         | text            | YES  | null  |                 |
| `created_at`   | timestamp       | YES  |       |                 |
| `updated_at`   | timestamp       | YES  |       |                 |


**ユニーク:** `code`  
**インデックス:** `order_id`, `product_id`, `shipped_date`

**移行元:** `DemoData::shipments()`

---

### 在庫・反明細

#### `greige_rolls`（生機の物理反）【新規】


| 列名                  | 型                      | NULL | デフォルト        | 説明               |
| ------------------- | ---------------------- | ---- | ------------ | ---------------- |
| `id`                | bigint PK              | NO   | auto         |                  |
| `code`              | string(30)             | NO   |              | 反番号（GR-…）        |
| `greige_id`         | FK → `greiges`         | NO   |              |                  |
| `purchase_order_id` | FK → `purchase_orders` | YES  | null         | 由来発注（照会・引当のため保持） |
| `receiving_line_id` | FK → `receiving_lines` | NO   |              | 入荷明細行            |
| `tan_qty`           | decimal(8,2)           | NO   | 1.00         | 反数（0.25刻み可）      |
| `actual_qty_m`      | decimal(12,2)          | NO   | 0            | **織り実測m**        |
| `nominal_meters`    | unsignedInteger        | YES  | null         | 参考：標準長（表示用）      |
| `status`            | string(32)             | NO   | `'in_stock'` |                  |
| `received_date`     | date                   | NO   |              | FIFO 用           |
| `created_at`        | timestamp              | YES  |              |                  |
| `updated_at`        | timestamp              | YES  |              |                  |


**ユニーク:** `code`  
**インデックス:** `greige_id`, `status`, `(greige_id, status, received_date)`（在庫一覧・FIFO）

**部分消費:** 染色で半分使ったときは `tan_qty` を減らすか、残りを新行に分割（実装時にどちらかを決定）。

**移行元:** `GreigeRoll` JSON / `FabricTanRoll`（生機段階）

---

#### `product_rolls`（製品の物理反）【新規】


| 列名                      | 型                      | NULL | デフォルト        | 説明        |
| ----------------------- | ---------------------- | ---- | ------------ | --------- |
| `id`                    | bigint PK              | NO   | auto         |           |
| `code`                  | string(30)             | NO   |              | 反番号（PR-…） |
| `product_id`            | FK → `products`        | NO   |              |           |
| `purchase_order_id`     | FK → `purchase_orders` | YES  | null         |           |
| `receiving_line_id`     | FK → `receiving_lines` | NO   |              | 入荷明細行     |
| `parent_greige_roll_id` | FK → `greige_rolls`    | YES  | null         | 染色元の生機反   |
| `tan_qty`               | decimal(8,2)           | NO   | 1.00         | 0.25刻み可   |
| `actual_qty_m`          | decimal(12,2)          | NO   | 0            | **染め実測m** |
| `nominal_meters`        | unsignedInteger        | YES  | null         | 参考：標準長    |
| `status`                | string(32)             | NO   | `'in_stock'` |           |
| `received_date`         | date                   | NO   |              | FIFO 用    |
| `created_at`            | timestamp              | YES  |              |           |
| `updated_at`            | timestamp              | YES  |              |           |


**ユニーク:** `code`  
**インデックス:** `product_id`, `status`, `(product_id, status, received_date)`

**在庫合計クエリ（イメージ）**

```sql
SELECT product_id,
       SUM(tan_qty) AS stock_tan,
       SUM(actual_qty_m) AS stock_m
FROM product_rolls
WHERE status = 'in_stock'
GROUP BY product_id;
```

**移行元:** `ProductRoll` JSON / 旧 `inbound_lots`（段階8で置き換え）

---

#### `shipment_plans`（出荷予定）【既存・拡張】

**既存列**


| 列名                  | 型                     | 備考                      |
| ------------------- | --------------------- | ----------------------- |
| `id`                | bigint PK             |                         |
| `code`              | string unique         |                         |
| `order_id`          | unsignedBigInteger    | **FK 制約を追加** → `orders` |
| `product_id`        | FK → `products`       |                         |
| `planned_ship_date` | date                  |                         |
| `confirmed_qty_m`   | decimal(12,2)         |                         |
| `shipped_qty_m`     | decimal(12,2)         | default 0               |
| `status`            | string(32)            | default `confirmed`     |
| `note`              | text                  |                         |
| `created_by`        | FK → `users` nullable |                         |
| `timestamps`        |                       |                         |


**追加する列**


| 列名                  | 型            | NULL | デフォルト | 説明     |
| ------------------- | ------------ | ---- | ----- | ------ |
| `confirmed_qty_tan` | decimal(8,2) | NO   | 0     | 確定反数   |
| `shipped_qty_tan`   | decimal(8,2) | NO   | 0     | 出荷済み反数 |


**変更:** `order_id` に `foreignId()->constrained('orders')` を付与（既存データ移行後）

---

#### `shipment_roll_allocations`（出荷時の反消費）【新規】


| 列名                 | 型                    | NULL | デフォルト    | 説明                    |
| ------------------ | -------------------- | ---- | -------- | --------------------- |
| `id`               | bigint PK            | NO   | auto     |                       |
| `shipment_id`      | FK → `shipments`     | NO   | cascade  |                       |
| `product_roll_id`  | FK → `product_rolls` | NO   | restrict | 丸ごと出荷した反              |
| `consumed_tan_qty` | decimal(8,2)         | NO   |          | = その反の `tan_qty`      |
| `consumed_qty_m`   | decimal(12,2)        | NO   |          | = その反の `actual_qty_m` |
| `note`             | text                 | YES  | null     |                       |
| `created_at`       | timestamp            | YES  |          |                       |
| `updated_at`       | timestamp            | YES  |          |                       |


**ユニーク:** `product_roll_id`（1反は1回しか出荷できない）  
**インデックス:** `shipment_id`

**移行元:** `ShipmentRollAllocation` JSON / 旧 `shipment_lot_consumptions`

---

#### `yarn_stock_movements`（糸入出庫履歴）【新規】


| 列名               | 型                  | NULL | デフォルト | 説明                                         |
| ---------------- | ------------------ | ---- | ----- | ------------------------------------------ |
| `id`             | bigint PK          | NO   | auto  |                                            |
| `material_id`    | FK → `materials`   | NO   |       | 糸のみ                                        |
| `movement_type`  | string(32)         | NO   |       | `receiving` / `consumption` / `adjustment` |
| `qty_kg`         | decimal(12,3)      | NO   |       | 正=入庫、負=出庫でも可（どちらかに統一）                      |
| `reference_type` | string(32)         | YES  | null  | 参照元の種類                                     |
| `reference_id`   | unsignedBigInteger | YES  | null  | 参照元 id                                     |
| `movement_date`  | date               | NO   |       |                                            |
| `note`           | text               | YES  | null  |                                            |
| `created_at`     | timestamp          | YES  |       |                                            |
| `updated_at`     | timestamp          | YES  |       |                                            |


**インデックス:** `material_id`, `movement_date`, `(reference_type, reference_id)`

**残高:** `SUM(qty_kg) WHERE material_id = ?`（入庫正・出庫負で統一する場合）

**移行元:** `DemoData::yarnStockBase()` を初期 `adjustment` 行として投入

---

### 原価・レシピ

#### `product_recipes`（製品レシピ＝染色加工料）【新規】


| 列名                | 型               | NULL | デフォルト  | 説明      |
| ----------------- | --------------- | ---- | ------ | ------- |
| `id`              | bigint PK       | NO   | auto   |         |
| `product_id`      | FK → `products` | NO   | unique | 1製品1レシピ |
| `processing_cost` | unsignedInteger | NO   | 0      | 円/m     |
| `created_at`      | timestamp       | YES  |        |         |
| `updated_at`      | timestamp       | YES  |        |         |


**移行元:** `DemoData::recipeData()`

---

#### `greige_recipes`（生機レシピ・ヘッダ）【新規】


| 列名             | 型               | NULL | デフォルト  | 説明      |
| -------------- | --------------- | ---- | ------ | ------- |
| `id`           | bigint PK       | NO   | auto   |         |
| `greige_id`    | FK → `greiges`  | NO   | unique | 1生機1レシピ |
| `loss_rate`    | decimal(5,4)    | NO   | 0      | ロス率     |
| `weaving_cost` | unsignedInteger | NO   | 0      | 円/m     |
| `created_at`   | timestamp       | YES  |        |         |
| `updated_at`   | timestamp       | YES  |        |         |


---

#### `greige_recipe_lines`（生機レシピ明細）【新規】


| 列名                 | 型                     | NULL | デフォルト   | 説明   |
| ------------------ | --------------------- | ---- | ------- | ---- |
| `id`               | bigint PK             | NO   | auto    |      |
| `greige_recipe_id` | FK → `greige_recipes` | NO   | cascade |      |
| `material_id`      | FK → `materials`      | NO   |         | 糸    |
| `qty_per_m`        | decimal(10,4)         | NO   |         | kg/m |
| `created_at`       | timestamp             | YES  |         |      |
| `updated_at`       | timestamp             | YES  |         |      |


**ユニーク:** `(greige_recipe_id, material_id)`

**移行元:** `DemoData::greigeRecipeData()` の `lines`

---

#### `material_prices`（糸の月次単価）【新規】


| 列名            | 型                | NULL | デフォルト | 説明          |
| ------------- | ---------------- | ---- | ----- | ----------- |
| `id`          | bigint PK        | NO   | auto  |             |
| `material_id` | FK → `materials` | NO   |       | 糸のみ         |
| `ym`          | string(7)        | NO   |       | `'2026-06'` |
| `unit_price`  | unsignedInteger  | NO   |       | 円/kg        |
| `created_at`  | timestamp        | YES  |       |             |
| `updated_at`  | timestamp        | YES  |       |             |


**ユニーク:** `(material_id, ym)`

**移行元:** `DemoData::materialPrices()`

---

### 予測

#### `forecast_manual_adjustments`【既存】

マイグレーション済み。変更不要。


| 列名                 | 型             |
| ------------------ | ------------- |
| `id`               | bigint PK     |
| `product_id`       | FK            |
| `target_ym`        | string(7)     |
| `adjustment_qty_m` | decimal(12,2) |
| `direction`        | string(16)    |
| `reason`           | text          |
| `created_by_name`  | string        |
| `timestamps`       |               |


---

#### `month_end_forecasts`【既存】

マイグレーション済み。変更不要。

---

#### `month_end_forecast_lines`【既存】

マイグレーション済み。変更不要。

---

#### `sales_forecasts`（売上見通し・提出版ヘッダ）【新規】


| 列名                  | 型               | NULL | デフォルト         | 説明    |
| ------------------- | --------------- | ---- | ------------- | ----- |
| `id`                | bigint PK       | NO   | auto          |       |
| `target_ym`         | string(7)       | NO   |               | 対象月   |
| `base_date`         | date            | NO   |               | 算出基準日 |
| `version`           | unsignedInteger | NO   | 1             | 版番号   |
| `created_by_name`   | string(100)     | NO   |               | 提出者名  |
| `submitted_at`      | timestamp       | NO   |               |       |
| `submission_status` | string(32)      | NO   | `'submitted'` |       |
| `total_sales`       | bigInteger      | NO   | 0             | 円     |
| `total_qty`         | decimal(12,2)   | NO   | 0             | 数量合計  |
| `total_profit`      | bigInteger      | NO   | 0             | 円     |
| `created_at`        | timestamp       | YES  |               |       |
| `updated_at`        | timestamp       | YES  |               |       |


**ユニーク:** `(target_ym, version)`

**移行元:** `SalesForecastSnapshot` JSON のヘッダ部分

---

#### `sales_forecast_lines`（売上見通し明細）【新規】


| 列名                  | 型                      | NULL | デフォルト   | 説明                         |
| ------------------- | ---------------------- | ---- | ------- | -------------------------- |
| `id`                | bigint PK              | NO   | auto    |                            |
| `sales_forecast_id` | FK → `sales_forecasts` | NO   | cascade |                            |
| `product_id`        | FK → `products`        | NO   |         |                            |
| `source_type`       | string(32)             | NO   |         | `order` / `purchase_order` |
| `source_id`         | unsignedBigInteger     | NO   |         | 受注 or 発注 id                |
| `forecast_qty_m`    | decimal(12,2)          | NO   | 0       | 見通しm                       |
| `forecast_sales`    | unsignedInteger        | NO   | 0       | 見通し売上                      |
| `forecast_profit`   | integer                | NO   | 0       | 見通し粗利                      |
| `note`              | text                   | YES  | null    |                            |
| `created_at`        | timestamp              | YES  |         |                            |
| `updated_at`        | timestamp              | YES  |         |                            |


**インデックス:** `sales_forecast_id`, `(product_id, source_type, source_id)`

**移行元:** `SalesForecastLine` JSON + スナップショット内 `lines`

---

### 認証

#### `users`【既存】

Laravel 標準。変更不要。

---

### 廃止予定

#### `inbound_lots`【既存・廃止予定】

m 単位のかたまり在庫。→ `product_rolls` へ移行後にテーブル削除。

#### `shipment_lot_consumptions`【既存・廃止予定】

部分消費前提。→ `shipment_roll_allocations` へ移行後に削除。

---

## 実装段階とマイグレーション順

`DBplan.md` の段階に対応する。**1段階 = マイグレーション（1〜2本）→ Seeder → アプリ → 完了条件チェック** が目安。

**進め方**

- **本線（0→10）** は番号順にのみ進む。飛ばさない。
- **拡張（8a・8b）** は本線8の後・本線9の前。省略可（省略時は 8→9 へ）。
- 拡張の前倒し: 出荷前に反実測の訂正が必要 → **8b** を8より前へ。日常で1入荷に複数品目が必須 → **8a** を8より前へ。

### 段階0（既存資産）

Laravel 初期・在庫予測導入時に作成済み。本線1から触る前に把握しておく。


| テーブル | 作成元 | 本線での扱い |
| --- | --- | --- |
| `products`, `materials`, `users` | Laravel 初期 | 段階2で拡張（products/materials） |
| `inbound_lots`, `shipment_plans`, `shipment_lot_consumptions` | `2026_06_29_*_create_inventory_forecast_tables` | 段階7〜8で整理・置き換え |
| `forecast_manual_adjustments`, `month_end_forecasts`, `month_end_forecast_lines` | 同上 | 既存維持。段階10は `sales_forecasts` 系を追加 |


### 本線ロードマップ


| 段階 | マイグレーション | アプリ作業 | Seeder | 依存 | 状態 |
| --- | --- | --- | --- | --- | --- |
| 0 | （上表・既存） | 現状維持 | — | Laravel 初期 | **済** |
| 1 | `customers`, `suppliers`, `ship_tos`, `greiges` | マスタ画面を DB 優先に | `MasterFoundationSeeder` | 0 | **済 2026-07** |
| 2 | `products`, `materials` に列追加 | 同上 | `MasterCatalogSeeder` | 1 | **済 2026-07** |
| 3 | `orders` | 受注 CRUD を DB 化 | `OrderSeeder` | 1・2 | **済 2026-07** |
| 4 | `purchase_orders` + `purchase_order_lines` | 発注画面 DB 化 | `PurchaseOrderSeeder` | 1〜3 | **済 2026-07** |
| 5 | `order_allocations` | `StockAllocation` JSON 廃止 | `OrderAllocationSeeder` | 3・4 | **済 2026-07** |
| 6 | `receivings`, `receiving_lines`（**`qty_*` 初回から**）, `greige_rolls`, `product_rolls` | `ReceivingRegistrar`, `ReceivingLineTotals::sync`, `PurchaseOrderLineReceiver`, 発注残 DB 優先, 入荷一覧（1明細運用） | `ReceivingSeeder` | 4 | **済 2026-07**（qty 列は追補マイグレあり・下表） |
| 7 | `shipment_plans`：`order_id` FK + `confirmed_qty_tan` / `shipped_qty_tan` | 出荷予定を DB 正に | 既存データ移行 | 3 | **未** |
| 8 | `shipments`, `shipment_roll_allocations` | 出荷登録 DB 化、`inbound_lots` / `shipment_lot_consumptions` / 関連 JSON 廃止 | デモ移行 | 3・6・**7** | **未** |
| 9 | レシピ3 + `material_prices` + `yarn_stock_movements` | 原価画面、糸入荷を movements に接続 | 原価系 | 1・2・6 | **未** |
| 10 | `sales_forecasts`, `sales_forecast_lines` | 売上見通し JSON 廃止 | 見通し移行 | 2・3 | **未** |


### 拡張ロードマップ（本線8の後・本線9の前。スキップ可）


| 段階 | 内容 | 依存 | デフォルト位置 |
| --- | --- | --- | --- |
| 8a | 入荷・発注の複数明細行 UI（同一種別のみ） | 6 | 本線8の直後 |
| 8b | `receiving_roll_amendments` + 反明細修正 UI | 6・`ReceivingLineTotals` | 8a の直後（8a 省略時は8の直後） |


**8b の注意:** マイグレーション単体禁止。反明細修正 UI と同じタイミングで実装する。

### 旧番号との対応（移行用）


| 旧 | 新 |
| --- | --- |
| 6b | 6（アプリ作業の一部） |
| 7b | 7 |
| 7（出荷実績） | 8 |
| 6d | 8a |
| 6c | 8b |
| 8（糸・原価） | 9 |
| 9（見通し） | 10 |


### 段階1のマイグレーション例（学習用）

```php
// database/migrations/xxxx_create_master_foundation_tables.php

Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('contact', 100)->nullable();
    $table->string('tel', 30)->nullable();
    $table->text('note')->nullable();
    $table->softDeletes();
    $table->timestamps();
});

Schema::create('greiges', function (Blueprint $table) {
    $table->id();
    $table->string('sku', 50)->unique();
    $table->string('name', 100);
    $table->string('category', 50);
    $table->string('unit', 10)->default('反');
    $table->unsignedInteger('meters_per_tan')->default(100);
    $table->text('note')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

### 段階2の拡張例

```php
// database/migrations/xxxx_add_greige_fields_to_products_table.php

Schema::table('products', function (Blueprint $table) {
    $table->foreignId('greige_id')->after('id')->constrained();
    $table->string('color', 50)->nullable()->after('sku');
    $table->unsignedInteger('meters_per_tan')->default(50)->after('unit');
    $table->unsignedInteger('stock_min_m')->default(0)->after('meters_per_tan');
});
```

### 段階6のマイグレーション例（学習用・理想形）

`qty_*` は `receiving_lines` 作成時から含める（既存環境は `2026_07_11_*` で追補済み。巻き直さなくてよい）。

```php
Schema::create('receiving_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('receiving_id')->constrained()->cascadeOnDelete();
    $table->foreignId('purchase_order_line_id')->constrained();
    $table->unsignedSmallInteger('line_no');
    $table->decimal('qty_tan', 8, 2)->default(0);
    $table->unsignedInteger('qty_m')->default(0);
    $table->decimal('qty_kg', 12, 3)->default(0);
    $table->timestamps();
    $table->unique(['receiving_id', 'line_no']);
});
```

---

## マイグレーションファイル対応表

理想段階と、現リポジトリのファイルの対応。理想と異なる分割は **ドキュメント注記のみ**（既存マイグレは巻き直さない）。


| マイグレーションファイル | 理想段階 | 備考 |
| --- | --- | --- |
| `2026_06_09_*_create_products_table` | 0 | Laravel 初期 |
| `2026_06_14_*_create_materials_table` | 0 | 同上 |
| `2026_06_29_*_create_inventory_forecast_tables` | 0 | `inbound_lots`, `shipment_plans` 等 |
| `2026_07_07_*_create_master_foundation_tables` | 1 | |
| `2026_07_08_000001_*_catalog` | 2 | |
| `2026_07_08_000002_*_orders` | 3 | |
| `2026_07_08_000004_*_purchase_orders` | 4 | |
| `2026_07_08_000006_*_purchase_order_lines` | 4 | 理想は4で1ファイルに統合可 |
| `2026_07_08_000005_*_order_allocations` | 5 | |
| `2026_07_08_000007_*_receiving_and_roll` | 6 | |
| `2026_07_11_*_add_qty_columns_to_receiving_lines` | 6 | 理想は 000007 に含む |
| （未作成）`shipment_plans` 拡張 | 7 | FK + 反数列 |
| （未作成）`shipments` 系 | 8 | |

各段階の Seeder はその段階のマイグレ直後に `DatabaseSeeder` へ `call` を追加する。取引系（3以降）は段階ごとに `php artisan migrate:fresh --seed` で通し確認する。

---

## 段階ごとの完了条件

共通: マイグレーション up/down 確認、Seeder でデモ再現、該当画面が DB 優先、Feature テスト 1本以上、移行チェックリスト該当行を [x]。

### 段階6 完了条件（段階7 着手前）

- [x] `receiving_lines` に `qty_tan` / `qty_m` / `qty_kg`（B案キャッシュ）
- [x] `ReceivingLineTotals::sync` + `PurchaseOrderLineReceiver`（発注明細 `received_qty_*` 同期）
- [x] `ReceivingRegistrar` による入荷登録 DB 化（1明細・現 UI）
- [x] `DemoState::effectiveReceivedQty` / `poRemaining` が DB 優先
- [x] 入荷一覧が `receiving_lines.qty_*` を表示

### 段階7 完了条件（段階8 着手前）

- [ ] `shipment_plans.order_id` に FK 制約
- [ ] `confirmed_qty_tan` / `shipped_qty_tan` 列あり
- [ ] 出荷予定画面が DB 優先

### 段階8 完了条件（段階9 着手前）

- [ ] `shipments` + `shipment_roll_allocations` 作成
- [ ] 出荷登録が DB 化
- [ ] `inbound_lots` / `shipment_lot_consumptions` 参照廃止

### 段階9 完了条件（段階10 着手前）

- [ ] レシピ3 + `material_prices` + `yarn_stock_movements`
- [ ] 糸入荷が `yarn_stock_movements` に記録（`qty_kg` 暫定から脱却）

### 段階8a 完了条件（任意）

- [ ] 1入荷に複数 `receiving_lines` 登録可
- [ ] 発注・入荷・一覧が明細行単位表示

### 段階8b 完了条件（任意）

- [ ] 反明細修正 UI あり
- [ ] 修正時に `receiving_roll_amendments` へ履歴、合計は `qty_*` 再同期

---

## よくある疑問（壁打ち用）

### Q. `purchase_orders.order_id` と `order_allocations` の違いは？

- `order_id` … 「この発注はどの受注のためか」という**意図・メモ**（1発注1受注が多いが必須ではない）
- `order_allocations` … 「何反／何m をどの受注に充てるか」という**数量の引当**（複数行・在庫/発注の両方）

### Q. なぜ `greige_rolls` と `product_rolls` を分ける？

現場が違う（織り場 vs 染工場・倉庫）、消費ルールが違う（半分カット可 vs 丸ごと出荷のみ）。`DBplan.md` の繊維業務ルール表を参照。

### Q. `qty_meters`（整数）と `actual_qty_m`（decimal）の違いは？

- `qty_meters` … 発注・受注時の**見積**（標準長 × 反数など）
- `actual_qty_m` … 入荷後に現場が測った**実測**（請求・在庫の正）

### Q. 最初に壁打ちするならどのテーブル？

`customers` + `orders`。マスタと取引の分離がこのアプリの設計の芯。

### Q. 入荷の複数品目はどう扱う？

- **DB**は対応済み：`receivings` 1件に `receiving_lines` 複数行（`(receiving_id, line_no)` ユニーク）
- **アプリ（段階6）**は 1入荷1明細行（`line_no = 1`）で運用。一覧も `lines->first()` 表示
- **段階8a**で入荷登録・一覧を明細行単位に拡張。発注の複数明細行 UI とセット
- **非対応:** 1入荷に糸＋製品など **種別の混在**（1発注＝同一 `purchase_orders.type` のみ）

### Q. `receiving_lines.qty_*` と rolls の関係は？

- rolls / movements が **在庫の正**
- `qty_*` は **一覧・明細用キャッシュ**（`ReceivingLineTotals::sync` で自動更新。手入力しない）
- 反明細修正時は **最新を表示**し、変更内容は `receiving_roll_amendments` に履歴として残す（段階8b）

### Q. 8a/8b を飛ばして段階9に進んでよい？

よい。本線は 8→9。8a/8b は運用が必要になったら戻って実装する。

---

## デモデータ移行チェックリスト

実装時に1行ずつ確認する。


| 段階 | 移行元 | 移行先 | 状態 |
| --- | --- | --- | --- |
| 1 | `DemoData::customers()` | `customers` | [ ] |
| 1 | `DemoData::suppliers()` | `suppliers` | [ ] |
| 1 | `DemoData::shipTos()` | `ship_tos` | [ ] |
| 1 | `DemoData::greiges()` | `greiges` | [ ] |
| 2 | `DemoData::products()` | `products`（`greige_id` 変換） | [ ] |
| 2 | `DemoData::materials()` | `materials` | [ ] |
| 3 | `DemoData::orders()` | `orders` | [x] |
| 4 | `DemoData::purchaseOrders()` | `purchase_orders` + `purchase_order_lines` | [x] |
| 5 | `StockAllocation` JSON | `order_allocations` | [x] |
| 6 | `DemoData::receivings()` | `receivings` + `receiving_lines` + 反明細 | [x] |
| 6 | `GreigeRoll` / `ProductRoll` JSON | `greige_rolls` / `product_rolls` | [x] |
| 8 | `DemoData::shipments()` | `shipments` + `shipment_roll_allocations` | [ ] |
| 8 | `inbound_lots` | `product_rolls`（移行後廃止） | [ ] |
| 9 | レシピ・単価・糸在庫 | 原価系テーブル | [ ] |
| 10 | 売上見通し JSON | `sales_forecasts` 系 | [ ] |

---

*最終更新：* 段階番号を本線0〜10・拡張8a/8bに整理。段階6まで実装済み（2026-07）。次は段階7（`shipment_plans` FK + 反数列）。
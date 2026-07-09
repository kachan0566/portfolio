# DB 具体設計（実装用）

このファイルは `[memo/DBplan.md](DBplan.md)` の設計地図を、**マイグレーションにそのまま落とし込めるレベル**まで具体化したものです。


| ファイル        | 役割                           |
| ----------- | ---------------------------- |
| `DBplan.md` | なぜそのテーブルがあるか・全体のつながり・実装の優先順位 |
| `DB.md`（本書） | 列名・型・制約・インデックス・移行手順          |


**まだマイグレーションは書かない。** 壁打ちと学習用。実装するときは段階（後述）に沿って進める。

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


`reference_type` はポリモーフィック風に `receiving` / `purchase_order` / `manual` などを文字列で保持。

### 入荷明細種別 `receiving_lines.line_type`

`yarn` / `greige` / `product`（発注種別と揃える）

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

**制約（アプリ or DB）:** `type` と子テーブルは 1対1。`type = yarn` なら `yarn_purchase_orders` が必ず1行。

**一覧の JOIN 方針**

```sql
-- イメージ（3種を UNION せず親＋LEFT JOIN）
SELECT po.*, ypo.*, gpo.*, ppo.*
FROM purchase_orders po
LEFT JOIN yarn_purchase_orders ypo ON ypo.purchase_order_id = po.id
LEFT JOIN greige_purchase_orders gpo ON gpo.purchase_order_id = po.id
LEFT JOIN product_purchase_orders ppo ON ppo.purchase_order_id = po.id
```

**移行元:** `DemoData::purchaseOrders()` の共通部分 + `PurchaseOrderLink` JSON の `order_id`

---



#### `yarn_purchase_orders`（糸発注・種別詳細）【新規】


| 列名                  | 型                         | NULL | デフォルト | 説明    |
| ------------------- | ------------------------- | ---- | ----- | ----- |
| `purchase_order_id` | FK → `purchase_orders` PK | NO   |       | 親と1対1 |
| `material_id`       | FK → `materials`          | NO   |       | 糸品番   |
| `qty_kg`            | decimal(12,3)             | NO   | 0     | 発注量   |
| `received_qty_kg`   | decimal(12,3)             | NO   | 0     | 入荷済み量 |


**主キー:** `purchase_order_id`（= 親 id）

---



#### `greige_purchase_orders`（生機発注・種別詳細）【新規】


| 列名                  | 型                         | NULL | デフォルト | 説明                              |
| ------------------- | ------------------------- | ---- | ----- | ------------------------------- |
| `purchase_order_id` | FK → `purchase_orders` PK | NO   |       |                                 |
| `greige_id`         | FK → `greiges`            | NO   |       |                                 |
| `qty_tan`           | unsignedInteger           | NO   | 0     | 発注反数（整数）                        |
| `meters_per_tan`    | unsignedInteger           | NO   |       | **発注時スナップショット**                 |
| `qty_meters`        | unsignedInteger           | NO   | 0     | 見積m（`qty_tan × meters_per_tan`） |
| `received_qty_tan`  | decimal(8,2)              | NO   | 0     | 入荷済み反数                          |
| `received_qty_m`    | unsignedInteger           | NO   | 0     | 入荷済み実測m合計                       |


**主キー:** `purchase_order_id`  
**インデックス:** `greige_id`

---



#### `product_purchase_orders`（製品発注・種別詳細）【新規】


| 列名                  | 型                         | NULL | デフォルト | 説明            |
| ------------------- | ------------------------- | ---- | ----- | ------------- |
| `purchase_order_id` | FK → `purchase_orders` PK | NO   |       |               |
| `product_id`        | FK → `products`           | NO   |       |               |
| `qty_tan`           | unsignedInteger           | NO   | 0     | 発注反数（整数）      |
| `qty_meters`        | unsignedInteger           | NO   | 0     | 見積m           |
| `received_qty_tan`  | decimal(8,2)              | NO   | 0     |               |
| `received_qty_m`    | unsignedInteger           | NO   | 0     |               |
| `stage`             | string(50)                | YES  | null  | 現在工程（例：染機投入済） |
| `finish_date`       | date                      | YES  | null  | 上がり予定日        |
| `contact_date`      | date                      | YES  | null  | 連絡日           |


**主キー:** `purchase_order_id`  
**インデックス:** `product_id`, `stage`

---



#### `purchase_order_schedule_events`（製品発注・工程）【新規】


| 列名                  | 型                      | NULL | デフォルト | 説明                                |
| ------------------- | ---------------------- | ---- | ----- | --------------------------------- |
| `id`                | bigint PK              | NO   | auto  |                                   |
| `purchase_order_id` | FK → `purchase_orders` | NO   |       | `type = product` のみ               |
| `stage_name`        | string(50)             | NO   |       | 工程名（`DemoData::PO_STAGES` と同じ文字列） |
| `planned_date`      | date                   | YES  | null  | 予定日                               |
| `sort_order`        | unsignedTinyInteger    | NO   | 0     | 表示順（PO_STAGES の並び）                |
| `created_at`        | timestamp              | YES  |       |                                   |
| `updated_at`        | timestamp              | YES  |       |                                   |


**ユニーク:** `(purchase_order_id, stage_name)`  
**インデックス:** `purchase_order_id`

**移行元:** 製品発注の `schedule` 連想配列

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


| 列名                  | 型                      | NULL | デフォルト | 説明         |
| ------------------- | ---------------------- | ---- | ----- | ---------- |
| `id`                | bigint PK              | NO   | auto  |            |
| `code`              | string(30)             | NO   |       | 入荷番号（RC-…） |
| `purchase_order_id` | FK → `purchase_orders` | NO   |       | 元発注        |
| `received_date`     | date                   | NO   |       | 入荷日        |
| `note`              | text                   | YES  | null  |            |
| `created_at`        | timestamp              | YES  |       |            |
| `updated_at`        | timestamp              | YES  |       |            |


**ユニーク:** `code`  
**インデックス:** `purchase_order_id`, `received_date`

**移行元:** `DemoData::receivings()` のヘッダ部分

---



#### `receiving_lines`（入荷明細サマリ）【新規】


| 列名             | 型                 | NULL | デフォルト   | 説明                            |
| -------------- | ----------------- | ---- | ------- | ----------------------------- |
| `id`           | bigint PK         | NO   | auto    |                               |
| `receiving_id` | FK → `receivings` | NO   | cascade |                               |
| `line_type`    | string(16)        | NO   |         | `yarn` / `greige` / `product` |
| `product_id`   | FK → `products`   | YES  | null    | 製品入荷時                         |
| `greige_id`    | FK → `greiges`    | YES  | null    | 生機入荷時                         |
| `material_id`  | FK → `materials`  | YES  | null    | 糸入荷時                          |
| `qty_tan`      | decimal(8,2)      | YES  | null    | 反数合計                          |
| `qty_m`        | unsignedInteger   | YES  | null    | 実測m合計                         |
| `qty_kg`       | decimal(12,3)     | YES  | null    | 糸kg合計                         |
| `created_at`   | timestamp         | YES  |         |                               |
| `updated_at`   | timestamp         | YES  |         |                               |


**制約（アプリ）:** `line_type` に応じて **1つだけ** の FK（`product_id` / `greige_id` / `material_id`）が NOT NULL。

**移行元:** 入荷一覧の品目サマリ。反ごとの実測は `greige_rolls` / `product_rolls` へ。

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


| 列名                  | 型                      | NULL | デフォルト        | 説明          |
| ------------------- | ---------------------- | ---- | ------------ | ----------- |
| `id`                | bigint PK              | NO   | auto         |             |
| `code`              | string(30)             | NO   |              | 反番号（GR-…）   |
| `greige_id`         | FK → `greiges`         | NO   |              |             |
| `purchase_order_id` | FK → `purchase_orders` | YES  | null         | 由来発注        |
| `receiving_id`      | FK → `receivings`      | YES  | null         | 入荷イベント      |
| `tan_qty`           | decimal(8,2)           | NO   | 1.00         | 反数（0.25刻み可） |
| `actual_qty_m`      | decimal(12,2)          | NO   | 0            | **織り実測m**   |
| `nominal_meters`    | unsignedInteger        | YES  | null         | 参考：標準長（表示用） |
| `status`            | string(32)             | NO   | `'in_stock'` |             |
| `received_date`     | date                   | NO   |              | FIFO 用      |
| `created_at`        | timestamp              | YES  |              |             |
| `updated_at`        | timestamp              | YES  |              |             |


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
| `receiving_id`          | FK → `receivings`      | YES  | null         |           |
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

**移行元:** `ProductRoll` JSON / 旧 `inbound_lots`（段階7で置き換え）

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

`DBplan.md` の段階に対応する。**1段階 = 1〜2個のマイグレーションファイル** が目安。


| 段階  | マイグレーション内容                                                       | 依存             |
| --- | ---------------------------------------------------------------- | -------------- |
| 1   | `customers`, `suppliers`, `ship_tos`, `greiges` 作成               | なし             |
| 2   | `products`, `materials` に列追加                                     | 段階1（`greiges`） |
| 3   | `orders` 作成                                                      | 段階1・2          |
| 4   | `purchase_orders` + 子3 + `purchase_order_schedule_events`        | 段階1〜3          |
| 5   | `order_allocations`                                              | 段階3・4          |
| 6   | `receivings`, `receiving_lines`, `greige_rolls`, `product_rolls` | 段階4            |
| 7   | `shipments`, `shipment_roll_allocations`、旧テーブルデータ移行              | 段階3・6          |
| 7b  | `shipment_plans.order_id` FK 化 + 反数列追加                           | 段階3            |
| 8   | レシピ3 + `material_prices` + `yarn_stock_movements`                | 段階1・2          |
| 9   | `sales_forecasts`, `sales_forecast_lines`                        | 段階2・3          |




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

---



## シーダー方針


| 段階  | Seeder              | データ源                             |
| --- | ------------------- | -------------------------------- |
| 1   | `CustomerSeeder` など | `DemoData::customers()` 等を配列から投入 |
| 2   | `ProductSeeder` 更新  | `greige_sku` → `greige_id` 解決    |
| 3以降 | 取引系                 | JSON / `DemoData` から変換スクリプト      |


**コツ:** まずマスタだけ DB に入れ、画面が動くことを確認してから受注・発注へ進む。

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

---



## デモデータ移行チェックリスト

実装時に1行ずつ確認する。

- [ ] `DemoData::customers()` → `customers`
- [ ] `DemoData::suppliers()` → `suppliers`
- [ ] `DemoData::shipTos()` → `ship_tos`
- [ ] `DemoData::greiges()` → `greiges`
- [ ] `DemoData::products()` → `products`（`greige_id` 変換）
- [ ] `DemoData::materials()` → `materials`
- [ ] `DemoData::orders()` → `orders`
- [ ] `DemoData::purchaseOrders()` → `purchase_orders` + 子テーブル + `schedule_events`
- [ ] `StockAllocation` JSON → `order_allocations`
- [ ] `DemoData::receivings()` → `receivings` + `receiving_lines` + 反明細
- [ ] `GreigeRoll` / `ProductRoll` JSON → `greige_rolls` / `product_rolls`
- [ ] `DemoData::shipments()` → `shipments` + `shipment_roll_allocations`
- [ ] `inbound_lots` → `product_rolls`（段階7）
- [ ] レシピ・単価・糸在庫 → 原価系テーブル
- [ ] 売上見通し JSON → `sales_forecasts` 系

---

*最終更新：*`DBplan.md`*（発注親子分割・反明細ルール）に合わせて具体列定義を作成。*
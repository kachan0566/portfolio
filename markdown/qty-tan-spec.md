# 反数管理 仕様書

生機・製品の数量は **反数をメイン表示** とし、業務ごとに「正とする数量」の持ち方を分ける。

> **発注工程との関係** … 工程表示は `PurchaseOrderDisplay`（種別ごと）が正。詳細は `markdown/change-instructions.md` **付録B** と `memo/DB.md` を参照。
> 旧「8段階進捗で在庫を動かす」連動（`GreigeInventory::handleStageTransition`）は **廃止済み**。

---

## 数量の基本ルール（現行）

| 項目 | 内容 |
| --- | --- |
| 画面表示 | **「2.5反 / 125m」** 形式（反がメイン、m がサブ） |
| 換算率 | 生機・製品は品番ごと（例：生機 100m/反、製品 50m/反） |
| 受注・発注・出荷・引当 | **整数反のみ**（`QtyHelper::ORDER_PO_TAN_STEP = 1`） |
| 入荷（在庫化） | **0.25反刻み** ＋ 反ごと実測 m（`RECEIVING_TAN_STEP`） |
| 価格・コスト | 円/m のまま |
| レシピ | kg/m のまま |

### 何を「正」として保存するか

| 領域 | 正とする値 | 補足 |
| --- | --- | --- |
| 受注・発注・出荷（伝票） | 反数ベースで入力、内部は m も保持 | `QtyHelper` で換算 |
| 入荷反明細 | **反ごとの実測 m**（`actual_qty_m`） | 標準 m/反 は見積用 |
| 在庫集計 | 反明細の合計（`GreigeRoll` / `ProductRoll`） | ロット集計は明細の足し算 |

---

## 生機在庫（染工場仕掛）

| 項目 | 内容 |
| --- | --- |
| 場所 | 染工場の仕掛在庫（染色待ち） |
| キー | 生機品番 ＋ **生機発注**（PO） |
| データ源 | `GreigeRoll`（反明細）があればそれを優先。なければ発注の入荷済み数量 |
| 増えるタイミング | **生機発注への入荷処理**（織り上がり登録） |
| 減るタイミング（在庫から外れる） | **製品発注を染機投入済にしたとき**（`GreigeDyeInput` → `in_dyeing`） |
| 減るタイミング（消費） | **染色完了登録**（`TanRollRecorder::recordDyeingCompletion`）で `consumed` |
| 画面 | 在庫一覧 **生機タブ** ＋ 発注詳細 |

### 発注工程との対応（表示のみ）

生機在庫の増減は **工程バッジの変更では動かない**。

| 画面の工程表示 | 在庫との関係 |
| --- | --- |
| 生機出荷済（染工場入荷開始） | 入荷済みならこのラベルになるが、在庫は **入荷記録** で増える |
| 染機投入済（製品発注の手動工程） | 生機反を `in_dyeing` に移動（`GreigeDyeInput`）。生機在庫カウントから外れる |
| 染色完了登録 | `in_dyeing` の反を `consumed` にし、製品反を生成 |

---

## 製品在庫・染色移動

| 項目 | 内容 |
| --- | --- |
| データ源 | `ProductRoll`（反明細） |
| 増えるタイミング | 製品発注への入荷、または染色完了で生機から生成 |
| 移動ルール | 生機反を消費し、製品反を作成（m は実測を引き継ぐ。反数は品番換算） |
| デモ補助 | `DemoState::applyDyeTransfer` … 旧デモ用の m 移動記録（工程とは独立） |

---

## 実装ファイル

| ファイル | 役割 |
| --- | --- |
| `app/Support/QtyHelper.php` | 表示・換算・刻みルール |
| `app/Support/GreigeRoll.php` | 生機の物理反（1反 = 1レコード） |
| `app/Support/ProductRoll.php` | 製品の物理反 |
| `app/Support/GreigeInventory.php` | 生機在庫一覧の組み立て（入荷ベース） |
| `app/Services/Inventory/GreigeDyeInput.php` | 染機投入に連動した生機反の `in_dyeing` 移動 |
| `app/Services/Fabric/TanRollRecorder.php` | 織り上がり・染め上がりの反登録 |
| `app/Support/DemoState.php` | 発注の手動工程オーバーレイ・デモ用染色移動 |
| `app/Support/PurchaseOrderDisplay.php` | 発注の工程表示ラベル |
| `resources/views/inventory/index.blade.php` | 製品／生機タブ |
| `resources/views/purchases/show.blade.php` | 発注詳細 |

---

## 実装フェーズ（履歴）

| フェーズ | 内容 | 状態 |
| --- | --- | --- |
| Phase 1 | 標準換算・表示形式・生機在庫の PO 集計 | ✅ 基盤済み（反明細へ拡張済み） |
| Phase 2 | 受注・発注・出荷の反数入力、m 上書き | ✅ |
| Phase 3 | 反明細（`GreigeRoll` / `ProductRoll`）、入荷行 UI | ✅ |
| 発注工程リニューアル | 種別ごと工程・表示1本化 | ✅（`PurchaseOrderStages` 等） |

---

## 将来拡張

連携の実装チェックリスト・着手順は [`memo/integration/factory-receiving.md`](../memo/integration/factory-receiving.md) を参照。

### 染工場・織工場システム連携

| 現状 | 将来 |
| --- | --- |
| 入荷画面で反行を手入力 | 工場 API から反ごとの実測 m を受信 |
| JSON ファイルで反明細 | DB テーブル `greige_rolls` / `product_rolls`（`memo/DB.md`） |
| ロット（PO）単位の一覧 | 反 ID 単位の追跡・FIFO 出荷 |

### 反明細のデータイメージ（DB 移行後）

```
code:           KB-A-PO003-01
greige_id:      …
purchase_order_id: 3
tan_qty:        1.00
actual_qty_m:   98.2        ← 正（工場計量）
status:         in_stock | partially_consumed | consumed
received_date:  2026-06-16
```

### その他の将来検討

- 染色ロス（投入 m と製品 m の差の記録）
- 生機の直接販売（現状は対象外）
- 場所別在庫（自社 / 染工場 / 織工場）
- 工程ごとの予定日テーブル（現状は `due_date` / `finish_date` のみ）

---

*最終更新：* 発注工程リニューアル（2026-07）に合わせ、工程連動の記述を入荷・反明細ベースに修正。

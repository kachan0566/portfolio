# 工場連携（未実装・設計メモ）

織編・染工などの工場システムから入荷実績を受け取り、在庫を自動反映する機能の着手用メモ。  
**実装はまだしない。** 連携開発を始める日に、このファイルを最初に開く。

全体方針は [`markdown/portfolio.md`](../../markdown/portfolio.md) の「外部工場連携（将来方針）」を参照。

---

## 目的

| 項目 | 内容 |
| --- | --- |
| 何をするか | 工場の自社システム → 本システムへ入荷実績を送信 → 在庫自動反映 |
| 誰が使うか | 工場側のシステム（自社担当者の手入力画面は併存） |
| 最初の対象 | 入荷（糸・生機・製品）。出荷連携は段階12以降で検討 |

---

## アーキテクチャ（予定）

```text
【フェーズ1・現状】
Blade 入荷画面 → ReceivingController → ReceivingRegistrar → DB・在庫

【フェーズ2・追加する層】
工場システム → POST /api/v1/receivings (JSON)
                    ↓
              FactoryReceivingController（新規）
                    ↓
              ReceivingRegistrar::register()  ← 画面と同じ
                    ↓
              DB・在庫
```

出荷も同様に、将来は `ShipmentRegistrar::register()` を API から呼ぶ想定。

---

## 実装チェックリスト

着手時は上から順に進める。

### 準備

- [ ] 工場（仕入先）ごとの連携要件を確認（JSON 形式・頻度・リアルタイムかバッチか）
- [ ] 対象発注の特定方法を決める（`po_code` / `purchase_order_line_id` など）
- [ ] 反明細（`roll_lines`）を送るか、数量のみかを決める → [`markdown/qty-tan-spec.md`](../../markdown/qty-tan-spec.md)

### Laravel（API 入口）

- [ ] `routes/api.php` を追加し `bootstrap/app.php` の `withRouting` に登録
- [ ] `POST /api/v1/receivings` コントローラーを新規作成
- [ ] リクエスト JSON を `ReceivingRegistrar::register()` 用の `$entries` 配列に変換
- [ ] 成功時は JSON（`receiving_code`, `message`）。422 / 404 も JSON で返す
- [ ] 既存の Blade 入荷画面は変更しない（併存）

### 認証・権限

- [ ] 工場（`suppliers`）ごとの API キーまたはトークン
- [ ] ミドルウェアで「この工場は自分の発注への入荷だけ登録可」を強制
- [ ] 本番では HTTPS 必須

### DB・二重登録防止

- [ ] `receivings` に `external_id`（工場側の伝票 ID）+ `source_supplier_id` を追加
- [ ] `(source_supplier_id, external_id)` のユニーク制約で同一データの再送を弾く
- [ ] 連携ログテーブル（任意）: 受信 raw JSON・処理結果・エラー理由

### テスト

- [ ] Feature テスト: 正常系・発注なし 404・数量超過 422・二重送信
- [ ] 既存の `ReceivingController` 手入力テストが壊れていないこと

### 仕様書

- [ ] 工場向けのみ OpenAPI または `memo/API/factory-receivings-api.md` を作成
- [ ] 社内画面用仕様（フォーム・リダイレクト）とは分けて書く → [`memo/API/APIexplain.md`](../API/APIexplain.md)

---

## エンドポイント案（ドラフト）

```http
POST /api/v1/receivings
Authorization: Bearer {工場ごとのトークン}
Content-Type: application/json
```

```json
{
  "external_id": "DYE-RCV-20260620-001",
  "po_code": "PO-2606-002",
  "date": "2026-06-20",
  "entries": [
    {
      "line_no": 1,
      "qty_meters": 500,
      "qty_tan": 10,
      "roll_lines": [
        { "tan_qty": 1.0, "actual_qty_m": 98.2 }
      ]
    }
  ]
}
```

成功レスポンス案:

```json
{
  "success": true,
  "receiving_code": "RC-260620-001",
  "message": "入荷を登録しました。"
}
```

※ フィールド名・必須条件は工場との合意後に確定する。

---

## 共通処理（既存コード）

| 処理 | クラス | 呼び出し元（現状） |
| --- | --- | --- |
| 入荷登録 | `App\Services\Receiving\ReceivingRegistrar` | `ReceivingController@store` |
| 出荷登録 | `App\Services\Shipment\ShipmentRegistrar` | `ShipmentController@store` |

`ReceivingRegistrar::register()` の引数形:

```php
register(
    int $poId,
    string $date,
    string $poType,
    array $entries = [],
    // ...
): array
```

`$entries` の各要素は `purchase_order_line_id`, `qty_kg` / `qty_tan` / `qty_meters`, `roll_lines` など。  
API コントローラーは **ID の解決とバリデーションだけ** 行い、在庫反映は Registrar に任せる。

---

## 開発習慣（フェーズ1中も守る）

1. **在庫を変える処理は Registrar / Service に寄せる**（コントローラーに書かない）
2. **画面専用のロジックを Registrar に混ぜない**（リダイレクト・セッションは Controller の責務）
3. 新規の入荷・出荷関連機能を足すときは、Registrar 経由かどうかを確認する

---

## 関連ドキュメント

| ファイル | 内容 |
| --- | --- |
| [`markdown/portfolio.md`](../../markdown/portfolio.md) | 全体方針・フェーズ1/2 |
| [`memo/DB.md`](../DB.md) | 本線ロードマップ・段階11（将来） |
| [`markdown/qty-tan-spec.md`](../../markdown/qty-tan-spec.md) | 反明細・工場からの実測 m |
| [`memo/API/APIexplain.md`](../API/APIexplain.md) | 仕様書の書き方（社内画面 vs 工場 API） |

---

*作成: 工場連携着手前の設計メモ。実装開始時にチェックリストを更新すること。*

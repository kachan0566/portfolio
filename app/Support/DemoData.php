<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * フロント確認用のテストデータ置き場。
 *
 * データベースには接続せず、ここで定義した固定データ（テストデータ）を
 * 各画面に渡す。実データに差し替えるときは、各メソッドの中身を
 * Eloquent などの取得処理へ置き換えればよい。
 */
class DemoData
{
    /** 「今月」として扱う年月（テスト用に固定） */
    public const CURRENT_YM = '2026-06';

    /** 受注登録デモの遷移先（本日受付・在庫十分で即引当可能なシナリオ） */
    public const DEMO_NEW_ORDER_ID = 8;

    /** 大型受注デモの遷移先（FAB-T-BK・在庫大幅不足で追加発注が必要なシナリオ） */
    public const DEMO_LARGE_ORDER_ID = 9;

    /** 製品品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_PRODUCT = 50;

    /** 生機品番の標準：1反あたりのメートル数 */
    public const METERS_PER_TAN_GREIGE = 100;

    /** 発注（生産）の進捗段階。左から順に進む */
    public const PO_STAGES = [
        '原材料未発注',
        '原材料発注済',
        '原材料出荷済',
        '織編機投入済',
        '生機出荷済',
        '染機投入済',
        '製品在庫中',
        '製品出荷済',
    ];

    /** カテゴリ一覧 */
    public static function categories(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '生地'],
            (object) ['id' => 2, 'name' => '糸'],
            (object) ['id' => 3, 'name' => '製品'],
        ]);
    }

    /** 生機品番（親品番）一覧 */
    public static function greiges(): Collection
    {
        return collect([
            (object) ['id' => 1, 'sku' => 'KB-A', 'name' => '生機A',        'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 2, 'sku' => 'KB-B', 'name' => '生機B',        'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 3, 'sku' => 'KB-T', 'name' => 'Tシャツ生機',  'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 4, 'sku' => 'KB-C', 'name' => '裏地C生機',    'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
            (object) ['id' => 5, 'sku' => 'KB-D', 'name' => 'デニム生機',   'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_GREIGE],
        ]);
    }

    /** 製品品番（子品番）一覧。1つの生機品番に複数の製品品番（カラー違い）がぶら下がる */
    public static function products(): Collection
    {
        return collect([
            (object) ['id' => 1, 'sku' => 'FAB-A-BK', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ブラック',   'price' => 1200, 'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 40,  'stock_min' => 100],
            (object) ['id' => 2, 'sku' => 'FAB-B-NV', 'greige_sku' => 'KB-B', 'greige_name' => '生機B',       'color' => 'ネイビー',   'price' => 1500, 'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 100],
            (object) ['id' => 3, 'sku' => 'FAB-T-WH', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ホワイト',   'price' => 900,  'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 70,  'stock_min' => 150],
            (object) ['id' => 4, 'sku' => 'LIN-C-BE', 'greige_sku' => 'KB-C', 'greige_name' => '裏地C生機',   'color' => 'ベージュ',   'price' => 700,  'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 80],
            (object) ['id' => 5, 'sku' => 'DEN-D-IN', 'greige_sku' => 'KB-D', 'greige_name' => 'デニム生機',   'color' => 'インディゴ', 'price' => 1800, 'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 100],
            (object) ['id' => 6, 'sku' => 'FAB-A-WH', 'greige_sku' => 'KB-A', 'greige_name' => '生機A',       'color' => 'ホワイト',   'price' => 1250, 'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 0,   'stock_min' => 80],
            (object) ['id' => 7, 'sku' => 'FAB-T-BK', 'greige_sku' => 'KB-T', 'greige_name' => 'Tシャツ生機', 'color' => 'ブラック',   'price' => 950,  'category' => '生地', 'unit' => 'm', 'meters_per_tan' => self::METERS_PER_TAN_PRODUCT, 'stock' => 120,  'stock_min' => 150],
        ]);
    }

    public static function findProduct(int $id): ?object
    {
        return self::products()->firstWhere('id', $id);
    }

    /** 原材料一覧 */
    public static function materials(): Collection
    {
        return collect([
            (object) ['id' => 1, 'sku' => 'RM-001', 'name' => '綿糸',         'unit' => 'kg'],
            (object) ['id' => 2, 'sku' => 'RM-002', 'name' => 'ポリエステル糸', 'unit' => 'kg'],
            (object) ['id' => 3, 'sku' => 'RM-003', 'name' => '染料',         'unit' => 'kg'],
            (object) ['id' => 4, 'sku' => 'RM-004', 'name' => '仕上げ剤',      'unit' => 'L'],
        ]);
    }

    public static function findMaterial(int $id): ?object
    {
        return self::materials()->firstWhere('id', $id);
    }

    /** 月別原材料価格 */
    public static function materialPrices(): Collection
    {
        $rows = [
            ['material_id' => 1, 'prices' => ['2026-04' => 480,  '2026-05' => 500,  '2026-06' => 550]],
            ['material_id' => 2, 'prices' => ['2026-04' => 300,  '2026-05' => 320,  '2026-06' => 310]],
            ['material_id' => 3, 'prices' => ['2026-04' => 1200, '2026-05' => 1250, '2026-06' => 1300]],
            ['material_id' => 4, 'prices' => ['2026-04' => 800,  '2026-05' => 820,  '2026-06' => 850]],
        ];

        $result = collect();
        $id = 1;
        foreach ($rows as $row) {
            $material = self::findMaterial($row['material_id']);
            foreach ($row['prices'] as $ym => $price) {
                $result->push((object) [
                    'id' => $id++,
                    'material_sku' => $material->sku,
                    'material' => $material->name,
                    'unit' => $material->unit,
                    'ym' => $ym,
                    'price' => $price,
                ]);
            }
        }

        return $result;
    }

    /** 指定原材料・年月の単価を取得 */
    public static function materialPrice(int $materialId, string $ym): int
    {
        $material = self::findMaterial($materialId);
        $row = self::materialPrices()
            ->where('material', $material->name)
            ->firstWhere('ym', $ym);

        return $row?->price ?? 0;
    }

    /** 商品レシピ（商品ごとの原材料使用量） */
    public static function recipes(): Collection
    {
        $data = [
            1 => [[1, 2.0], [3, 0.3], [4, 0.1]],
            2 => [[1, 1.5], [2, 1.0], [3, 0.4]],
            3 => [[1, 1.8], [4, 0.2]],
            4 => [[2, 2.0], [3, 0.2]],
            5 => [[1, 2.5], [3, 0.5], [4, 0.15]],
            6 => [[1, 2.0], [3, 0.3], [4, 0.1]],
            7 => [[1, 1.8], [4, 0.2]],
        ];

        $result = collect();
        $id = 1;
        foreach ($data as $productId => $items) {
            $product = self::findProduct($productId);
            foreach ($items as [$materialId, $qty]) {
                $material = self::findMaterial($materialId);
                $result->push((object) [
                    'id' => $id++,
                    'product_id' => $productId,
                    'product' => $product->sku,
                    'sku' => $product->sku,
                    'material_id' => $materialId,
                    'material' => $material->name,
                    'material_sku' => $material->sku,
                    'unit' => $material->unit,
                    'qty' => $qty,
                ]);
            }
        }

        return $result;
    }

    /** 商品1単位あたりの製造コスト（指定年月の原材料単価で計算） */
    public static function unitCost(int $productId, string $ym): float
    {
        return self::recipes()
            ->where('product_id', $productId)
            ->sum(fn ($r) => $r->qty * self::materialPrice($r->material_id, $ym));
    }

    /** 得意先 */
    public static function customers(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '東レ商事',        'contact' => '田中 一郎', 'tel' => '03-1111-2222'],
            (object) ['id' => 2, 'name' => 'アパレル東京',    'contact' => '佐藤 花子', 'tel' => '03-3333-4444'],
            (object) ['id' => 3, 'name' => '西日本繊維',      'contact' => '鈴木 次郎', 'tel' => '06-5555-6666'],
            (object) ['id' => 4, 'name' => 'ユニフォーム製作所', 'contact' => '高橋 三郎', 'tel' => '052-777-8888'],
        ]);
    }

    /** 仕入先 */
    public static function suppliers(): Collection
    {
        return collect([
            (object) ['id' => 1, 'name' => '紡績ワークス',    'contact' => '伊藤 健', 'tel' => '03-9999-0000'],
            (object) ['id' => 2, 'name' => 'ケミカル商会',    'contact' => '渡辺 茜', 'tel' => '06-1212-3434'],
            (object) ['id' => 3, 'name' => '染料センター',    'contact' => '山本 武', 'tel' => '075-5656-7878'],
        ]);
    }

    /** 受注一覧 */
    public static function orders(): Collection
    {
        $rows = [
            ['id' => 1, 'code' => 'SO-2606-001', 'customer' => '東レ商事',        'product_id' => 1, 'qty' => 120, 'shipped' => 120, 'order_date' => '2026-06-02', 'due_date' => '2026-06-12', 'ship_memo' => '6/11 全量出荷済み'],
            ['id' => 2, 'code' => 'SO-2606-002', 'customer' => 'アパレル東京',    'product_id' => 3, 'qty' => 200, 'shipped' => 80,  'order_date' => '2026-06-03', 'due_date' => '2026-06-18', 'ship_memo' => '残120は6/17午前に分納予定'],
            ['id' => 3, 'code' => 'SO-2606-003', 'customer' => '西日本繊維',      'product_id' => 5, 'qty' => 90,  'shipped' => 0,   'order_date' => '2026-06-05', 'due_date' => '2026-06-20', 'ship_memo' => '在庫確保のうえ6/19出荷予定'],
            ['id' => 4, 'code' => 'SO-2606-004', 'customer' => 'ユニフォーム製作所', 'product_id' => 2, 'qty' => 60,  'shipped' => 60,  'order_date' => '2026-06-06', 'due_date' => '2026-06-15', 'ship_memo' => '6/12 出荷完了'],
            ['id' => 5, 'code' => 'SO-2606-005', 'customer' => '東レ商事',        'product_id' => 4, 'qty' => 150, 'shipped' => 0,   'order_date' => '2026-06-08', 'due_date' => '2026-06-25', 'ship_memo' => '入荷待ち。6/24までに出荷予定'],
            ['id' => 6, 'code' => 'SO-2606-006', 'customer' => 'アパレル東京',    'product_id' => 1, 'qty' => 100, 'shipped' => 40,  'order_date' => '2026-06-10', 'due_date' => '2026-06-28', 'ship_memo' => '残60を6/27に出荷予定'],
            ['id' => 7, 'code' => 'SO-2606-007', 'customer' => '西日本繊維',      'product_id' => 6, 'qty' => 180, 'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-03', 'ship_memo' => '本日受付。在庫140mを引当予定。不足40mは PO-2606-007 で追加発注済み'],
            ['id' => 8, 'code' => 'SO-2606-008', 'customer' => 'アパレル東京',    'product_id' => 7, 'qty' => 80,  'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-08', 'ship_memo' => '本日受付。在庫不足のため PO-2606-008 を追加手配済み'],
            ['id' => 9, 'code' => 'SO-2606-009', 'customer' => '東レ商事',        'product_id' => 7, 'qty' => 500, 'shipped' => 0,   'order_date' => '2026-06-25', 'due_date' => '2026-07-20', 'ship_memo' => '本日受付。大型案件500m。在庫不足のため PO-2606-009 を追加手配済み'],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['color'] = $product->color;
            $r['unit'] = $product->unit;
            $r['status'] = self::progressStatus($r['shipped'], $r['qty'], '受注');
            $r['is_new_today'] = $r['order_date'] === self::today();
            return (object) $r;
        });
    }

    /** デモ上の「今日」の日付（受注日の基準） */
    public static function today(): string
    {
        return self::CURRENT_YM.'-25';
    }

    /** 受注日の新しい順に並べた一覧 */
    public static function recentOrders(int $limit = 6): Collection
    {
        return self::orders()
            ->sortBy([
                ['order_date', 'desc'],
                ['id', 'desc'],
            ])
            ->take($limit)
            ->values();
    }

    /** 発注（生産）一覧 */
    public static function purchaseOrders(): Collection
    {
        $rows = [
            [
                'id' => 1, 'code' => 'PO-2606-001', 'order_id' => 1, 'supplier' => '紡績ワークス', 'customer' => '東レ商事',
                'product_id' => 1, 'qty' => 200, 'received' => 200,
                'order_date' => '2026-06-01', 'eta' => '2026-06-08',
                'stage' => '製品出荷済', 'finish_date' => '2026-06-07', 'contact_date' => '2026-06-06',
                'schedule' => [
                    '原材料未発注' => '2026-06-01', '原材料発注済' => '2026-06-02', '原材料出荷済' => '2026-06-03',
                    '織編機投入済' => '2026-06-04', '生機出荷済' => '2026-06-05', '染機投入済' => '2026-06-06',
                    '製品在庫中' => '2026-06-07', '製品出荷済' => '2026-06-08',
                ],
            ],
            [
                'id' => 2, 'code' => 'PO-2606-002', 'order_id' => 2, 'supplier' => 'ケミカル商会', 'customer' => 'アパレル東京',
                'product_id' => 3, 'qty' => 300, 'received' => 150,
                'order_date' => '2026-06-03', 'eta' => '2026-06-14',
                'stage' => '染機投入済', 'finish_date' => '2026-06-16', 'contact_date' => '2026-06-15',
                'schedule' => [
                    '原材料未発注' => '2026-06-03', '原材料発注済' => '2026-06-05', '原材料出荷済' => '2026-06-07',
                    '織編機投入済' => '2026-06-10', '生機出荷済' => '2026-06-12', '染機投入済' => '2026-06-14',
                    '製品在庫中' => '2026-06-16', '製品出荷済' => '2026-06-18',
                ],
            ],
            [
                'id' => 3, 'code' => 'PO-2606-003', 'order_id' => 3, 'supplier' => '染料センター', 'customer' => '西日本繊維',
                'product_id' => 5, 'qty' => 120, 'received' => 0,
                'order_date' => '2026-06-05', 'eta' => '2026-06-19',
                'stage' => '原材料発注済', 'finish_date' => '2026-06-22', 'contact_date' => '2026-06-20',
                'schedule' => [
                    '原材料未発注' => '2026-06-05', '原材料発注済' => '2026-06-08', '原材料出荷済' => '2026-06-11',
                    '織編機投入済' => '2026-06-14', '生機出荷済' => '2026-06-16', '染機投入済' => '2026-06-19',
                    '製品在庫中' => '2026-06-21', '製品出荷済' => '2026-06-22',
                ],
            ],
            [
                'id' => 7, 'code' => 'PO-2606-007', 'order_id' => 7, 'supplier' => '染料センター', 'customer' => '西日本繊維',
                'product_id' => 6, 'qty' => 40, 'received' => 0,
                'order_date' => '2026-06-25', 'eta' => '2026-07-01',
                'stage' => '原材料発注済', 'finish_date' => '2026-07-02', 'contact_date' => '2026-06-30',
                'schedule' => [
                    '原材料未発注' => '2026-06-25', '原材料発注済' => '2026-06-25', '原材料出荷済' => '2026-06-27',
                    '織編機投入済' => '2026-06-28', '生機出荷済' => '2026-06-29', '染機投入済' => '2026-07-01',
                    '製品在庫中' => '2026-07-02', '製品出荷済' => '2026-07-03',
                ],
            ],
            [
                'id' => 8, 'code' => 'PO-2606-008', 'order_id' => 8, 'supplier' => '紡績ワークス', 'customer' => 'アパレル東京',
                'product_id' => 7, 'qty' => 200, 'received' => 120,
                'order_date' => '2026-06-25', 'eta' => '2026-07-05',
                'stage' => '原材料発注済', 'finish_date' => '2026-07-06', 'contact_date' => '2026-07-04',
                'schedule' => [
                    '原材料未発注' => '2026-06-25', '原材料発注済' => '2026-06-25', '原材料出荷済' => '2026-06-28',
                    '織編機投入済' => '2026-06-30', '生機出荷済' => '2026-07-01', '染機投入済' => '2026-07-03',
                    '製品在庫中' => '2026-07-06', '製品出荷済' => '2026-07-07',
                ],
            ],
            [
                'id' => 9, 'code' => 'PO-2606-009', 'order_id' => 9, 'supplier' => '紡績ワークス', 'customer' => '東レ商事',
                'product_id' => 7, 'qty' => 500, 'received' => 0,
                'order_date' => '2026-06-25', 'eta' => '2026-07-18',
                'stage' => '原材料発注済', 'finish_date' => '2026-07-19', 'contact_date' => '2026-07-17',
                'schedule' => [
                    '原材料未発注' => '2026-06-25', '原材料発注済' => '2026-06-25', '原材料出荷済' => '2026-06-28',
                    '織編機投入済' => '2026-07-02', '生機出荷済' => '2026-07-06', '染機投入済' => '2026-07-12',
                    '製品在庫中' => '2026-07-19', '製品出荷済' => '2026-07-20',
                ],
            ],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['unit'] = $product->unit;
            $idx = array_search($r['stage'], self::PO_STAGES, true);
            $r['progress'] = (int) round(($idx + 1) / count(self::PO_STAGES) * 100);
            $linkedOrderId = PurchaseOrderLink::orderIdForPurchase($r['id'], $r['order_id'] ?? null);
            $r['order_id'] = $linkedOrderId;
            $r['order_code'] = $linkedOrderId
                ? (self::orders()->firstWhere('id', $linkedOrderId)?->code ?? null)
                : null;
            return (object) $r;
        });
    }

    /** 出荷一覧 */
    public static function shipments(): Collection
    {
        $rows = [
            ['id' => 1, 'code' => 'SH-2606-001', 'order_code' => 'SO-2606-001', 'customer' => '東レ商事',        'product_id' => 1, 'qty' => 120, 'date' => '2026-06-11', 'due_date' => '2026-06-12', 'ship_to' => '東レ商事 滋賀倉庫',     'note' => '時間指定 午前中'],
            ['id' => 2, 'code' => 'SH-2606-002', 'order_code' => 'SO-2606-004', 'customer' => 'ユニフォーム製作所', 'product_id' => 2, 'qty' => 60,  'date' => '2026-06-12', 'due_date' => '2026-06-15', 'ship_to' => 'ユニフォーム製作所 本社', 'note' => ''],
            ['id' => 3, 'code' => 'SH-2606-003', 'order_code' => 'SO-2606-002', 'customer' => 'アパレル東京',    'product_id' => 3, 'qty' => 80,  'date' => '2026-06-14', 'due_date' => '2026-06-18', 'ship_to' => 'アパレル東京 物流センター', 'note' => '分納の1回目'],
            ['id' => 4, 'code' => 'SH-2606-004', 'order_code' => 'SO-2606-006', 'customer' => 'アパレル東京',    'product_id' => 1, 'qty' => 40,  'date' => '2026-06-15', 'due_date' => '2026-06-28', 'ship_to' => 'アパレル東京 物流センター', 'note' => ''],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['color'] = $product->color;
            $r['unit'] = $product->unit;
            $r['price'] = $product->price;
            $r['amount'] = $product->price * $r['qty'];
            return (object) $r;
        });
    }

    /** 入荷一覧 */
    public static function receivings(): Collection
    {
        $rows = [
            ['id' => 1, 'code' => 'RC-2606-001', 'po_code' => 'PO-2606-001', 'supplier' => '紡績ワークス', 'product_id' => 1, 'qty' => 200, 'date' => '2026-06-08'],
            ['id' => 3, 'code' => 'RC-2606-003', 'po_code' => 'PO-2606-002', 'supplier' => 'ケミカル商会', 'product_id' => 3, 'qty' => 150, 'date' => '2026-06-14'],
            ['id' => 4, 'code' => 'RC-2606-004', 'po_code' => 'PO-2606-008', 'supplier' => '紡績ワークス', 'product_id' => 7, 'qty' => 120, 'date' => '2026-06-25'],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['unit'] = $product->unit;
            return (object) $r;
        });
    }

    /** 在庫移動履歴 */
    public static function stockMovements(): Collection
    {
        $rows = [
            ['date' => '2026-06-08', 'product_id' => 1, 'type' => '入庫', 'qty' => 200, 'note' => '入荷 RC-2606-001'],
            ['date' => '2026-06-11', 'product_id' => 1, 'type' => '出庫', 'qty' => 120, 'note' => '出荷 SH-2606-001'],
            ['date' => '2026-06-12', 'product_id' => 2, 'type' => '出庫', 'qty' => 60,  'note' => '出荷 SH-2606-002'],
            ['date' => '2026-06-14', 'product_id' => 3, 'type' => '入庫', 'qty' => 150, 'note' => '入荷 RC-2606-003'],
            ['date' => '2026-06-14', 'product_id' => 3, 'type' => '出庫', 'qty' => 80,  'note' => '出荷 SH-2606-003'],
            ['date' => '2026-06-15', 'product_id' => 1, 'type' => '出庫', 'qty' => 40,  'note' => '出荷 SH-2606-004'],
            ['date' => '2026-06-25', 'product_id' => 7, 'type' => '入庫', 'qty' => 120,  'note' => '入荷 RC-2606-004'],
        ];

        return collect($rows)->map(function ($r) {
            $product = self::findProduct($r['product_id']);
            $r['product'] = $product->sku;
            $r['sku'] = $product->sku;
            $r['unit'] = $product->unit;
            return (object) $r;
        })->sortByDesc('date')->values();
    }

    /** 進捗からステータス文字列を返す */
    private static function progressStatus(int $done, int $total, string $kind): string
    {
        $shipped = $kind === '受注';
        if ($done <= 0) {
            return $shipped ? '未出荷' : '未入荷';
        }
        if ($done < $total) {
            return $shipped ? '一部出荷' : '一部入荷';
        }
        return $shipped ? '出荷済み' : '入荷済み';
    }

    /** 今月の売上・製造コスト・粗利を商品別に集計 */
    public static function monthlySalesByProduct(): Collection
    {
        $ym = self::CURRENT_YM;

        return self::shipments()
            ->filter(fn ($s) => str_starts_with($s->date, $ym))
            ->groupBy('product_id')
            ->map(function ($group) use ($ym) {
                $first = $group->first();
                $qty = $group->sum('qty');
                $sales = $group->sum('amount');
                $cost = self::unitCost($first->product_id, $ym) * $qty;
                return (object) [
                    'product_id' => $first->product_id,
                    'product' => $first->product,
                    'sku' => $first->sku,
                    'unit' => $first->unit,
                    'qty' => $qty,
                    'sales' => (int) round($sales),
                    'cost' => (int) round($cost),
                    'profit' => (int) round($sales - $cost),
                ];
            })
            ->values();
    }

    /** ダッシュボード用のKPIなどをまとめて返す */
    public static function dashboard(): array
    {
        $salesByProduct = self::monthlySalesByProduct();

        $orders = self::orders();
        $purchaseOrders = self::purchaseOrders();
        $lowStock = self::products()->filter(fn ($p) => $p->stock < $p->stock_min)->values();

        return [
            'sales' => $salesByProduct->sum('sales'),
            'shippedQty' => $salesByProduct->sum('qty'),
            'profit' => $salesByProduct->sum('profit'),
            'cost' => $salesByProduct->sum('cost'),
            'unshippedOrders' => $orders->whereIn('status', ['未出荷', '一部出荷'])->count(),
            'unreceivedPurchaseOrders' => $purchaseOrders->where('stage', '!=', '製品出荷済')->count(),
            'lowStock' => $lowStock,
            'salesByProduct' => $salesByProduct,
            // 売上・粗利の推移（過去6か月のデモ値）
            'trend' => collect([
                ['ym' => '2026-01', 'sales' => 980000,  'profit' => 320000],
                ['ym' => '2026-02', 'sales' => 1120000, 'profit' => 360000],
                ['ym' => '2026-03', 'sales' => 1040000, 'profit' => 335000],
                ['ym' => '2026-04', 'sales' => 1260000, 'profit' => 410000],
                ['ym' => '2026-05', 'sales' => 1180000, 'profit' => 388000],
                ['ym' => '2026-06', 'sales' => $salesByProduct->sum('sales'), 'profit' => $salesByProduct->sum('profit')],
            ]),
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Inventory\GreigeRoll;
use App\Models\Inventory\ProductRoll;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Receiving\Receiving;
use App\Models\Receiving\ReceivingLine;
use App\Services\Fabric\TanRollRecorder;
use App\Services\Receiving\PurchaseOrderLineReceiver;
use App\Services\Receiving\ReceivingLineTotals;
use App\Services\Yarn\YarnStockMovementRecorder;
use App\Support\Core\DemoData;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * デモ用の入荷データを、入荷・明細・在庫の各テーブルへ登録する。
 *
 * 元データは DemoData::baseReceivingRows()。発注（purchase_orders）が
 * 先に無い行は登録しない。DatabaseSeeder から呼び出す。
 */
class ReceivingSeeder extends Seeder
{
    /**
     * デモ入荷の各行を、入荷ヘッダ・明細と、種別ごとの在庫記録へ登録または更新する。
     *
     * 処理の流れ:
     * 1. po_code で発注を探し、その明細（line_no = 1）を探す。どちらか無ければその行は飛ばす
     * 2. receivings と receiving_lines を、id と明細番号で updateOrCreate する（同じキーなら更新）
     * 3. po_type が生機なら反（greige_rolls）、製品なら反（product_rolls）、糸なら在庫移動を作る
     * 4. 明細の数量合計と、発注明細の入荷累計を同期する
     *
     * @return void  返り値は使わない。結果は DB の登録・更新として残る
     */
    public function run(): void
    {
        $now = now();

        foreach (DemoData::baseReceivingRows() as $row) {
            $po = PurchaseOrder::query()->where('code', $row['po_code'])->first();
            if ($po === null) {
                continue;
            }

            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $po->id)
                ->where('line_no', 1)
                ->first();
            if ($poLine === null) {
                continue;
            }

            Receiving::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'received_date' => $row['date'],
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $receivingLine = ReceivingLine::query()->updateOrCreate(
                ['receiving_id' => $row['id'], 'line_no' => 1],
                [
                    'purchase_order_line_id' => $poLine->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $type = (string) $row['po_type'];

            if ($type === PurchaseOrderType::GREIGE) {
                $this->seedGreigeRolls($row, $po, $receivingLine, $now);
                ReceivingLineTotals::sync($receivingLine->fresh());
            } elseif ($type === PurchaseOrderType::PRODUCT) {
                $this->seedProductRolls($row, $po, $receivingLine, $now);
                ReceivingLineTotals::sync($receivingLine->fresh());
            } elseif ($type === PurchaseOrderType::YARN) {
                $qtyKg = (float) ($row['qty_kg'] ?? 0);
                YarnStockMovementRecorder::recordYarnReceiving(
                    $receivingLine,
                    (int) ($row['material_id'] ?? $poLine->material_id),
                    $qtyKg,
                    (string) $row['date'],
                    '入荷 '.($row['code'] ?? ''),
                );
                ReceivingLineTotals::sync($receivingLine->fresh());
            }

            PurchaseOrderLineReceiver::syncFromReceivingLine($receivingLine->fresh());
        }
    }

    /**
     * 生機の入荷1件を、反（greige_rolls の1行＝生地1本）に分けて登録または更新する。
     *
     * 処理の流れ:
     * 1. greige_sku で生機マスタを探す。無いときは何もしない
     * 2. qty_meters（無ければ qty）が 0 以下なら何もしない
     * 3. 1反あたりの目安メートルで反数に分け、実メートルを配分する
     * 4. 反コード（SKU-発注コード-連番）で greige_rolls を updateOrCreate する
     *
     * @param  array{greige_sku?: string, qty_meters?: int|float, qty?: int|float, date: string}  $row  デモ入荷1行。生機SKU・メートル数・入荷日を読む
     * @param  \App\Models\Purchase\PurchaseOrder  $po  紐づく発注。反コードと purchase_order_id に使う
     * @param  \App\Models\Receiving\ReceivingLine  $receivingLine  この反が属する入荷明細。receiving_line_id に使う
     * @param  \Illuminate\Support\Carbon  $now  created_at と updated_at に入れる現在時刻
     * @return void  返り値は使わない。生機が無い、またはメートル数が 0 以下のときは DB を変えない
     */
    private function seedGreigeRolls(array $row, PurchaseOrder $po, ReceivingLine $receivingLine, Carbon $now): void
    {
        $greigeSku = (string) ($row['greige_sku'] ?? '');
        $greige = DemoData::findGreige($greigeSku);
        if ($greige === null) {
            return;
        }

        $meters = (float) ($row['qty_meters'] ?? $row['qty'] ?? 0);
        if ($meters <= 0) {
            return;
        }

        $nominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        $rollCount = max(1, (int) round($meters / $nominal));
        $perRoll = TanRollRecorder::distributeMeters($meters, $rollCount);

        foreach ($perRoll as $index => $actual) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $code = $greigeSku.'-'.$po->code.'-'.$seq;

            GreigeRoll::query()->updateOrCreate(
                ['code' => $code],
                [
                    'greige_id' => $greige->id,
                    'purchase_order_id' => $po->id,
                    'receiving_line_id' => $receivingLine->id,
                    'tan_qty' => 1.0,
                    'actual_qty_m' => $actual,
                    'nominal_meters' => $nominal,
                    'status' => GreigeRoll::STATUS_IN_STOCK,
                    'received_date' => $row['date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    /**
     * 製品の入荷1件を、反（product_rolls の1行＝製品生地1本）に分けて登録または更新する。
     *
     * 処理の流れ:
     * 1. product_id で製品マスタを探す。無いときは何もしない
     * 2. qty（メートル）が 0 以下なら何もしない
     * 3. 反数と実メートルの内訳を作り、反コード（SKU-発注コード-連番）で product_rolls を updateOrCreate する
     *
     * @param  array{product_id?: int, qty?: int|float, date: string}  $row  デモ入荷1行。製品ID・メートル数・入荷日を読む
     * @param  \App\Models\Purchase\PurchaseOrder  $po  紐づく発注。反コードと purchase_order_id に使う
     * @param  \App\Models\Receiving\ReceivingLine  $receivingLine  この反が属する入荷明細。receiving_line_id に使う
     * @param  \Illuminate\Support\Carbon  $now  created_at と updated_at に入れる現在時刻
     * @return void  返り値は使わない。製品が無い、またはメートル数が 0 以下のときは DB を変えない
     */
    private function seedProductRolls(array $row, PurchaseOrder $po, ReceivingLine $receivingLine, Carbon $now): void
    {
        $productId = (int) ($row['product_id'] ?? 0);
        $product = DemoData::findProduct($productId);
        if ($product === null) {
            return;
        }

        $meters = (float) ($row['qty'] ?? 0);
        if ($meters <= 0) {
            return;
        }

        $qtyTan = QtyHelper::tanCount($meters, $productId);

        $rollLines = TanRollRecorder::defaultRollLines($qtyTan, (int) round($meters));
        $nominal = (int) ($product->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);

        foreach ($rollLines as $index => $rollLine) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $code = $product->sku.'-'.$po->code.'-'.$seq;

            ProductRoll::query()->updateOrCreate(
                ['code' => $code],
                [
                    'product_id' => $productId,
                    'purchase_order_id' => $po->id,
                    'receiving_line_id' => $receivingLine->id,
                    'parent_greige_roll_id' => null,
                    'tan_qty' => $rollLine['tan_qty'],
                    'actual_qty_m' => $rollLine['actual_qty_m'],
                    'nominal_meters' => $nominal,
                    'status' => ProductRoll::STATUS_IN_STOCK,
                    'received_date' => $row['date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}

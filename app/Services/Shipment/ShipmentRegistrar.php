<?php

namespace App\Services\Shipment;

use App\Models\Order\Order;
use App\Models\Shipment\Shipment;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Support\Qty\QtyHelper;
use App\Support\Shipment\ShipmentPlan;
use Illuminate\Support\Facades\DB;

/**
 * 出荷実績の登録と在庫減少の共通入口。
 *
 * 呼び出し元: ShipmentController（Blade 手入力）、将来の工場・倉庫向け API コントローラー。
 * 設計メモ: memo/integration/factory-receiving.md（出荷連携は段階12予定）
 */
class ShipmentRegistrar
{
    /**
     * 受注に紐づく出荷を1件登録し、反引当・在庫減・受注出荷累計をトランザクション内で更新する。
     *
     * @param  int  $orderId  出荷元の受注 ID（行ロックして読む）
     * @param  float  $qtyTan  反数受注時の出荷反数（m 受注時は未使用）
     * @param  float|null  $qtyMeters  m 受注時の出荷 m 数
     * @param  string  $shippedDate  出荷日（DB 保存形式）
     * @param  string|null  $shipToName  届け先名称
     * @param  string|null  $note  備考
     * @param  float|null  $partialActualMeters  反数受注で部分出荷するときの実測 m（任意）
     * @return array{code: string, message: string, allocated_tan: float, allocated_m: float}  登録した出荷コードと引当結果
     *
     * @throws \RuntimeException  引当できる在庫がない場合
     */
    public static function register(
        int $orderId,
        float $qtyTan,
        ?float $qtyMeters,
        string $shippedDate,
        ?string $shipToName = null,
        ?string $note = null,
        ?float $partialActualMeters = null,
    ): array {
        return DB::transaction(function () use ($orderId, $qtyTan, $qtyMeters, $shippedDate, $shipToName, $note, $partialActualMeters) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $productId = (int) $order->product_id;
            $isMetersOrder = ($order->order_qty_mode ?? 'tan') === 'meters';

            $shipment = Shipment::query()->create([
                'code' => self::nextCode(),
                'order_id' => $orderId,
                'product_id' => $productId,
                'qty_tan' => 0,
                'qty_m' => 0,
                'shipped_date' => $shippedDate,
                'ship_to_name' => $shipToName,
                'note' => $note,
            ]);

            $noteRef = 'shipment:'.$shipment->id;

            if ($isMetersOrder) {
                $requiredM = (float) ($qtyMeters ?? 0);
                $result = ShipmentRollAllocator::allocateForMeters(
                    $productId,
                    $requiredM,
                    (int) $shipment->id,
                    $noteRef,
                );
            } else {
                $result = ShipmentRollAllocator::allocate(
                    $productId,
                    QtyHelper::roundTan($qtyTan),
                    (int) $shipment->id,
                    $noteRef,
                    $partialActualMeters,
                );
            }

            $allocatedTan = (float) ($result['allocated_tan'] ?? 0);
            $allocatedM = (float) ($result['allocated_m'] ?? 0);

            if ($allocatedTan <= 0 && $allocatedM <= 0) {
                throw new \RuntimeException('出荷できる在庫反がありません。');
            }

            $allocatedM = round($allocatedM, 2);

            $shipment->update([
                'qty_tan' => $allocatedTan,
                'qty_m' => $allocatedM,
            ]);

            $order->update([
                'shipped_qty_tan' => round((float) $order->shipped_qty_tan + $allocatedTan, 2),
                'shipped_qty_m' => round((float) $order->shipped_qty_m + $allocatedM, 2),
            ]);

            ShipmentPlan::recordShipment($orderId, $allocatedM, $allocatedTan);

            $messageQty = $isMetersOrder
                ? QtyHelper::formatMeters($allocatedM).'m（実測）'
                : QtyHelper::formatFromTan($allocatedTan, $productId);

            return [
                'code' => $shipment->code,
                'message' => '受注 '.$order->code.' から '.$messageQty.' を出荷登録し、在庫を減少しました。',
                'allocated_tan' => $allocatedTan,
                'allocated_m' => $allocatedM,
            ];
        });
    }

    /**
     * デモ Seeder 用に、既存出荷ヘッダへ FIFO 引当のみ再実行する（受注の shipped_qty は更新しない）。
     *
     * @param  Shipment  $shipment  引当対象の出荷（既に allocations がある場合は何もしない）
     * @param  int  $qtyM  引当する m 数
     * @return void
     */
    public static function replayDemoShipment(Shipment $shipment, int $qtyM): void
    {
        if ($shipment->allocations()->exists()) {
            return;
        }

        DB::transaction(function () use ($shipment, $qtyM) {
            $result = ShipmentRollAllocator::allocateForMeters(
                (int) $shipment->product_id,
                (float) $qtyM,
                (int) $shipment->id,
                'seed:'.$shipment->code,
            );

            $shipment->update([
                'qty_tan' => (float) ($result['allocated_tan'] ?? 0),
                'qty_m' => round((float) ($result['allocated_m'] ?? 0), 2),
            ]);
        });
    }

    /**
     * デモ環境用の出荷マスタ初期行定義を返す。
     *
     * @return list<array<string, mixed>>  Seeder が insert する連想配列のリスト
     */
    public static function demoRows(): array
    {
        return [
            ['id' => 1, 'code' => 'SH-2606-001', 'order_id' => 1, 'product_id' => 1, 'qty_m' => 120, 'shipped_date' => '2026-06-11', 'ship_to_name' => '東レ商事 滋賀倉庫', 'note' => '時間指定 午前中'],
            ['id' => 2, 'code' => 'SH-2606-002', 'order_id' => 4, 'product_id' => 2, 'qty_m' => 60, 'shipped_date' => '2026-06-12', 'ship_to_name' => 'ユニフォーム製作所 本社', 'note' => ''],
            ['id' => 3, 'code' => 'SH-2606-003', 'order_id' => 2, 'product_id' => 3, 'qty_m' => 75, 'shipped_date' => '2026-06-14', 'ship_to_name' => 'アパレル東京 物流センター', 'note' => '分納の1回目'],
            ['id' => 4, 'code' => 'SH-2606-004', 'order_id' => 6, 'product_id' => 1, 'qty_m' => 50, 'shipped_date' => '2026-06-15', 'ship_to_name' => 'アパレル東京 物流センター', 'note' => ''],
        ];
    }

    /**
     * 当月連番の出荷コード（SH-yymm-NNN）を採番する。
     *
     * @return string  未使用の出荷 code
     */
    private static function nextCode(): string
    {
        $prefix = 'SH-'.date('ym').'-';
        $seq = Shipment::query()->where('code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}

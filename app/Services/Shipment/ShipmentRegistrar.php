<?php

namespace App\Services\Shipment;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Support\QtyHelper;
use App\Support\ShipmentPlan;
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
     * @return array{code: string, message: string, allocated_tan: float, allocated_m: float}
     */
    public static function register(
        int $orderId,
        float $qtyTan,
        ?int $qtyMeters,
        string $shippedDate,
        ?string $shipToName = null,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($orderId, $qtyTan, $qtyMeters, $shippedDate, $shipToName, $note) {
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
                    QtyHelper::roundIntegerTan($qtyTan),
                    (int) $shipment->id,
                    $noteRef,
                );
            }

            $allocatedTan = (float) ($result['allocated_tan'] ?? 0);
            $allocatedM = (float) ($result['allocated_m'] ?? 0);

            if ($allocatedTan <= 0 && $allocatedM <= 0) {
                throw new \RuntimeException('出荷できる在庫反がありません。');
            }

            $allocatedMInt = (int) round($allocatedM);

            $shipment->update([
                'qty_tan' => $allocatedTan,
                'qty_m' => $allocatedMInt,
            ]);

            $order->update([
                'shipped_qty_tan' => round((float) $order->shipped_qty_tan + $allocatedTan, 2),
                'shipped_qty_m' => (int) $order->shipped_qty_m + $allocatedMInt,
            ]);

            ShipmentPlan::recordShipment($orderId, $allocatedM, $allocatedTan);

            $messageQty = $isMetersOrder
                ? number_format($allocatedMInt).'m（実測）'
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
     * デモ Seeder 用：出荷ヘッダ作成後に FIFO で反消費のみ行う（受注 shipped_qty は触らない）。
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
                'qty_m' => (int) round((float) ($result['allocated_m'] ?? 0)),
            ]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function demoRows(): array
    {
        return [
            ['id' => 1, 'code' => 'SH-2606-001', 'order_id' => 1, 'product_id' => 1, 'qty_m' => 120, 'shipped_date' => '2026-06-11', 'ship_to_name' => '東レ商事 滋賀倉庫', 'note' => '時間指定 午前中'],
            ['id' => 2, 'code' => 'SH-2606-002', 'order_id' => 4, 'product_id' => 2, 'qty_m' => 60, 'shipped_date' => '2026-06-12', 'ship_to_name' => 'ユニフォーム製作所 本社', 'note' => ''],
            ['id' => 3, 'code' => 'SH-2606-003', 'order_id' => 2, 'product_id' => 3, 'qty_m' => 80, 'shipped_date' => '2026-06-14', 'ship_to_name' => 'アパレル東京 物流センター', 'note' => '分納の1回目'],
            ['id' => 4, 'code' => 'SH-2606-004', 'order_id' => 6, 'product_id' => 1, 'qty_m' => 40, 'shipped_date' => '2026-06-15', 'ship_to_name' => 'アパレル東京 物流センター', 'note' => ''],
        ];
    }

    private static function nextCode(): string
    {
        $prefix = 'SH-'.date('ym').'-';
        $seq = Shipment::query()->where('code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}

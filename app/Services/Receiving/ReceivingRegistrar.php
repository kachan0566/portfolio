<?php

namespace App\Services\Receiving;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Fabric\TanRollRecorder;
use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\StockAllocation;
use App\Support\YarnInventory;
use Illuminate\Support\Facades\DB;

class ReceivingRegistrar
{
    /**
     * @param  list<array{tan_qty: float, actual_qty_m: float}>  $rollLines
     * @return array{code: string, message: string, converted: list<array<string, mixed>>}
     */
    public static function register(
        int $poId,
        string $date,
        string $poType,
        float $qtyKg = 0,
        float $qtyTan = 0,
        int $qtyMeters = 0,
        array $rollLines = [],
    ): array {
        return DB::transaction(function () use ($poId, $date, $poType, $qtyKg, $qtyTan, $qtyMeters, $rollLines) {
            $po = PurchaseOrder::query()
                ->with(['lines.material', 'lines.greige', 'lines.product', 'supplier'])
                ->findOrFail($poId);

            if ((string) $po->type !== $poType) {
                throw new \InvalidArgumentException('発注種別が一致しません。');
            }

            $poLine = $po->lines()->where('line_no', 1)->first();
            if ($poLine === null) {
                throw new \InvalidArgumentException('発注明細が見つかりません。');
            }

            $code = self::nextCode();
            $receiving = Receiving::query()->create([
                'code' => $code,
                'received_date' => $date,
            ]);

            $receivingLine = ReceivingLine::query()->create([
                'receiving_id' => $receiving->id,
                'purchase_order_line_id' => $poLine->id,
                'line_no' => 1,
            ]);

            if ($poType === PurchaseOrderType::YARN) {
                $receivingLine->update([
                    'qty_kg' => round($qtyKg, 3),
                    'qty_tan' => 0,
                    'qty_m' => 0,
                ]);
                YarnInventory::addStockKg((int) $poLine->material_id, $qtyKg);
            } elseif ($poType === PurchaseOrderType::GREIGE) {
                $greigeSku = (string) ($poLine->greige?->sku ?? '');
                TanRollRecorder::recordWeavingFromLines(
                    $poId,
                    $greigeSku,
                    $rollLines,
                    $date,
                    $receiving->id,
                    $receivingLine->id,
                );
                ReceivingLineTotals::sync($receivingLine->fresh());
            } else {
                TanRollRecorder::recordProductReceivingFromLines(
                    $poId,
                    (int) $poLine->product_id,
                    $rollLines,
                    $date,
                    $receiving->id,
                    $receivingLine->id,
                );
                ReceivingLineTotals::sync($receivingLine->fresh());
            }

            PurchaseOrderLineReceiver::syncFromReceivingLine($receivingLine->fresh());

            $message = "入荷 {$code} を登録しました。";
            $converted = [];

            if ($poType === PurchaseOrderType::PRODUCT) {
                $converted = StockAllocation::convertOnReceiving($poId, $qtyMeters, $code);
                $message = "入荷 {$code} を登録し、製品在庫を {$qtyMeters}m 増加しました。";
                if ($converted !== []) {
                    $details = collect($converted)->map(function ($c) {
                        $order = DemoData::orders()->firstWhere('id', $c['order_id']);

                        return ($order?->code ?? '#'.$c['order_id'])." {$c['qty']}m";
                    })->implode('、');
                    $message .= " 発注引当から現在庫引当へ自動変換: {$details}";
                }
            } elseif ($poType === PurchaseOrderType::YARN) {
                $message = "入荷 {$code} を登録し、糸在庫を ".number_format($qtyKg, 2).'kg 増加しました。';
            } elseif ($poType === PurchaseOrderType::GREIGE) {
                $line = $receivingLine->fresh();
                $message = "入荷 {$code} を登録し、染工場の生機在庫を {$line->qty_tan}反（実測 {$line->qty_m}m）増加しました。反明細に織り上がり実測を記録しました。";
            }

            return [
                'code' => $code,
                'message' => $message,
                'converted' => $converted,
            ];
        });
    }

    private static function nextCode(): string
    {
        $seq = Receiving::query()->count() + 1;

        return 'RC-'.date('ymd').'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}

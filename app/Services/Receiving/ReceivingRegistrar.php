<?php

namespace App\Services\Receiving;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Fabric\TanRollRecorder;
use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use App\Support\StockAllocation;
use App\Support\YarnInventory;
use Illuminate\Support\Facades\DB;

class ReceivingRegistrar
{
    /**
     * @param  list<array{
     *   purchase_order_line_id: int,
     *   qty_kg?: float,
     *   qty_tan?: float,
     *   qty_meters?: int,
     *   roll_lines?: list<array{tan_qty: float, actual_qty_m: float}>
     * }>  $entries
     * @return array{code: string, message: string, converted: list<array<string, mixed>>}
     */
    public static function register(
        int $poId,
        string $date,
        string $poType,
        array $entries = [],
        // Legacy single-line parameters (backward compatible)
        float $qtyKg = 0,
        float $qtyTan = 0,
        int $qtyMeters = 0,
        array $rollLines = [],
    ): array {
        if ($entries === [] && ($qtyKg > 0 || $qtyTan > 0 || $qtyMeters > 0 || $rollLines !== [])) {
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $poId)
                ->orderBy('line_no')
                ->first();
            if ($poLine === null) {
                throw new \InvalidArgumentException('発注明細が見つかりません。');
            }

            $entry = ['purchase_order_line_id' => $poLine->id];
            if ($poType === PurchaseOrderType::YARN) {
                $entry['qty_kg'] = $qtyKg;
            } else {
                $entry['qty_tan'] = $qtyTan;
                $entry['qty_meters'] = $qtyMeters;
                $entry['roll_lines'] = $rollLines;
            }
            $entries = [$entry];
        }

        if ($entries === []) {
            throw new \InvalidArgumentException('入荷明細が指定されていません。');
        }

        return DB::transaction(function () use ($poId, $date, $poType, $entries) {
            $po = PurchaseOrder::query()
                ->with(['lines.material', 'lines.greige', 'lines.product', 'supplier'])
                ->findOrFail($poId);

            if ((string) $po->type !== $poType) {
                throw new \InvalidArgumentException('発注種別が一致しません。');
            }

            $poLineIds = $po->lines->pluck('id')->all();
            foreach ($entries as $entry) {
                $lineId = (int) ($entry['purchase_order_line_id'] ?? 0);
                if (! in_array($lineId, $poLineIds, true)) {
                    throw new \InvalidArgumentException('発注明細がこの発注に属していません。');
                }
            }

            $code = self::nextCode();
            $receiving = Receiving::query()->create([
                'code' => $code,
                'received_date' => $date,
            ]);

            $converted = [];
            $lineNo = 0;
            $totalProductMeters = 0;

            foreach ($entries as $entry) {
                $lineNo++;
                $poLine = $po->lines->firstWhere('id', (int) $entry['purchase_order_line_id']);
                if ($poLine === null) {
                    throw new \InvalidArgumentException('発注明細が見つかりません。');
                }

                $receivingLine = ReceivingLine::query()->create([
                    'receiving_id' => $receiving->id,
                    'purchase_order_line_id' => $poLine->id,
                    'line_no' => $lineNo,
                ]);

                if ($poType === PurchaseOrderType::YARN) {
                    $qtyKg = round((float) ($entry['qty_kg'] ?? 0), 3);
                    $receivingLine->update([
                        'qty_kg' => $qtyKg,
                        'qty_tan' => 0,
                        'qty_m' => 0,
                    ]);
                    YarnInventory::addStockKg((int) $poLine->material_id, $qtyKg);
                } elseif ($poType === PurchaseOrderType::GREIGE) {
                    $greigeSku = (string) ($poLine->greige?->sku ?? '');
                    $rollLines = (array) ($entry['roll_lines'] ?? []);
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
                    $rollLines = (array) ($entry['roll_lines'] ?? []);
                    TanRollRecorder::recordProductReceivingFromLines(
                        $poId,
                        (int) $poLine->product_id,
                        $rollLines,
                        $date,
                        $receiving->id,
                        $receivingLine->id,
                    );
                    ReceivingLineTotals::sync($receivingLine->fresh());
                    $totalProductMeters += (int) ($receivingLine->fresh()->qty_m ?? 0);
                }

                PurchaseOrderLineReceiver::syncFromReceivingLine($receivingLine->fresh());
            }

            $message = "入荷 {$code} を登録しました。（明細 {$lineNo} 行）";

            if ($poType === PurchaseOrderType::PRODUCT && $totalProductMeters > 0) {
                $converted = StockAllocation::convertOnReceiving($poId, $totalProductMeters, $code);
                $message = "入荷 {$code} を登録し、製品在庫を {$totalProductMeters}m 増加しました。（明細 {$lineNo} 行）";
                if ($converted !== []) {
                    $details = collect($converted)->map(function ($c) {
                        $order = DemoData::orders()->firstWhere('id', $c['order_id']);

                        return ($order?->code ?? '#'.$c['order_id'])." {$c['qty']}m";
                    })->implode('、');
                    $message .= " 発注引当から現在庫引当へ自動変換: {$details}";
                }
            } elseif ($poType === PurchaseOrderType::YARN) {
                $totalKg = (float) ReceivingLine::query()
                    ->where('receiving_id', $receiving->id)
                    ->sum('qty_kg');
                $message = "入荷 {$code} を登録し、糸在庫を ".number_format($totalKg, 2)."kg 増加しました。（明細 {$lineNo} 行）";
            } elseif ($poType === PurchaseOrderType::GREIGE) {
                $lines = ReceivingLine::query()->where('receiving_id', $receiving->id)->get();
                $tan = (float) $lines->sum(fn ($row) => (float) $row->qty_tan);
                $meters = (int) $lines->sum(fn ($row) => (int) $row->qty_m);
                $message = "入荷 {$code} を登録し、染工場の生機在庫を {$tan}反（実測 {$meters}m）増加しました。（明細 {$lineNo} 行）";
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

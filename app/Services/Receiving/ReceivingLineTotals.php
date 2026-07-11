<?php

namespace App\Services\Receiving;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\ReceivingLine;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;

class ReceivingLineTotals
{
    public static function sync(ReceivingLine $line): void
    {
        $line->loadMissing('purchaseOrderLine.purchaseOrder');
        $poLine = $line->purchaseOrderLine;
        $type = (string) ($poLine?->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        $payload = match ($type) {
            PurchaseOrderType::GREIGE => self::totalsFromGreigeRolls($line),
            PurchaseOrderType::PRODUCT => self::totalsFromProductRolls($line),
            PurchaseOrderType::YARN => [
                'qty_tan' => 0,
                'qty_m' => 0,
                'qty_kg' => (float) $line->qty_kg,
            ],
            default => ['qty_tan' => 0, 'qty_m' => 0, 'qty_kg' => 0],
        };

        $line->update($payload);
    }

    /**
     * @return array{qty_tan: float, qty_m: int, qty_kg: float}
     */
    private static function totalsFromGreigeRolls(ReceivingLine $line): array
    {
        $rolls = GreigeRoll::query()->where('receiving_line_id', $line->id)->get();
        $tan = QtyHelper::roundReceivingTan((float) $rolls->sum(fn ($roll) => (float) $roll->tan_qty));
        $meters = (int) round((float) $rolls->sum(fn ($roll) => (float) $roll->actual_qty_m));

        return ['qty_tan' => $tan, 'qty_m' => $meters, 'qty_kg' => 0];
    }

    /**
     * @return array{qty_tan: float, qty_m: int, qty_kg: float}
     */
    private static function totalsFromProductRolls(ReceivingLine $line): array
    {
        $rolls = ProductRoll::query()->where('receiving_line_id', $line->id)->get();
        $tan = QtyHelper::roundReceivingTan((float) $rolls->sum(fn ($roll) => (float) $roll->tan_qty));
        $meters = (int) round((float) $rolls->sum(fn ($roll) => (float) $roll->actual_qty_m));

        return ['qty_tan' => $tan, 'qty_m' => $meters, 'qty_kg' => 0];
    }
}

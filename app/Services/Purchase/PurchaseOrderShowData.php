<?php

namespace App\Services\Purchase;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingLine;
use App\Support\PurchaseOrderType;

class PurchaseOrderShowData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function orderLines(PurchaseOrder $po): array
    {
        return $po->lines
            ->sortBy('line_no')
            ->map(fn (PurchaseOrderLine $line) => $line->toDisplayRow())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function receivingBySku(PurchaseOrder $po): array
    {
        $type = (string) $po->type;
        $groups = [];

        foreach ($po->lines->sortBy('line_no') as $line) {
            $key = self::skuKey($line, $type);
            if ($key === '') {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = self::emptySummaryRow($line, $type, $key);
            }

            if ($type === PurchaseOrderType::YARN) {
                $groups[$key]['ordered_kg'] += (float) ($line->qty_kg ?? 0);
                $groups[$key]['received_kg'] += (float) ($line->received_qty_kg ?? 0);
                $groups[$key]['remaining_kg'] = max(
                    0.0,
                    $groups[$key]['ordered_kg'] - $groups[$key]['received_kg'],
                );
            } else {
                $groups[$key]['ordered_tan'] += $line->orderedTan();
                $groups[$key]['received_tan'] += $line->receivedTan();
                $groups[$key]['received_m'] += (int) ($line->received_qty_m ?? 0);
                $groups[$key]['remaining_tan'] = max(
                    0.0,
                    $groups[$key]['ordered_tan'] - $groups[$key]['received_tan'],
                );
            }
        }

        return array_values($groups);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function receivedDetailRows(PurchaseOrder $po): array
    {
        $type = (string) $po->type;

        if ($type === PurchaseOrderType::YARN) {
            return self::yarnReceivingRows($po);
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return self::greigeRollRows($po);
        }

        return self::productRollRows($po);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function yarnReceivingRows(PurchaseOrder $po): array
    {
        $rows = [];

        $receivingLines = ReceivingLine::query()
            ->whereIn('purchase_order_line_id', $po->lines->pluck('id'))
            ->with(['purchaseOrderLine.material', 'receiving'])
            ->orderBy('id')
            ->get();

        foreach ($receivingLines as $receivingLine) {
            $qtyKg = (float) ($receivingLine->qty_kg ?? 0);
            if ($qtyKg <= 0) {
                continue;
            }

            $material = $receivingLine->purchaseOrderLine?->material;
            $rows[] = [
                'code' => $receivingLine->receiving?->code ?? '—',
                'sku_label' => $material
                    ? $material->sku.'（'.$material->name.'）'
                    : '—',
                'tan_qty' => null,
                'actual_m' => null,
                'qty_kg' => $qtyKg,
                'measured_at' => $receivingLine->receiving?->received_date?->toDateString() ?? '—',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function greigeRollRows(PurchaseOrder $po): array
    {
        return GreigeRoll::query()
            ->where('purchase_order_id', $po->id)
            ->with('greige')
            ->orderBy('id')
            ->get()
            ->map(function (GreigeRoll $roll) {
                $greige = $roll->greige;

                return [
                    'code' => $roll->code,
                    'sku_label' => $greige
                        ? $greige->sku.'（'.$greige->name.'）'
                        : '—',
                    'tan_qty' => (float) $roll->tan_qty,
                    'actual_m' => (float) $roll->actual_qty_m,
                    'qty_kg' => null,
                    'measured_at' => $roll->received_date?->toDateString() ?? '—',
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function productRollRows(PurchaseOrder $po): array
    {
        return ProductRoll::query()
            ->where('purchase_order_id', $po->id)
            ->with('product')
            ->orderBy('id')
            ->get()
            ->map(function (ProductRoll $roll) {
                $product = $roll->product;
                $color = $product?->color ?? '';
                $skuLabel = $product
                    ? $product->sku.($color !== '' ? '（'.$color.'）' : '')
                    : '—';

                return [
                    'code' => $roll->code,
                    'sku_label' => $skuLabel,
                    'tan_qty' => (float) $roll->tan_qty,
                    'actual_m' => (float) $roll->actual_qty_m,
                    'qty_kg' => null,
                    'measured_at' => $roll->received_date?->toDateString() ?? '—',
                ];
            })
            ->all();
    }

    private static function skuKey(PurchaseOrderLine $line, string $type): string
    {
        return match ($type) {
            PurchaseOrderType::YARN => (string) ($line->material?->sku ?? ''),
            PurchaseOrderType::GREIGE => (string) ($line->greige?->sku ?? ''),
            default => (string) ($line->product?->sku ?? ''),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySummaryRow(PurchaseOrderLine $line, string $type, string $key): array
    {
        if ($type === PurchaseOrderType::YARN) {
            return [
                'sku_key' => $key,
                'sku_label' => $line->material
                    ? $line->material->sku.'（'.$line->material->name.'）'
                    : $key,
                'ordered_kg' => 0.0,
                'received_kg' => 0.0,
                'remaining_kg' => 0.0,
            ];
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return [
                'sku_key' => $key,
                'sku_label' => $line->greige
                    ? $line->greige->sku.'（'.$line->greige->name.'）'
                    : $key,
                'ordered_tan' => 0.0,
                'received_tan' => 0.0,
                'received_m' => 0,
                'remaining_tan' => 0.0,
            ];
        }

        $product = $line->product;
        $color = $product?->color ?? '';

        return [
            'sku_key' => $key,
            'sku_label' => $product
                ? $product->sku.($color !== '' ? '（'.$color.'）' : '')
                : $key,
            'ordered_tan' => 0.0,
            'received_tan' => 0.0,
            'received_m' => 0,
            'remaining_tan' => 0.0,
        ];
    }
}

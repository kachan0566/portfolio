<?php

namespace App\Support;

use App\Support\MasterCatalog;
use App\Support\PurchaseOrderDisplay;

use Illuminate\Support\Collection;

/**
 * 受注に紐づく糸・生機・製品発注の生産状況行を組み立てる。
 */
class OrderProductionStatus
{
    /**
     * @return Collection<int, object{
     *     sort: int,
     *     type: string,
     *     type_label: string,
     *     code: string,
     *     sku: string,
     *     label: string,
     *     expected_arrival: string,
     *     purchase_id: int
     * }>
     */
    public static function rowsForOrder(object $order): Collection
    {
        $product = MasterCatalog::findProduct((int) ($order->product_id ?? 0));
        if ($product === null) {
            return collect();
        }

        $greigeSku = (string) ($product->greige_sku ?? '');
        $materialIds = GreigeYarnReadiness::materialIdsForGreigeSku($greigeSku);
        $orderId = (int) ($order->id ?? 0);
        $rows = collect();

        foreach (DemoData::purchaseOrders() as $po) {
            if (! PurchaseOrderStatus::isActive((string) ($po->status ?? ''))) {
                continue;
            }

            $type = (string) ($po->type ?? '');
            $include = match ($type) {
                PurchaseOrderType::YARN => $materialIds !== []
                    && in_array((int) ($po->material_id ?? 0), $materialIds, true),
                PurchaseOrderType::GREIGE => $greigeSku !== ''
                    && (string) ($po->sku ?? '') === $greigeSku,
                PurchaseOrderType::PRODUCT => (int) ($po->order_id ?? 0) === $orderId,
                default => false,
            };

            if (! $include) {
                continue;
            }

            $rows->push(self::toRow($po));
        }

        return $rows
            ->unique('purchase_id')
            ->sortBy([
                ['sort', 'asc'],
                ['code', 'asc'],
            ])
            ->values();
    }

    private static function toRow(object $po): object
    {
        $type = (string) ($po->type ?? '');

        return (object) [
            'sort' => match ($type) {
                PurchaseOrderType::YARN => 1,
                PurchaseOrderType::GREIGE => 2,
                PurchaseOrderType::PRODUCT => 3,
                default => 9,
            },
            'type' => $type,
            'type_label' => PurchaseOrderType::label($type),
            'code' => (string) ($po->code ?? ''),
            'sku' => (string) ($po->sku ?? ''),
            'label' => PurchaseOrderDisplay::label($po),
            'expected_arrival' => DemoData::expectedArrivalDate($po) ?: '—',
            'purchase_id' => (int) ($po->id ?? 0),
        ];
    }
}

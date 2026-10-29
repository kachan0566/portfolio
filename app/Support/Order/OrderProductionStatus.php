<?php

namespace App\Support\Order;

use App\Support\Master\MasterCatalog;
use App\Support\Purchase\PurchaseOrderDisplay;

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
        $product = \App\Support\Master\MasterCatalog::findProduct((int) ($order->product_id ?? 0));
        if ($product === null) {
            return collect();
        }

        $greigeSku = (string) ($product->greige_sku ?? '');
        $materialIds = \App\Support\Inventory\GreigeYarnReadiness::materialIdsForGreigeSku($greigeSku);
        $orderId = (int) ($order->id ?? 0);
        $rows = collect();

        foreach (\App\Support\Core\DemoData::purchaseOrders() as $po) {
            if (! \App\Support\Purchase\PurchaseOrderStatus::isActive((string) ($po->status ?? ''))) {
                continue;
            }

            $type = (string) ($po->type ?? '');
            $include = match ($type) {
                \App\Support\Purchase\PurchaseOrderType::YARN => $materialIds !== []
                    && in_array((int) ($po->material_id ?? 0), $materialIds, true),
                \App\Support\Purchase\PurchaseOrderType::GREIGE => $greigeSku !== ''
                    && (string) ($po->sku ?? '') === $greigeSku,
                \App\Support\Purchase\PurchaseOrderType::PRODUCT => (int) ($po->order_id ?? 0) === $orderId,
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
                \App\Support\Purchase\PurchaseOrderType::YARN => 1,
                \App\Support\Purchase\PurchaseOrderType::GREIGE => 2,
                \App\Support\Purchase\PurchaseOrderType::PRODUCT => 3,
                default => 9,
            },
            'type' => $type,
            'type_label' => \App\Support\Purchase\PurchaseOrderType::label($type),
            'code' => (string) ($po->code ?? ''),
            'sku' => (string) ($po->sku ?? ''),
            'label' => \App\Support\Purchase\PurchaseOrderDisplay::label($po),
            'expected_arrival' => \App\Support\Core\DemoData::expectedArrivalDate($po) ?: '—',
            'purchase_id' => (int) ($po->id ?? 0),
        ];
    }
}

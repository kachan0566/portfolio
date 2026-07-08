<?php

namespace App\Models;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\PurchaseOrderDisplay;
use App\Support\PurchaseOrderLink;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[Fillable([
    'code',
    'type',
    'status',
    'supplier_id',
    'ship_to_id',
    'order_id',
    'order_date',
    'due_date',
    'arrival_memo',
])]
class PurchaseOrder extends Model
{
    protected function casts(): array
    {
        return [
            'supplier_id' => 'integer',
            'ship_to_id' => 'integer',
            'order_id' => 'integer',
            'order_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<ShipTo, $this> */
    public function shipTo(): BelongsTo
    {
        return $this->belongsTo(ShipTo::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasOne<YarnPurchaseOrder, $this> */
    public function yarnDetail(): HasOne
    {
        return $this->hasOne(YarnPurchaseOrder::class);
    }

    /** @return HasOne<GreigePurchaseOrder, $this> */
    public function greigeDetail(): HasOne
    {
        return $this->hasOne(GreigePurchaseOrder::class);
    }

    /** @return HasOne<ProductPurchaseOrder, $this> */
    public function productDetail(): HasOne
    {
        return $this->hasOne(ProductPurchaseOrder::class);
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with([
                'supplier',
                'shipTo',
                'order.customer',
                'yarnDetail.material',
                'greigeDetail.greige',
                'productDetail.product.greige',
            ])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $po) => $po->toDisplayObject());
    }

    public static function findForDisplay(int $id): ?object
    {
        $po = self::query()
            ->with([
                'supplier',
                'shipTo',
                'order.customer',
                'yarnDetail.material',
                'greigeDetail.greige',
                'productDetail.product.greige',
            ])
            ->find($id);

        return $po?->toDisplayObject();
    }

    public function toDisplayObject(): object
    {
        $type = $this->type;
        $status = $this->status ?? PurchaseOrderStatus::ORDERED;

        $row = [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $type,
            'type_label' => PurchaseOrderType::label($type),
            'status' => $status,
            'status_label' => PurchaseOrderStatus::label($type, $status),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier?->name ?? '—',
            'supplier_type' => $this->supplier?->type,
            'ship_to_id' => $this->ship_to_id,
            'ship_to' => $this->shipTo?->name ?? '—',
            'ship_to_type' => $this->shipTo?->type,
            'order_date' => $this->order_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'eta' => $this->due_date?->toDateString(),
            'arrival_memo' => (string) ($this->arrival_memo ?? ''),
        ];

        $linkedOrderId = PurchaseOrderLink::orderIdForPurchase((int) $this->id, $this->order_id);
        $row['order_id'] = $linkedOrderId;
        $row['order_code'] = $this->order?->code;
        $row['customer'] = $this->order?->customer?->name;

        if ($type === PurchaseOrderType::YARN) {
            $detail = $this->yarnDetail;
            $material = $detail?->material;
            $row['material_id'] = $detail?->material_id;
            $row['sku'] = $material?->sku ?? '—';
            $row['product'] = $material?->name ?? '—';
            $row['unit'] = 'kg';
            $row['qty_kg'] = (float) ($detail?->qty_kg ?? 0);
            $row['qty'] = $row['qty_kg'];
            $row['received_kg'] = (float) ($detail?->received_qty_kg ?? 0);
            $row['received'] = $row['received_kg'];
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $detail = $this->greigeDetail;
            $greige = $detail?->greige;
            $sku = $greige?->sku ?? '—';
            $row['greige_sku'] = $sku;
            $row['sku'] = $sku;
            $row['product'] = $greige?->name ?? '—';
            $row['unit'] = '反';
            $row['qty_meters'] = (int) ($detail?->qty_meters ?? 0);
            $row['qty'] = $row['qty_meters'];
            $row['qty_tan'] = (float) ($detail?->qty_tan ?? 0);
            $row['meters_per_tan'] = (int) ($detail?->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
            $row['received'] = (int) ($detail?->received_qty_m ?? 0);
            $row['yarn_requirements'] = DemoData::greigeYarnRequirements($sku, $row['qty_meters']);
            $row['manual_stage'] = DemoState::effectivePoStage((int) $this->id)
                ?: PurchaseOrderStages::normalizeGreigeManualStage($detail?->stage);
        } else {
            $detail = $this->productDetail;
            $product = $detail?->product;
            $productId = (int) ($detail?->product_id ?? 0);
            $row['product_id'] = $productId;
            $row['product'] = $product?->sku ?? '—';
            $row['sku'] = $product?->sku ?? '—';
            $row['unit'] = $product?->unit ?? '反';
            $row['qty_meters'] = (int) ($detail?->qty_meters ?? 0);
            $row['qty_tan'] = (float) ($detail?->qty_tan ?? 0);
            if ($row['qty_tan'] <= 0 && $row['qty_meters'] > 0) {
                $row['qty_tan'] = QtyHelper::tanCount($row['qty_meters'], $productId);
            }
            if ($row['qty_meters'] <= 0 && $row['qty_tan'] > 0) {
                $row['qty_meters'] = QtyHelper::metersFromTan($row['qty_tan'], $productId);
            }
            $row['qty'] = $row['qty_meters'];
            $row['received'] = (int) ($detail?->received_qty_m ?? 0);
            $row['finish_date'] = $detail?->finish_date?->toDateString();
            $row['contact_date'] = $detail?->contact_date?->toDateString();
            $row['manual_stage'] = DemoState::effectivePoStage((int) $this->id)
                ?: PurchaseOrderStages::normalizeProductManualStage($detail?->stage);
        }

        $po = (object) $row;
        $row['stage'] = PurchaseOrderDisplay::label($po);
        $row['progress'] = PurchaseOrderDisplay::progressPercent($po);

        return (object) $row;
    }
}

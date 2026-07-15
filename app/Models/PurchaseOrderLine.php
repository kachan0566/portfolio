<?php

namespace App\Models;

use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'line_no',
    'material_id',
    'greige_id',
    'product_id',
    'qty_kg',
    'received_qty_kg',
    'qty_tan',
    'meters_per_tan',
    'qty_meters',
    'received_qty_tan',
    'received_qty_m',
    'stage',
    'finish_date',
    'contact_date',
])]
class PurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'line_no' => 'integer',
            'material_id' => 'integer',
            'greige_id' => 'integer',
            'product_id' => 'integer',
            'qty_kg' => 'decimal:3',
            'received_qty_kg' => 'decimal:3',
            'qty_tan' => 'integer',
            'meters_per_tan' => 'integer',
            'qty_meters' => 'integer',
            'received_qty_tan' => 'decimal:2',
            'received_qty_m' => 'integer',
            'finish_date' => 'date',
            'contact_date' => 'date',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return HasMany<ReceivingLine, $this> */
    public function receivingLines(): HasMany
    {
        return $this->hasMany(ReceivingLine::class, 'purchase_order_line_id');
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function skuLabel(): string
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        return match ($type) {
            PurchaseOrderType::YARN => $this->material?->sku ?? '—',
            PurchaseOrderType::GREIGE => $this->greige?->sku ?? '—',
            default => $this->product?->sku ?? '—',
        };
    }

    public function orderedQty(): float
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        return match ($type) {
            PurchaseOrderType::YARN => (float) ($this->qty_kg ?? 0),
            default => (float) ($this->qty_meters ?? 0),
        };
    }

    public function receivedQty(): float
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        return match ($type) {
            PurchaseOrderType::YARN => (float) ($this->received_qty_kg ?? 0),
            default => (float) ($this->received_qty_m ?? 0),
        };
    }

    public function remainingQty(): float
    {
        return max(0.0, $this->orderedQty() - $this->receivedQty());
    }

    public function orderedTan(): float
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        if ($type === PurchaseOrderType::YARN) {
            return 0.0;
        }

        if ((float) ($this->qty_tan ?? 0) > 0) {
            return (float) $this->qty_tan;
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return QtyHelper::tanCount(
                (int) ($this->qty_meters ?? 0),
                null,
                true,
                $this->greige?->sku,
            );
        }

        return QtyHelper::tanCount((int) ($this->qty_meters ?? 0), (int) ($this->product_id ?? 0));
    }

    public function receivedTan(): float
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        if ($type === PurchaseOrderType::YARN) {
            return 0.0;
        }

        if ((float) ($this->received_qty_tan ?? 0) > 0) {
            return (float) $this->received_qty_tan;
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return QtyHelper::tanCount(
                (int) ($this->received_qty_m ?? 0),
                null,
                true,
                $this->greige?->sku,
            );
        }

        return QtyHelper::tanCount((int) ($this->received_qty_m ?? 0), (int) ($this->product_id ?? 0));
    }

    public function remainingTan(): float
    {
        return max(0.0, $this->orderedTan() - $this->receivedTan());
    }

    public function metersPerTanValue(): int
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        if ($type === PurchaseOrderType::GREIGE) {
            return (int) ($this->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        }

        if ($type === PurchaseOrderType::PRODUCT) {
            return (int) ($this->product?->meters_per_tan ?? 50);
        }

        return 0;
    }

    /** @return array<string, mixed> */
    public function toReceivingMeta(): array
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);
        $remaining = DemoState::poLineRemaining((int) $this->id);

        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'sku' => $this->skuLabel(),
            'remaining' => $remaining,
            'meters_per_tan' => $this->metersPerTanValue(),
            'product_id' => $this->product_id,
            'greige_sku' => $this->greige?->sku,
            'type' => $type,
        ];
    }

    /** @return array<string, mixed> */
    public function toDisplayRow(): array
    {
        $type = (string) ($this->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        $row = [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'sku' => $this->skuLabel(),
            'ordered' => $this->orderedQty(),
            'received' => $this->receivedQty(),
            'remaining' => $this->remainingQty(),
            'ordered_tan' => $this->orderedTan(),
            'received_tan' => $this->receivedTan(),
            'remaining_tan' => $this->remainingTan(),
        ];

        if ($type === PurchaseOrderType::YARN) {
            $row['unit'] = 'kg';
            $row['material_name'] = $this->material?->name ?? '—';
            $row['ordered_kg'] = (float) ($this->qty_kg ?? 0);
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $row['unit'] = '反';
            $row['greige_sku'] = $this->greige?->sku ?? '—';
            $row['greige_name'] = $this->greige?->name ?? '—';
            $row['meters_per_tan'] = $this->metersPerTanValue();
        } else {
            $row['unit'] = '反';
            $row['product_id'] = $this->product_id;
            $row['product_sku'] = $this->product?->sku ?? '—';
            $row['product_color'] = $this->product?->color ?? '';
            $row['greige_sku'] = $this->product?->greige?->sku ?? '—';
            $row['greige_name'] = $this->product?->greige?->name ?? '—';
            $row['meters_per_tan'] = $this->metersPerTanValue();
        }

        return $row;
    }
}

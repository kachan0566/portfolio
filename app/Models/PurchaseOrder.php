<?php

namespace App\Models;

use App\Support\DemoData;
use App\Support\PurchaseOrderDisplay;
use App\Support\PurchaseOrderLineDisplay;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function primaryLine(): ?PurchaseOrderLine
    {
        return $this->lines->sortBy('line_no')->first();
    }

    public function receivedQty(): float
    {
        return match ((string) $this->type) {
            PurchaseOrderType::YARN => (float) $this->lines->sum(
                fn ($line) => (float) ($line->received_qty_kg ?? 0),
            ),
            default => (float) $this->lines->sum(
                fn ($line) => (int) ($line->received_qty_m ?? 0),
            ),
        };
    }

    public function orderedQty(): float
    {
        return match ((string) $this->type) {
            PurchaseOrderType::YARN => (float) $this->lines->sum(
                fn ($line) => (float) ($line->qty_kg ?? 0),
            ),
            default => (float) $this->lines->sum(
                fn ($line) => (int) ($line->qty_meters ?? 0),
            ),
        };
    }

    public function remainingQty(): float
    {
        return max(0.0, $this->orderedQty() - $this->receivedQty());
    }

    public function hasReceived(): bool
    {
        return $this->receivedQty() > 0;
    }

    public function hasRemaining(): bool
    {
        return $this->remainingQty() > 0;
    }

    public function manualStageValue(): string
    {
        $detail = $this->primaryLine();
        $raw = match ((string) $this->type) {
            PurchaseOrderType::GREIGE, PurchaseOrderType::PRODUCT => $detail?->stage,
            default => null,
        };

        return match ((string) $this->type) {
            PurchaseOrderType::PRODUCT => PurchaseOrderStages::normalizeProductManualStage($raw),
            PurchaseOrderType::GREIGE => PurchaseOrderStages::normalizeGreigeManualStage($raw) ?? '',
            default => '',
        };
    }

    public static function receivedQtyFor(int $poId, ?object $displayPo = null): float
    {
        if (Schema::hasTable('purchase_orders')) {
            $model = self::query()->with('lines')->find($poId);
            if ($model !== null) {
                return $model->receivedQty();
            }
        }

        if ($displayPo !== null) {
            return (float) ($displayPo->received ?? $displayPo->received_kg ?? 0);
        }

        return 0.0;
    }

    public static function remainingQtyFor(int $poId, ?object $displayPo = null): float
    {
        if (Schema::hasTable('purchase_orders')) {
            $model = self::query()->with('lines')->find($poId);
            if ($model !== null) {
                return $model->remainingQty();
            }
        }

        if ($displayPo !== null) {
            $ordered = match ($displayPo->type ?? PurchaseOrderType::PRODUCT) {
                PurchaseOrderType::YARN => (float) ($displayPo->qty_kg ?? $displayPo->qty ?? 0),
                default => (float) ($displayPo->qty_meters ?? $displayPo->qty ?? 0),
            };

            return max(0.0, $ordered - self::receivedQtyFor($poId, $displayPo));
        }

        return 0.0;
    }

    public static function hasReceivedFor(int $poId, ?object $displayPo = null): bool
    {
        return self::receivedQtyFor($poId, $displayPo) > 0;
    }

    public static function hasRemainingFor(int $poId, ?object $displayPo = null): bool
    {
        return self::remainingQtyFor($poId, $displayPo) > 0;
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with([
                'supplier',
                'shipTo',
                'order.customer',
                'lines.material',
                'lines.greige',
                'lines.product.greige',
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
                'lines.material',
                'lines.greige',
                'lines.product.greige',
            ])
            ->find($id);

        return $po?->toDisplayObject();
    }

    public function toDisplayObject(): object
    {
        $type = $this->type;
        $status = $this->status ?? PurchaseOrderStatus::ORDERED;
        $detail = $this->primaryLine();

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
            'line_count' => $this->lines->count(),
        ];

        $row['order_id'] = $this->order_id;
        $row['order_code'] = $this->order?->code;
        $row['customer'] = $this->order?->customer?->name;

        if ($type === PurchaseOrderType::YARN) {
            $material = $detail?->material;
            $row['material_id'] = $detail?->material_id;
            $row['sku'] = $this->summarizeLineSkus(fn ($line) => $line->material?->sku ?? '—');
            $row['product'] = $material?->name ?? '—';
            $row['unit'] = 'kg';
            $row['qty_kg'] = (float) $this->lines->sum(fn ($line) => (float) ($line->qty_kg ?? 0));
            $row['qty'] = $row['qty_kg'];
            $row['received_kg'] = (float) $this->lines->sum(fn ($line) => (float) ($line->received_qty_kg ?? 0));
            $row['received'] = $row['received_kg'];
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $greige = $detail?->greige;
            $sku = $greige?->sku ?? '—';
            $row['greige_sku'] = $sku;
            $row['sku'] = $this->summarizeLineSkus(fn ($line) => $line->greige?->sku ?? '—');
            $row['product'] = $greige?->name ?? '—';
            $row['unit'] = '反';
            $row['qty_meters'] = (int) $this->lines->sum(fn ($line) => (int) ($line->qty_meters ?? 0));
            $row['qty'] = $row['qty_meters'];
            $row['qty_tan'] = (float) $this->lines->sum(fn ($line) => (float) ($line->qty_tan ?? 0));
            $row['meters_per_tan'] = (int) ($detail?->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
            $row['received'] = (int) $this->lines->sum(fn ($line) => (int) ($line->received_qty_m ?? 0));
            $row['yarn_requirements'] = DemoData::greigeYarnRequirements($sku, $row['qty_meters']);
            $row['manual_stage'] = $this->manualStageValue()
                ?: PurchaseOrderStages::normalizeGreigeManualStage($detail?->stage);
            $row['finish_date'] = $detail?->finish_date?->toDateString()
                ?? $this->due_date?->toDateString();
        } else {
            $product = $detail?->product;
            $productId = (int) ($detail?->product_id ?? 0);
            $row['product_id'] = $productId;
            $row['product'] = $product?->sku ?? '—';
            $row['sku'] = $this->summarizeLineSkus(fn ($line) => $line->product?->sku ?? '—');
            $row['unit'] = $product?->unit ?? '反';
            $row['qty_meters'] = (int) $this->lines->sum(fn ($line) => (int) ($line->qty_meters ?? 0));
            $row['qty_tan'] = (float) $this->lines->sum(fn ($line) => (float) ($line->qty_tan ?? 0));
            if ($row['qty_tan'] <= 0 && $row['qty_meters'] > 0 && $productId > 0) {
                $row['qty_tan'] = QtyHelper::tanCount($row['qty_meters'], $productId);
            }
            if ($row['qty_meters'] <= 0 && $row['qty_tan'] > 0 && $productId > 0) {
                $row['qty_meters'] = QtyHelper::metersFromTan($row['qty_tan'], $productId);
            }
            $row['qty'] = $row['qty_meters'];
            $row['received'] = (int) $this->lines->sum(fn ($line) => (int) ($line->received_qty_m ?? 0));
            $row['finish_date'] = $detail?->finish_date?->toDateString();
            $row['contact_date'] = $detail?->contact_date?->toDateString();
            $row['manual_stage'] = $this->manualStageValue()
                ?: PurchaseOrderStages::normalizeProductManualStage($detail?->stage);
        }

        $po = (object) $row;
        $row['stage'] = PurchaseOrderDisplay::label($po);
        $row['progress'] = PurchaseOrderDisplay::progressPercent($po);

        return (object) $row;
    }

    /** @return Collection<int, object> */
    public static function displayLineList(): Collection
    {
        return self::query()
            ->with([
                'supplier',
                'shipTo',
                'order.customer',
                'lines.material',
                'lines.greige',
                'lines.product.greige',
            ])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get()
            ->flatMap(fn (self $po) => $po->toDisplayObjects())
            ->values();
    }

    /** @return list<object> */
    public function toDisplayObjects(): array
    {
        $lines = $this->lines->sortBy('line_no');
        if ($lines->isEmpty()) {
            return [$this->toDisplayObject()];
        }

        return $lines
            ->map(fn (PurchaseOrderLine $line) => $this->lineToDisplayObject($line))
            ->all();
    }

    public function lineToDisplayObject(PurchaseOrderLine $line): object
    {
        $header = $this->toDisplayObject();
        $type = $this->type;
        $lineCount = $this->lines->count();

        $row = (array) $header;
        $row['purchase_order_line_id'] = $line->id;
        $row['line_no'] = $line->line_no;
        $row['line_count'] = $lineCount;

        if ($type === PurchaseOrderType::YARN) {
            $material = $line->material;
            $row['material_id'] = $line->material_id;
            $row['sku'] = $line->skuLabel();
            $row['product'] = $material?->name ?? '—';
            $row['unit'] = 'kg';
            $row['qty_kg'] = (float) ($line->qty_kg ?? 0);
            $row['qty'] = $row['qty_kg'];
            $row['received_kg'] = (float) ($line->received_qty_kg ?? 0);
            $row['received'] = $row['received_kg'];
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $greigeSku = $line->greige?->sku ?? '—';
            $row['greige_sku'] = $greigeSku;
            $row['sku'] = $line->skuLabel();
            $row['product'] = $line->greige?->name ?? '—';
            $row['unit'] = '反';
            $row['qty_meters'] = (int) ($line->qty_meters ?? 0);
            $row['qty'] = $row['qty_meters'];
            $row['qty_tan'] = (float) ($line->qty_tan ?? 0);
            $row['meters_per_tan'] = $line->metersPerTanValue();
            $row['received'] = (int) ($line->received_qty_m ?? 0);
            $row['yarn_requirements'] = DemoData::greigeYarnRequirements($greigeSku, $row['qty_meters']);
            $row['manual_stage'] = PurchaseOrderStages::normalizeGreigeManualStage($line->stage);
            $row['finish_date'] = $line->finish_date?->toDateString()
                ?? $this->due_date?->toDateString();
        } else {
            $productId = (int) ($line->product_id ?? 0);
            $row['product_id'] = $productId;
            $row['product'] = $line->product?->sku ?? '—';
            $row['sku'] = $line->skuLabel();
            $row['unit'] = $line->product?->unit ?? '反';
            $row['qty_meters'] = (int) ($line->qty_meters ?? 0);
            $row['qty_tan'] = (float) ($line->qty_tan ?? 0);
            if ($row['qty_tan'] <= 0 && $row['qty_meters'] > 0 && $productId > 0) {
                $row['qty_tan'] = QtyHelper::tanCount($row['qty_meters'], $productId);
            }
            if ($row['qty_meters'] <= 0 && $row['qty_tan'] > 0 && $productId > 0) {
                $row['qty_meters'] = QtyHelper::metersFromTan($row['qty_tan'], $productId);
            }
            $row['qty'] = $row['qty_meters'];
            $row['received'] = (int) ($line->received_qty_m ?? 0);
            $row['finish_date'] = $line->finish_date?->toDateString();
            $row['contact_date'] = $line->contact_date?->toDateString();
            $row['manual_stage'] = PurchaseOrderStages::normalizeProductManualStage($line->stage);
        }

        $lineStage = PurchaseOrderLineDisplay::label($this, $line);
        $row['stage'] = $lineStage;
        $row['line_stage'] = $lineStage;
        $row['progress'] = PurchaseOrderLineDisplay::progressPercent($this, $line);

        return (object) $row;
    }

    /** @return list<array<string, mixed>> */
    public function lineDisplayRows(): array
    {
        return $this->lines
            ->sortBy('line_no')
            ->map(fn (PurchaseOrderLine $line) => $line->toDisplayRow())
            ->values()
            ->all();
    }

    /**
     * @param  callable(PurchaseOrderLine): string  $skuResolver
     */
    private function summarizeLineSkus(callable $skuResolver): string
    {
        $skus = $this->lines
            ->map($skuResolver)
            ->filter(fn ($sku) => $sku !== '' && $sku !== '—')
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            return '—';
        }

        if ($skus->count() === 1) {
            return (string) $skus->first();
        }

        return $skus->implode(', ');
    }

    /** 受注画面の「発注紐づけ」用。製品発注のみ product_id を返す */
    public function productIdForLink(): ?int
    {
        if ($this->type !== PurchaseOrderType::PRODUCT) {
            return null;
        }

        $line = $this->relationLoaded('lines')
            ? $this->primaryLine()
            : $this->lines()->orderBy('line_no')->first();

        return $line?->product_id ? (int) $line->product_id : null;
    }

    public static function linkToOrder(int $purchaseOrderId, int $orderId): void
    {
        self::query()
            ->whereKey($purchaseOrderId)
            ->update(['order_id' => $orderId]);
    }
}

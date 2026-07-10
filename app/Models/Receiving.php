<?php

namespace App\Models;

use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'code',
    'received_date',
    'note',
])]
class Receiving extends Model
{
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    /** @return HasMany<ReceivingLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceivingLine::class)->orderBy('line_no');
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with([
                'lines.purchaseOrderLine.purchaseOrder.supplier',
                'lines.purchaseOrderLine.material',
                'lines.purchaseOrderLine.greige',
                'lines.purchaseOrderLine.product',
                'lines.greigeRolls',
                'lines.productRolls',
            ])
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $receiving) => $receiving->toDisplayObject());
    }

    public function toDisplayObject(): object
    {
        $line = $this->lines->sortBy('line_no')->first();
        $poLine = $line?->purchaseOrderLine;
        $po = $poLine?->purchaseOrder;
        $type = (string) ($po?->type ?? PurchaseOrderType::PRODUCT);

        $row = [
            'id' => $this->id,
            'code' => $this->code,
            'po_code' => $po?->code ?? '—',
            'po_type' => $type,
            'supplier' => $po?->supplier?->name ?? '—',
            'date' => $this->received_date?->toDateString(),
        ];

        if ($type === PurchaseOrderType::YARN) {
            $base = DemoData::baseReceivingRows()->firstWhere('id', $this->id);
            $material = $poLine?->material;
            $row['material_id'] = $poLine?->material_id;
            $row['sku'] = $material?->sku ?? '—';
            $row['unit'] = 'kg';
            $row['qty_kg'] = (float) (is_array($base) ? ($base['qty_kg'] ?? $base['qty'] ?? 0) : ($base?->qty_kg ?? $base?->qty ?? 0));
            $row['qty'] = $row['qty_kg'];
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $greige = $poLine?->greige;
            $rolls = $line?->greigeRolls ?? collect();
            $row['greige_sku'] = $greige?->sku ?? '—';
            $row['sku'] = $row['greige_sku'];
            $row['unit'] = '反';
            $row['qty_meters'] = (int) round($rolls->sum(fn ($roll) => (float) $roll->actual_qty_m));
            $row['qty_tan'] = QtyHelper::roundReceivingTan((float) $rolls->sum(fn ($roll) => (float) $roll->tan_qty));
            $row['qty'] = $row['qty_meters'];
        } else {
            $product = $poLine?->product;
            $rolls = $line?->productRolls ?? collect();
            $row['product_id'] = $poLine?->product_id;
            $row['sku'] = $product?->sku ?? '—';
            $row['unit'] = $product?->unit ?? '反';
            $row['qty'] = (int) round($rolls->sum(fn ($roll) => (float) $roll->actual_qty_m));
            $row['qty_tan'] = QtyHelper::roundReceivingTan((float) $rolls->sum(fn ($roll) => (float) $roll->tan_qty));
        }

        return (object) $row;
    }
}

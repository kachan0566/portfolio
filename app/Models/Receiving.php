<?php

namespace App\Models;

use App\Support\PurchaseOrderType;
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
            ->flatMap(fn (self $receiving) => $receiving->toDisplayObjects())
            ->values();
    }

    /** @return list<object> */
    public function toDisplayObjects(): array
    {
        $lines = $this->lines->sortBy('line_no');
        if ($lines->isEmpty()) {
            return [(object) [
                'id' => $this->id,
                'code' => $this->code,
                'line_no' => null,
                'po_code' => '—',
                'po_type' => PurchaseOrderType::PRODUCT,
                'supplier' => '—',
                'sku' => '—',
                'qty' => 0,
                'date' => $this->received_date?->toDateString(),
            ]];
        }

        return $lines->map(fn (ReceivingLine $line) => $this->lineToDisplayObject($line))->all();
    }

    public function lineToDisplayObject(ReceivingLine $line): object
    {
        $poLine = $line->purchaseOrderLine;
        $po = $poLine?->purchaseOrder;
        $type = (string) ($po?->type ?? PurchaseOrderType::PRODUCT);

        $row = [
            'id' => $this->id,
            'receiving_line_id' => $line->id,
            'code' => $this->code,
            'line_no' => $line->line_no,
            'line_count' => $this->lines->count(),
            'po_id' => $po?->id,
            'po_code' => $po?->code ?? '—',
            'po_type' => $type,
            'supplier' => $po?->supplier?->name ?? '—',
            'date' => $this->received_date?->toDateString(),
        ];

        if ($type === PurchaseOrderType::YARN) {
            $material = $poLine?->material;
            $row['material_id'] = $poLine?->material_id;
            $row['sku'] = $material?->sku ?? '—';
            $row['unit'] = 'kg';
            $row['qty_kg'] = (float) ($line->qty_kg ?? 0);
            $row['qty'] = $row['qty_kg'];
        } elseif ($type === PurchaseOrderType::GREIGE) {
            $greige = $poLine?->greige;
            $row['greige_sku'] = $greige?->sku ?? '—';
            $row['sku'] = $row['greige_sku'];
            $row['unit'] = '反';
            $row['qty_meters'] = (int) ($line->qty_m ?? 0);
            $row['qty_tan'] = (float) ($line->qty_tan ?? 0);
            $row['qty'] = $row['qty_meters'];
        } else {
            $product = $poLine?->product;
            $row['product_id'] = $poLine?->product_id;
            $row['sku'] = $product?->sku ?? '—';
            $row['unit'] = $product?->unit ?? '反';
            $row['qty'] = (int) ($line->qty_m ?? 0);
            $row['qty_tan'] = (float) ($line->qty_tan ?? 0);
        }

        return (object) $row;
    }

    /** @deprecated Use toDisplayObjects() for list display */
    public function toDisplayObject(): object
    {
        $line = $this->lines->sortBy('line_no')->first();

        return $line !== null
            ? $this->lineToDisplayObject($line)
            : (object) ($this->toDisplayObjects()[0] ?? []);
    }
}

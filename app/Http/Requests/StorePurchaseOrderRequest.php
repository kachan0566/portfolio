<?php

namespace App\Http\Requests;

use App\Models\GreigeRecipe;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Support\DemoData;
use App\Support\MasterCatalog;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('lines') && is_array($this->input('lines'))) {
            return;
        }

        $type = (string) $this->input('type', PurchaseOrderType::PRODUCT);
        $line = match ($type) {
            PurchaseOrderType::YARN => [
                'material_id' => $this->input('material_id'),
                'qty_kg' => $this->input('qty_kg'),
            ],
            PurchaseOrderType::GREIGE => [
                'greige_sku' => $this->input('greige_sku'),
                'qty_tan' => $this->input('qty_tan'),
            ],
            default => [
                'product_id' => $this->input('product_id'),
                'qty_meters' => $this->input('qty_meters'),
            ],
        };

        $this->merge(['lines' => [0 => $line]]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type', PurchaseOrderType::PRODUCT);

        $base = [
            'type' => ['required', Rule::in(PurchaseOrderType::all())],
            'supplier_id' => ['required', 'integer'],
            'ship_to_id' => ['required', 'integer'],
            'order_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:order_date'],
            'save_action' => ['required', Rule::in(['draft', 'ordered'])],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
        ];

        if ($type === PurchaseOrderType::YARN) {
            return $base + [
                'lines.*.material_id' => [
                    'required', 'integer',
                    Rule::in(MasterCatalog::yarnMaterials()->pluck('id')->all()),
                ],
                'lines.*.qty_kg' => ['required', 'numeric', 'gt:0', 'max:999999'],
            ];
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return $base + [
                'lines.*.greige_sku' => [
                    'required', 'string',
                    Rule::in(MasterCatalog::greiges()->pluck('sku')->all()),
                ],
                'lines.*.qty_tan' => ['required', 'numeric', 'gt:0', 'max:99999'],
            ];
        }

        return $base + [
            'lines.*.product_id' => [
                'required', 'integer',
                Rule::in(MasterCatalog::products()->pluck('id')->all()),
            ],
            'lines.*.qty_meters' => ['required', 'integer', 'min:1', 'max:9999999'],
            'order_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => '発注種別',
            'supplier_id' => '依頼先',
            'ship_to_id' => '出荷先',
            'order_date' => '発注日',
            'due_date' => '納期',
            'lines' => '明細行',
            'lines.*.material_id' => '糸品番',
            'lines.*.qty_kg' => '発注数量',
            'lines.*.greige_sku' => '生機品番',
            'lines.*.qty_tan' => '発注反数',
            'lines.*.product_id' => '製品品番',
            'lines.*.qty_meters' => '総m数',
            'save_action' => '保存区分',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = (string) $this->input('type');
            $supplierId = (int) $this->input('supplier_id');
            $shipToId = (int) $this->input('ship_to_id');

            if ($supplierId > 0 && ! Supplier::forPurchaseType($type)->contains('id', $supplierId)) {
                $validator->errors()->add('supplier_id', 'この発注種別では選べない依頼先です。');
            }

            if ($shipToId > 0 && ! ShipTo::forPurchaseType($type)->contains('id', $shipToId)) {
                $validator->errors()->add('ship_to_id', 'この発注種別では選べない出荷先です。');
            }

            if ($type === PurchaseOrderType::GREIGE) {
                foreach ((array) $this->input('lines', []) as $index => $line) {
                    $sku = (string) ($line['greige_sku'] ?? '');
                    if ($sku !== '' && ! GreigeRecipe::existsForSku($sku)) {
                        $validator->errors()->add("lines.{$index}.greige_sku", 'この生機品番のレシピが未登録のため発注できません。');
                    }

                    $tan = (float) ($line['qty_tan'] ?? 0);
                    if ($tan > 0 && ! QtyHelper::isIntegerTan($tan)) {
                        $validator->errors()->add("lines.{$index}.qty_tan", '発注反数は整数で入力してください。');
                    }
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalizedLines(): array
    {
        $type = (string) $this->input('type');
        $raw = (array) $this->input('lines', []);
        $lines = [];

        foreach (array_values($raw) as $line) {
            if (! is_array($line)) {
                continue;
            }

            if ($type === PurchaseOrderType::YARN) {
                $lines[] = [
                    'material_id' => (int) ($line['material_id'] ?? 0),
                    'qty_kg' => (float) ($line['qty_kg'] ?? 0),
                ];
            } elseif ($type === PurchaseOrderType::GREIGE) {
                $sku = (string) ($line['greige_sku'] ?? '');
                $greige = MasterCatalog::findGreige($sku);
                $perTan = (int) ($greige?->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
                $tan = (float) ($line['qty_tan'] ?? 0);
                $lines[] = [
                    'greige_sku' => $sku,
                    'qty_tan' => $tan,
                    'meters_per_tan' => $perTan,
                    'qty_meters' => QtyHelper::metersFromTan($tan, null, true, $sku),
                ];
            } else {
                $productId = (int) ($line['product_id'] ?? 0);
                $qtyMeters = (int) ($line['qty_meters'] ?? 0);
                $lines[] = [
                    'product_id' => $productId,
                    'qty_meters' => $qtyMeters,
                    'qty_tan' => QtyHelper::tanCount($qtyMeters, $productId),
                ];
            }
        }

        return $lines;
    }
}

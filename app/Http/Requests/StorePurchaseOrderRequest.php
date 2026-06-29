<?php

namespace App\Http\Requests;

use App\Support\DemoData;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        ];

        if ($type === PurchaseOrderType::YARN) {
            return $base + [
                'material_id' => [
                    'required', 'integer',
                    Rule::in(DemoData::yarnMaterials()->pluck('id')->all()),
                ],
                'qty_kg' => ['required', 'numeric', 'gt:0', 'max:999999'],
            ];
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return $base + [
                'greige_sku' => [
                    'required', 'string',
                    Rule::in(DemoData::greiges()->pluck('sku')->all()),
                ],
                'qty_tan' => ['required', 'numeric', 'gt:0', 'max:99999'],
            ];
        }

        return $base + [
            'product_id' => [
                'required', 'integer',
                Rule::in(DemoData::products()->pluck('id')->all()),
            ],
            'qty_meters' => ['required', 'integer', 'min:1', 'max:9999999'],
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
            'material_id' => '糸品番',
            'qty_kg' => '発注数量',
            'greige_sku' => '生機品番',
            'qty_tan' => '発注反数',
            'product_id' => '製品品番',
            'qty_meters' => '総m数',
            'save_action' => '保存区分',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = (string) $this->input('type');
            $supplierId = (int) $this->input('supplier_id');
            $shipToId = (int) $this->input('ship_to_id');

            if ($supplierId > 0 && ! DemoData::suppliersForPurchaseType($type)->contains('id', $supplierId)) {
                $validator->errors()->add('supplier_id', 'この発注種別では選べない依頼先です。');
            }

            if ($shipToId > 0 && ! DemoData::shipTosForPurchaseType($type)->contains('id', $shipToId)) {
                $validator->errors()->add('ship_to_id', 'この発注種別では選べない出荷先です。');
            }

            if ($type === PurchaseOrderType::GREIGE) {
                $sku = (string) $this->input('greige_sku');
                if ($sku !== '' && ! DemoData::hasGreigeRecipe($sku)) {
                    $validator->errors()->add('greige_sku', 'この生機品番のレシピが未登録のため発注できません。');
                }
            }
        });
    }
}

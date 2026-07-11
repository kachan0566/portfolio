<?php

namespace App\Http\Requests;

use App\Support\FabricQuantity;
use App\Support\QtyHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id'),
            ],
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id'),
            ],
            'order_qty_mode' => ['required', Rule::in(['tan', 'meters'])],
            'qty_tan' => ['nullable', 'numeric', 'min:1', 'max:99999'],
            'qty_meters' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'order_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:order_date'],
            'ship_memo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => '得意先',
            'product_id' => '品番',
            'order_qty_mode' => '受注単位',
            'qty_tan' => '受注反数',
            'qty_meters' => '受注m数',
            'order_date' => '受注日',
            'due_date' => '納期',
            'ship_memo' => '出荷予定日メモ',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = (string) $this->input('order_qty_mode', 'tan');
            $productId = (int) $this->input('product_id');

            if ($mode === 'tan') {
                $tan = (float) $this->input('qty_tan');
                if ($tan <= 0) {
                    $validator->errors()->add('qty_tan', '受注反数を入力してください。');
                } elseif (! QtyHelper::isIntegerTan($tan)) {
                    $validator->errors()->add('qty_tan', '受注反数は整数で入力してください。');
                }
            } else {
                $meters = (int) $this->input('qty_meters');
                if ($meters <= 0) {
                    $validator->errors()->add('qty_meters', '受注m数を入力してください。');
                }
            }

            if ($productId > 0 && $mode === 'tan') {
                $resolved = FabricQuantity::resolve(
                    $this->input('qty_tan'),
                    null,
                    $productId,
                    false,
                    null,
                    FabricQuantity::CONTEXT_ORDER,
                );
                if ($resolved->qty_tan <= 0) {
                    $validator->errors()->add('qty_tan', '受注反数を入力してください。');
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Support\DemoData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                Rule::in(DemoData::products()->pluck('id')->all()),
            ],
            'processing_cost' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => '品番',
            'processing_cost' => '染色加工料',
            'price' => '販売価格',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $productId = (int) $this->input('product_id');
            if ($productId && DemoData::hasRecipe($productId)) {
                $validator->errors()->add('product_id', 'この品番のレシピはすでに登録されています。');
            }
        });
    }
}

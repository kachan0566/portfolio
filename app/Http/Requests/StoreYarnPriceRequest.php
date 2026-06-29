<?php

namespace App\Http\Requests;

use App\Support\DemoData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYarnPriceRequest extends FormRequest
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
            'material_id' => [
                'required',
                'integer',
                Rule::in(DemoData::yarnMaterials()->pluck('id')->all()),
            ],
            'ym' => ['required', 'date_format:Y-m'],
            'price' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'material_id' => '糸',
            'ym' => '年月',
            'price' => '単価',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $materialId = (int) $this->input('material_id');
            $ym = (string) $this->input('ym');

            if ($materialId && $ym && DemoData::hasYarnPrice($materialId, $ym)) {
                $validator->errors()->add('ym', 'この糸・年月の単価はすでに登録されています。');
            }
        });
    }
}

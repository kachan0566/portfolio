<?php

namespace App\Http\Requests;

use App\Support\MasterCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGreigeRecipeRequest extends FormRequest
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
            'loss_rate_percent' => ['required', 'numeric', 'min:0', 'max:99'],
            'weaving_cost' => ['required', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => [
                'required',
                'integer',
                Rule::in(MasterCatalog::yarnMaterials()->pluck('id')->all()),
            ],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'loss_rate' => 'ロス率',
            'weaving_cost' => '織賃',
            'lines' => '糸明細',
            'lines.*.material_id' => '糸',
            'lines.*.qty' => '使用量',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $percent = (float) $this->input('loss_rate_percent', 0);
            if ($percent >= 0 && $percent <= 99) {
                $this->merge(['loss_rate' => round($percent / 100, 4)]);
            }

            $materialIds = collect($this->input('lines', []))->pluck('material_id')->filter();
            if ($materialIds->count() !== $materialIds->unique()->count()) {
                $validator->errors()->add('lines', '同じ糸を重複して登録できません。');
            }
        });
    }
}

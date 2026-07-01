<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'],
            'ship_to_id' => ['required', 'integer'],
            'order_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:order_date'],
            'status' => ['required', 'string'],
            'finish_date' => ['nullable', 'date'],
            'arrival_memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => '依頼先',
            'ship_to_id' => '出荷先',
            'order_date' => '発注日',
            'due_date' => '納期',
            'status' => '状態',
            'finish_date' => '入荷予定日',
            'arrival_memo' => 'メモ',
        ];
    }
}

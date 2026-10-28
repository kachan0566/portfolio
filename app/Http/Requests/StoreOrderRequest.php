<?php

namespace App\Http\Requests;

use App\Support\FabricQuantity;
use App\Support\QtyHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 受注の新規登録・更新フォームから送られた入力を検証する FormRequest。
 *
 * OrderController の store / update で使う。
 * 得意先・品番はマスタ（customers / products）に存在すること、
 * 数量は「反」または「m」のどちらか一方のモードで入力することを確認する。
 *
 * @see \App\Http\Controllers\OrderController::store()
 * @see \App\Http\Controllers\OrderController::update()
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * このリクエストの実行を許可するかどうかを返す。
     *
     * 現状は認証・権限チェックを行わず、常に許可する（デモ用）。
     *
     * @return bool  true のとき Laravel が rules() 以降の検証を続行する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 受注入力の基本検証ルール（型・必須・マスタ存在・日付の前後）を返す。
     *
     * 処理の流れ:
     * 1. customer_id / product_id を整数として必須にし、各マスタ表の id に存在するか確認する
     * 2. order_qty_mode で「反（tan）」か「m（meters）」のどちらで入力するかを限定する
     * 3. qty_tan / qty_meters は nullable だが、モードごとの追加チェックは withValidator() で行う
     * 4. due_date は order_date 以降の日付であること
     *
     * @return array<string, array<int, mixed>>  フィールド名 => Laravel 検証ルール配列
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
            'qty_tan' => ['nullable', 'numeric', 'min:0.25', 'max:99999'],
            'qty_meters' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'order_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:order_date'],
            'ship_memo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * 検証エラーメッセージに使う日本語の項目名を返す。
     *
     * Laravel の attributes() として、:attribute プレースホルダを置き換える。
     *
     * @return array<string, string>  フォーム項目名 => 画面表示用ラベル
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

    /**
     * 基本ルールのあと、数量モードに応じた追加検証を登録する。
     *
     * 処理の流れ:
     * 1. order_qty_mode が tan のとき、qty_tan の正数と 0.25 反刻み（QtyHelper）を確認する
     * 2. meters のときは qty_meters の正の整数のみ確認する（0.25 反刻みは適用しない）
     * 3. 反指定かつ品番が有効なとき、FabricQuantity で受注用に数量を解決し、反数が 0 以下ならエラーにする
     *
     * @param  Validator  $validator  rules() 適用後の検証器。after クロージャでエラーを追加する
     * @return void  戻り値は使わず、$validator へエラーを積み上げる
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = (string) $this->input('order_qty_mode', 'tan');
            $productId = (int) $this->input('product_id');

            if ($mode === 'tan') {
                $tan = (float) $this->input('qty_tan');
                if ($tan <= 0) {
                    $validator->errors()->add('qty_tan', '受注反数を入力してください。');
                } elseif (! QtyHelper::isValidTanStep($tan)) {
                    $validator->errors()->add('qty_tan', '受注反数は0.25反刻みで入力してください。');
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

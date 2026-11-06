<?php

namespace App\Models\Yarn;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Master\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 生機発注ごとに、どの糸を何 kg 引当したかを表 yarn_allocations に保存するモデル。
 *
 * 1 行が「1 発注 × 1 糸」の引当量（qty_kg）。在庫の利用可能 kg 計算では allocatedKg の合計を使う。
 */
#[Fillable(['purchase_order_id', 'material_id', 'qty_kg'])]
class YarnAllocation extends Model
{
    /**
     * DB の値を PHP の型に変換するときのルール（属性名 → キャスト種別）を返す。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'material_id' => 'integer',
            'qty_kg' => 'decimal:3',
        ];
    }

    /**
     * この引当 1 行が、どの生機発注（PurchaseOrder / 表 purchase_orders）に属するかを取得する関連。
     *
     * purchase_order_id 列で PurchaseOrder を参照する。
     *
     * @return BelongsTo<PurchaseOrder, $this>  先は PurchaseOrder。$this は YarnAllocation の型表記
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * この引当 1 行が、どの糸マスタ（Material / 表 materials）に対する引当かを取得する関連。
     *
     * material_id 列で Material を参照する。
     *
     * @return BelongsTo<Material, $this>  先は Material。$this は YarnAllocation の型表記
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * 指定の生機発注に紐づく糸引当を、渡した明細で丸ごと置き換える。
     *
     * 処理の流れ:
     * 1. 同じ purchase_order_id の既存行をすべて削除する
     * 2. $lines の各行で qty_kg を小数 3 桁に丸める
     * 3. qty_kg が 0 以下の行は登録しない
     * 4. 残りを yarn_allocations に新規作成する
     *
     * @param  int  $purchaseOrderId  生機発注の内部 ID（purchase_orders.id）
     * @param  list<array{material_id: int, qty_kg: float}>  $lines  引当明細。material_id は materials.id、qty_kg は kg
     * @return void  戻り値はない（削除と insert のみ）
     */
    public static function replaceForGreigePo(int $purchaseOrderId, array $lines): void
    {
        self::query()->where('purchase_order_id', $purchaseOrderId)->delete();

        $now = now();
        foreach ($lines as $line) {
            $qtyKg = round((float) ($line['qty_kg'] ?? 0), 3);
            if ($qtyKg <= 0) {
                continue;
            }

            self::query()->create([
                'purchase_order_id' => $purchaseOrderId,
                'material_id' => (int) $line['material_id'],
                'qty_kg' => $qtyKg,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 指定の生機発注に紐づく糸引当をすべて削除し、引当を解放する。
     *
     * purchase_order_id が一致する yarn_allocations の行を delete する。
     *
     * @param  int  $purchaseOrderId  生機発注の内部 ID（purchase_orders.id）
     * @return void  戻り値はない
     */
    public static function releaseGreigePo(int $purchaseOrderId): void
    {
        self::query()->where('purchase_order_id', $purchaseOrderId)->delete();
    }

    /**
     * 指定の糸について、生機発注への引当合計（kg）を返す。
     *
     * 処理の流れ:
     * 1. material_id が一致する行を yarn_allocations から絞り込む
     * 2. $excludeGreigePoId が指定されていれば、その発注 ID の行は合計から除外する（編集中発注の二重カウント防止）
     * 3. qty_kg を合計し、小数 3 桁に丸める
     *
     * @param  int  $materialId  糸マスタの内部 ID（materials.id）
     * @param  int|null  $excludeGreigePoId  合計から除外する生機発注 ID。null のときは全発注を含める
     * @return float  引当合計（kg）。該当行がなければ 0.0
     */
    public static function allocatedKg(int $materialId, ?int $excludeGreigePoId = null): float
    {
        $query = self::query()->where('material_id', $materialId);

        if ($excludeGreigePoId !== null) {
            $query->where('purchase_order_id', '!=', $excludeGreigePoId);
        }

        return round((float) $query->sum('qty_kg'), 3);
    }
}

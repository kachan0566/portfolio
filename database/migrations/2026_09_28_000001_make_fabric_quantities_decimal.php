<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 0.25反と小数mを正確に保存できるよう、生地数量の整数列を小数列へ変更する。
 *
 * 反数は小数2桁、反数から換算・集計するm数も小数2桁で保持する。
 * ロール実測mなど、すでに小数対応済みの列は変更しない。
 */
return new class extends Migration
{
    /**
     * 生地取引の反数・m数を小数2桁で保存できる列へ変更する。
     *
     * @return void マイグレーション実行結果はLaravelが管理するため戻り値は使わない
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('qty_tan', 8, 2)->unsigned()->default(0)->change();
            $table->decimal('qty_meters', 12, 2)->unsigned()->default(0)->change();
            $table->decimal('shipped_qty_m', 12, 2)->unsigned()->default(0)->change();
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('qty_tan', 8, 2)->unsigned()->nullable()->change();
            $table->decimal('qty_meters', 12, 2)->unsigned()->nullable()->change();
            $table->decimal('received_qty_m', 12, 2)->unsigned()->nullable()->change();
        });

        Schema::table('order_allocations', function (Blueprint $table) {
            $table->decimal('qty_m', 12, 2)->unsigned()->default(0)->change();
        });

        Schema::table('receiving_lines', function (Blueprint $table) {
            $table->decimal('qty_m', 12, 2)->unsigned()->default(0)->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('qty_m', 12, 2)->unsigned()->default(0)->change();
        });

        Schema::table('receiving_roll_amendments', function (Blueprint $table) {
            $table->decimal('line_qty_m_before', 12, 2)->unsigned()->change();
            $table->decimal('line_qty_m_after', 12, 2)->unsigned()->nullable()->change();
        });
    }

    /**
     * 小数データがない場合だけ、変更した列を従来の整数型へ戻す。
     *
     * 小数部分を暗黙に失わないよう、対象列に小数値が1件でもあれば例外で中止する。
     *
     * @return void マイグレーション実行結果はLaravelが管理するため戻り値は使わない
     */
    public function down(): void
    {
        $this->assertWholeNumbersBeforeRollback([
            'orders' => ['qty_tan', 'qty_meters', 'shipped_qty_m'],
            'purchase_order_lines' => ['qty_tan', 'qty_meters', 'received_qty_m'],
            'order_allocations' => ['qty_m'],
            'receiving_lines' => ['qty_m'],
            'shipments' => ['qty_m'],
            'receiving_roll_amendments' => ['line_qty_m_before', 'line_qty_m_after'],
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('qty_tan')->default(0)->change();
            $table->unsignedInteger('qty_meters')->default(0)->change();
            $table->unsignedInteger('shipped_qty_m')->default(0)->change();
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('qty_tan')->nullable()->change();
            $table->unsignedInteger('qty_meters')->nullable()->change();
            $table->unsignedInteger('received_qty_m')->nullable()->change();
        });

        Schema::table('order_allocations', function (Blueprint $table) {
            $table->unsignedInteger('qty_m')->default(0)->change();
        });

        Schema::table('receiving_lines', function (Blueprint $table) {
            $table->unsignedInteger('qty_m')->default(0)->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedInteger('qty_m')->default(0)->change();
        });

        Schema::table('receiving_roll_amendments', function (Blueprint $table) {
            $table->unsignedInteger('line_qty_m_before')->change();
            $table->unsignedInteger('line_qty_m_after')->nullable()->change();
        });
    }

    /**
     * 整数型へ戻す対象列に小数値が含まれていないことを確認する。
     *
     * DB製品に依存する剰余SQLを使わず、取得値をPHPの誤差許容付き整数判定で検査する。
     *
     * @param  array<string, list<string>>  $columnsByTable  テーブル名と整数へ戻す列名の対応
     * @return void 小数値がなければ処理を継続し、ある場合は例外でロールバックを止める
     */
    private function assertWholeNumbersBeforeRollback(array $columnsByTable): void
    {
        foreach ($columnsByTable as $table => $columns) {
            foreach ($columns as $column) {
                $hasFraction = DB::table($table)
                    ->whereNotNull($column)
                    ->pluck($column)
                    ->contains(
                        fn (mixed $value): bool => abs((float) $value - round((float) $value)) > 0.0001
                    );

                if ($hasFraction) {
                    throw new RuntimeException(
                        "{$table}.{$column} に小数データがあるため、整数型へ戻せません。"
                    );
                }
            }
        }
    }
};

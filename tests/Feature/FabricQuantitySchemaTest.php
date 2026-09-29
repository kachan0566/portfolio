<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 0.25反と小数mを保持するDB列が、小数型へ統一されていることを確認する。
 */
class FabricQuantitySchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 反数と換算・集計mの対象列がdecimal型で作成されることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_fabric_quantity_columns_use_decimal_types(): void
    {
        $decimalColumns = [
            'orders' => ['qty_tan', 'qty_meters', 'shipped_qty_m'],
            'purchase_order_lines' => ['qty_tan', 'qty_meters', 'received_qty_m'],
            'order_allocations' => ['qty_tan', 'qty_m'],
            'receiving_lines' => ['qty_tan', 'qty_m'],
            'shipments' => ['qty_tan', 'qty_m'],
            'allocation_conversions' => ['qty'],
            'receiving_roll_amendments' => [
                'line_qty_tan_before',
                'line_qty_m_before',
                'line_qty_tan_after',
                'line_qty_m_after',
            ],
        ];

        foreach ($decimalColumns as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertContains(
                    Schema::getColumnType($table, $column),
                    ['decimal', 'numeric'],
                    "{$table}.{$column} must use a fixed-point decimal column."
                );
            }
        }
    }
}

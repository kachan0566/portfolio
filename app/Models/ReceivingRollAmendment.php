<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'receiving_line_id',
    'roll_type',
    'roll_id',
    'roll_code',
    'field',
    'old_value',
    'new_value',
    'line_qty_tan_before',
    'line_qty_m_before',
    'line_qty_tan_after',
    'line_qty_m_after',
    'reason',
    'changed_at',
])]
class ReceivingRollAmendment extends Model
{
    public const ROLL_TYPE_GREIGE = 'greige_roll';

    public const ROLL_TYPE_PRODUCT = 'product_roll';

    public const FIELD_TAN_QTY = 'tan_qty';

    public const FIELD_ACTUAL_QTY_M = 'actual_qty_m';

    protected function casts(): array
    {
        return [
            'receiving_line_id' => 'integer',
            'roll_id' => 'integer',
            'old_value' => 'decimal:3',
            'new_value' => 'decimal:3',
            'line_qty_tan_before' => 'decimal:2',
            'line_qty_m_before' => 'integer',
            'line_qty_tan_after' => 'decimal:2',
            'line_qty_m_after' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReceivingLine, $this> */
    public function receivingLine(): BelongsTo
    {
        return $this->belongsTo(ReceivingLine::class);
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            self::FIELD_TAN_QTY => '反数',
            self::FIELD_ACTUAL_QTY_M => '実測m',
            default => $field,
        };
    }
}

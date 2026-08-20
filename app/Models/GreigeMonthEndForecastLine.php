<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GreigeMonthEndForecastLine extends Model
{
    protected $fillable = [
        'greige_month_end_forecast_id',
        'greige_id',
        'current_stock_qty',
        'inbound_scheduled_qty',
        'outbound_scheduled_qty',
        'manual_adjustment_qty',
        'forecast_qty',
        'unit_cost_snapshot',
        'forecast_value',
        'long_term_qty',
        'long_term_value',
        'oldest_received_date',
        'oldest_age_months',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'current_stock_qty' => 'decimal:2',
            'inbound_scheduled_qty' => 'decimal:2',
            'outbound_scheduled_qty' => 'decimal:2',
            'manual_adjustment_qty' => 'decimal:2',
            'forecast_qty' => 'decimal:2',
            'long_term_qty' => 'decimal:2',
            'oldest_received_date' => 'date',
        ];
    }

    /** @return BelongsTo<GreigeMonthEndForecast, $this> */
    public function forecast(): BelongsTo
    {
        return $this->belongsTo(
            GreigeMonthEndForecast::class,
            'greige_month_end_forecast_id',
        );
    }

    /** @return BelongsTo<Greige, $this> */
    public function greige(): BelongsTo
    {
        return $this->belongsTo(Greige::class);
    }
}

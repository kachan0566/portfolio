<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthEndForecast extends Model
{
    protected $fillable = [
        'target_ym',
        'base_date',
        'version',
        'created_by_name',
        'submitted_at',
        'submission_status',
        'total_forecast_value',
        'total_long_term_value',
    ];

    protected function casts(): array
    {
        return [
            'base_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MonthEndForecastLine::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sku', 'name', 'category', 'unit', 'meters_per_tan', 'note'])]
class Greige extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'meters_per_tan' => 'integer',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

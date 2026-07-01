<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sku', 'price', 'category', 'unit'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }
}

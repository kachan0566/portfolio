<?php

namespace App\Models;

use App\Support\PurchaseOrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'note'])]
class ShipTo extends Model
{
    use SoftDeletes;

    /** @return Collection<int, self> */
    public static function forPurchaseType(string $type): Collection
    {
        $allowed = PurchaseOrderType::shipToTypesFor($type);

        return self::query()
            ->whereIn('type', $allowed)
            ->orderBy('id')
            ->get();
    }
}

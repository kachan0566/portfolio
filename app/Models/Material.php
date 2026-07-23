<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['sku', 'type', 'name', 'unit'])]
class Material extends Model
{
    public const TYPE_YARN = 'yarn';

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->orderBy('id')
            ->get()
            ->map(fn (self $material) => $material->toDisplayObject());
    }

    /** @return Collection<int, object> */
    public static function yarnMaterials(): Collection
    {
        return self::query()
            ->where('type', self::TYPE_YARN)
            ->orderBy('id')
            ->get()
            ->map(fn (self $material) => $material->toDisplayObject());
    }

    public function toDisplayObject(): object
    {
        return (object) [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'type' => $this->type,
        ];
    }

    public static function findDisplay(int $id): ?object
    {
        $material = self::query()->find($id);

        return $material?->toDisplayObject();
    }

    public static function isYarn(int $materialId): bool
    {
        return self::query()
            ->whereKey($materialId)
            ->where('type', self::TYPE_YARN)
            ->exists();
    }
}

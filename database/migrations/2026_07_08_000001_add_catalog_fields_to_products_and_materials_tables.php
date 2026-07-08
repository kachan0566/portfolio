<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'greige_id')) {
                $table->unsignedBigInteger('greige_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('products', 'color')) {
                $table->string('color', 50)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('products', 'meters_per_tan')) {
                $table->unsignedInteger('meters_per_tan')->default(50)->after('unit');
            }
            if (! Schema::hasColumn('products', 'stock_min_m')) {
                $table->unsignedInteger('stock_min_m')->default(0)->after('meters_per_tan');
            }
        });

        $defaultGreigeId = DB::table('greiges')->orderBy('id')->value('id');
        if ($defaultGreigeId !== null) {
            DB::table('products')
                ->where(function ($query) {
                    $query->whereNull('greige_id')->orWhere('greige_id', 0);
                })
                ->update(['greige_id' => $defaultGreigeId]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('greige_id')->references('id')->on('greiges');
            $table->index('greige_id');
        });

        Schema::table('materials', function (Blueprint $table) {
            if (! Schema::hasColumn('materials', 'sku')) {
                $table->string('sku', 50)->nullable()->after('id');
            }
            if (! Schema::hasColumn('materials', 'type')) {
                $table->string('type', 32)->nullable()->after('sku');
            }
        });

        foreach (DB::table('materials')->orderBy('id')->get() as $row) {
            if (($row->sku ?? '') === '' || ($row->type ?? '') === '') {
                DB::table('materials')
                    ->where('id', $row->id)
                    ->update([
                        'sku' => 'LEGACY-'.$row->id,
                        'type' => 'yarn',
                    ]);
            }
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->unique('sku');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['greige_id']);
            $table->dropColumn(['greige_id', 'color', 'meters_per_tan', 'stock_min_m']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'type']);
        });
    }
};

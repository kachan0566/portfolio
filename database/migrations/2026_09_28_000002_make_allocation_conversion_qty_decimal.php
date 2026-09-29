<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 発注引当から現在庫引当へ変換したm数を、小数2桁まで保存できるようにする。
 */
return new class extends Migration
{
    /**
     * 変換履歴のm数を整数から小数へ変更する。
     */
    public function up(): void
    {
        Schema::table('allocation_conversions', function (Blueprint $table) {
            $table->decimal('qty', 12, 2)->unsigned()->change();
        });
    }

    /**
     * 小数データがない場合だけ整数型へ戻す。
     */
    public function down(): void
    {
        $hasFraction = DB::table('allocation_conversions')
            ->pluck('qty')
            ->contains(
                fn (mixed $value): bool => abs((float) $value - round((float) $value)) > 0.0001
            );

        if ($hasFraction) {
            throw new RuntimeException(
                'allocation_conversions.qty に小数データがあるため、整数型へ戻せません。'
            );
        }

        Schema::table('allocation_conversions', function (Blueprint $table) {
            $table->unsignedInteger('qty')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_roll_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_line_id')->constrained()->cascadeOnDelete();
            $table->string('roll_type', 16);
            $table->unsignedBigInteger('roll_id');
            $table->string('roll_code', 30);
            $table->string('field', 32);
            $table->decimal('old_value', 12, 3);
            $table->decimal('new_value', 12, 3);
            $table->decimal('line_qty_tan_before', 8, 2);
            $table->unsignedInteger('line_qty_m_before');
            $table->decimal('line_qty_tan_after', 8, 2)->nullable();
            $table->unsignedInteger('line_qty_m_after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['receiving_line_id', 'changed_at']);
            $table->index(['roll_type', 'roll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_roll_amendments');
    }
};

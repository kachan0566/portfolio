<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact', 100)->nullable();
            $table->string('tel', 30)->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact', 100)->nullable();
            $table->string('tel', 30)->nullable();
            $table->string('type', 32);
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('ship_tos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 32);
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('greiges', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 50);
            $table->string('unit', 10)->default('反');
            $table->unsignedInteger('meters_per_tan')->default(100);
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greiges');
        Schema::dropIfExists('ship_tos');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};

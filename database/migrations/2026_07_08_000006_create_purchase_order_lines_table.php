<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('material_id')->nullable()->constrained();
            $table->foreignId('greige_id')->nullable()->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->decimal('qty_kg', 12, 3)->nullable();
            $table->decimal('received_qty_kg', 12, 3)->nullable();
            $table->unsignedInteger('qty_tan')->nullable();
            $table->unsignedInteger('meters_per_tan')->nullable();
            $table->unsignedInteger('qty_meters')->nullable();
            $table->decimal('received_qty_tan', 8, 2)->nullable();
            $table->unsignedInteger('received_qty_m')->nullable();
            $table->string('stage', 50)->nullable();
            $table->date('finish_date')->nullable();
            $table->date('contact_date')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index('material_id');
            $table->index('greige_id');
            $table->index('product_id');
        });

        if (Schema::hasTable('yarn_purchase_orders')) {
            $yarnRows = DB::table('yarn_purchase_orders')->get();
            foreach ($yarnRows as $row) {
                DB::table('purchase_order_lines')->insert([
                    'purchase_order_id' => $row->purchase_order_id,
                    'line_no' => 1,
                    'material_id' => $row->material_id,
                    'qty_kg' => $row->qty_kg,
                    'received_qty_kg' => $row->received_qty_kg,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('greige_purchase_orders')) {
            $greigeRows = DB::table('greige_purchase_orders')->get();
            foreach ($greigeRows as $row) {
                DB::table('purchase_order_lines')->insert([
                    'purchase_order_id' => $row->purchase_order_id,
                    'line_no' => 1,
                    'greige_id' => $row->greige_id,
                    'qty_tan' => $row->qty_tan,
                    'meters_per_tan' => $row->meters_per_tan,
                    'qty_meters' => $row->qty_meters,
                    'received_qty_tan' => $row->received_qty_tan,
                    'received_qty_m' => $row->received_qty_m,
                    'stage' => $row->stage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('product_purchase_orders')) {
            $productRows = DB::table('product_purchase_orders')->get();
            foreach ($productRows as $row) {
                DB::table('purchase_order_lines')->insert([
                    'purchase_order_id' => $row->purchase_order_id,
                    'line_no' => 1,
                    'product_id' => $row->product_id,
                    'qty_tan' => $row->qty_tan,
                    'qty_meters' => $row->qty_meters,
                    'received_qty_tan' => $row->received_qty_tan,
                    'received_qty_m' => $row->received_qty_m,
                    'stage' => $row->stage,
                    'finish_date' => $row->finish_date,
                    'contact_date' => $row->contact_date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('product_purchase_orders');
        Schema::dropIfExists('greige_purchase_orders');
        Schema::dropIfExists('yarn_purchase_orders');
    }

    public function down(): void
    {
        Schema::create('yarn_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('qty_kg', 12, 3)->default(0);
            $table->decimal('received_qty_kg', 12, 3)->default(0);
        });

        Schema::create('greige_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('greige_id')->constrained();
            $table->unsignedInteger('qty_tan')->default(0);
            $table->unsignedInteger('meters_per_tan');
            $table->unsignedInteger('qty_meters')->default(0);
            $table->decimal('received_qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('received_qty_m')->default(0);
            $table->string('stage', 50)->nullable();
            $table->index('greige_id');
        });

        Schema::create('product_purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('qty_tan')->default(0);
            $table->unsignedInteger('qty_meters')->default(0);
            $table->decimal('received_qty_tan', 8, 2)->default(0);
            $table->unsignedInteger('received_qty_m')->default(0);
            $table->string('stage', 50)->nullable();
            $table->date('finish_date')->nullable();
            $table->date('contact_date')->nullable();
            $table->index('product_id');
            $table->index('stage');
        });

        $lines = DB::table('purchase_order_lines')->orderBy('id')->get();
        foreach ($lines as $line) {
            $po = DB::table('purchase_orders')->where('id', $line->purchase_order_id)->first();
            if ($po === null) {
                continue;
            }

            if ($po->type === 'yarn' && $line->material_id !== null) {
                DB::table('yarn_purchase_orders')->insert([
                    'purchase_order_id' => $line->purchase_order_id,
                    'material_id' => $line->material_id,
                    'qty_kg' => $line->qty_kg ?? 0,
                    'received_qty_kg' => $line->received_qty_kg ?? 0,
                ]);
            } elseif ($po->type === 'greige' && $line->greige_id !== null) {
                DB::table('greige_purchase_orders')->insert([
                    'purchase_order_id' => $line->purchase_order_id,
                    'greige_id' => $line->greige_id,
                    'qty_tan' => $line->qty_tan ?? 0,
                    'meters_per_tan' => $line->meters_per_tan ?? 0,
                    'qty_meters' => $line->qty_meters ?? 0,
                    'received_qty_tan' => $line->received_qty_tan ?? 0,
                    'received_qty_m' => $line->received_qty_m ?? 0,
                    'stage' => $line->stage,
                ]);
            } elseif ($po->type === 'product' && $line->product_id !== null) {
                DB::table('product_purchase_orders')->insert([
                    'purchase_order_id' => $line->purchase_order_id,
                    'product_id' => $line->product_id,
                    'qty_tan' => $line->qty_tan ?? 0,
                    'qty_meters' => $line->qty_meters ?? 0,
                    'received_qty_tan' => $line->received_qty_tan ?? 0,
                    'received_qty_m' => $line->received_qty_m ?? 0,
                    'stage' => $line->stage,
                    'finish_date' => $line->finish_date,
                    'contact_date' => $line->contact_date,
                ]);
            }
        }

        Schema::dropIfExists('purchase_order_lines');
    }
};

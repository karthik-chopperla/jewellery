<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('barcode_format', 10)->default('code128');
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamp('barcode_generated_at')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
        });

        DB::table('inventory_items')->select(['id', 'created_at'])->orderBy('id')->chunkById(500, function ($items) {
            foreach ($items as $item) {
                DB::table('inventory_items')->where('id', $item->id)->update([
                    'qr_token' => Str::random(64),
                    'barcode_generated_at' => $item->created_at ?? now(),
                    'qr_generated_at' => now(),
                ]);
            }
        });

        Schema::table('label_print_logs', function (Blueprint $table) {
            $table->uuid('print_job_id')->nullable()->index();
            $table->string('template', 30)->nullable();
            $table->string('paper_type', 10)->nullable();
            $table->decimal('label_width_mm', 6, 2)->nullable();
            $table->decimal('label_height_mm', 6, 2)->nullable();
            $table->decimal('label_margin_mm', 6, 2)->nullable();
            $table->decimal('label_gap_mm', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('label_print_logs', function (Blueprint $table) {
            $table->dropIndex(['print_job_id']);
            $table->dropColumn(['print_job_id', 'template', 'paper_type', 'label_width_mm', 'label_height_mm', 'label_margin_mm', 'label_gap_mm']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['barcode_format', 'qr_token', 'barcode_generated_at', 'qr_generated_at']);
        });
    }
};

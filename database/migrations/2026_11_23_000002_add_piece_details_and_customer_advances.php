<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('other_weight_deductions', 10, 3)->default(0);
            $table->date('stock_entry_date')->nullable();
            $table->string('purchase_reference', 100)->nullable();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('diamond_weight', 10, 3)->default(0);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('state', 100)->nullable();
            $table->decimal('advance_balance', 14, 2)->default(0);
        });

        Schema::create('customer_advance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('reference_no', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advance_transactions');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['state', 'advance_balance']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['other_weight_deductions', 'stock_entry_date', 'purchase_reference']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('diamond_weight');
        });
    }
};

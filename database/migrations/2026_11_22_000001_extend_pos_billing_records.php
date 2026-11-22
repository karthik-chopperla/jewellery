<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('gstin', 15)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot', 30)->nullable();
            $table->string('customer_gstin', 15)->nullable();
            $table->text('customer_address')->nullable();
            $table->decimal('amount_received', 14, 2)->default(0);
            $table->decimal('change_return', 14, 2)->default(0);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1);
            $table->string('sku', 40)->nullable();
            $table->string('barcode', 60)->nullable();
            $table->string('huid', 30)->nullable();
            $table->string('category_name')->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('other_deductions', 10, 3)->default(0);
            $table->decimal('invoice_discount', 14, 2)->default(0);
        });

        Schema::table('old_gold_purchases', function (Blueprint $table) {
            $table->string('metal_type', 30)->default('Gold');
            $table->decimal('other_deductions', 10, 3)->default(0);
            $table->string('tax_treatment', 40)->default('customer_exchange');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('refund_method', 30)->nullable();
            $table->string('credit_note_no', 40)->nullable()->unique();
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropUnique(['credit_note_no']);
            $table->dropConstrainedForeignId('invoice_item_id');
            $table->dropColumn([
                'quantity', 'refund_method', 'credit_note_no', 'taxable_value',
                'cgst_amount', 'sgst_amount', 'igst_amount',
            ]);
        });

        Schema::table('old_gold_purchases', function (Blueprint $table) {
            $table->dropColumn(['metal_type', 'other_deductions', 'tax_treatment']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'quantity', 'sku', 'barcode', 'huid', 'category_name', 'hsn_code',
                'other_deductions', 'invoice_discount',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name_snapshot', 'customer_phone_snapshot', 'customer_gstin',
                'customer_address', 'amount_received', 'change_return',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('gstin');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });
    }
};

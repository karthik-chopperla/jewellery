<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('business_name_snapshot', 120)->nullable();
            $table->text('business_address_snapshot')->nullable();
            $table->string('business_phone_snapshot', 40)->nullable();
            $table->string('business_gstin_snapshot', 30)->nullable();
            $table->string('business_state_snapshot', 100)->nullable();
            $table->string('business_state_code_snapshot', 2)->nullable();
            $table->string('business_logo_snapshot')->nullable();
        });

        Schema::create('invoice_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('phone_snapshot', 20);
            $table->string('token_hash', 64)->unique();
            $table->string('status', 30)->default('link_prepared');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'created_at']);
        });

        Schema::create('invoice_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_no', 50)->unique();
            $table->uuid('request_token')->unique();
            $table->foreignId('original_invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('sales_return_id')->unique()->constrained('sales_returns')->restrictOnDelete();
            $table->foreignId('replacement_invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('return_value', 14, 2)->default(0);
            $table->decimal('replacement_taxable_value', 14, 2)->default(0);
            $table->decimal('replacement_cgst', 12, 2)->default(0);
            $table->decimal('replacement_sgst', 12, 2)->default(0);
            $table->decimal('replacement_igst', 12, 2)->default(0);
            $table->decimal('replacement_total', 14, 2)->default(0);
            $table->decimal('credit_applied', 14, 2)->default(0);
            $table->decimal('additional_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('completed');
            $table->string('reason', 255);
            $table->timestamps();
            $table->index(['original_invoice_id', 'created_at']);
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->string('metal_type', 30)->nullable();
            $table->decimal('stone_weight', 10, 3)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn(['metal_type', 'stone_weight']);
        });

        Schema::dropIfExists('invoice_exchanges');
        Schema::dropIfExists('invoice_shares');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'business_name_snapshot',
                'business_address_snapshot',
                'business_phone_snapshot',
                'business_gstin_snapshot',
                'business_state_snapshot',
                'business_state_code_snapshot',
                'business_logo_snapshot',
            ]);
        });
    }
};

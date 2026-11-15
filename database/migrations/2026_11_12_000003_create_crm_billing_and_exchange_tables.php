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
            $table->string('customer_code', 30)->unique();
            $table->string('name');
            $table->string('phone', 30)->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('group_tier', 20)->default('standard'); // standard, silver, gold, vip
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('outstanding_balance', 14, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->foreignId('referred_by')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('preferences')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('type', 20); // earn, redeem, adjust, referral
            $table->string('reference', 80)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('channel', 20); // whatsapp, sms, email
            $table->string('occasion', 40); // festival, akshaya_tritiya, dhanteras, diwali, birthday, anniversary, collection, loyalty
            $table->string('target_group', 30)->default('all'); // all, vip, gold, silver, birthday_month
            $table->text('message');
            $table->string('status', 20)->default('draft'); // draft, scheduled, sent
            $table->integer('sent_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_type', 20)->default('sale'); // sale, credit, estimate
            $table->string('gst_type', 15)->default('intra'); // intra (CGST+SGST), inter (IGST)
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('making_total', 14, 2)->default(0);
            $table->decimal('stone_total', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('old_gold_deduction', 14, 2)->default(0);
            $table->decimal('advance_adjusted', 14, 2)->default(0);
            $table->decimal('scheme_adjusted', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->string('status', 20)->default('paid'); // paid, partial, credit, cancelled, returned
            $table->text('notes')->nullable();
            $table->timestamp('billed_at');
            $table->timestamps();
            $table->index(['branch_id', 'billed_at', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('metal_type', 30);
            $table->string('purity', 20);
            $table->decimal('gross_weight', 10, 3)->default(0);
            $table->decimal('stone_weight', 10, 3)->default(0);
            $table->decimal('net_weight', 10, 3)->default(0);
            $table->decimal('metal_rate', 12, 2)->default(0);
            $table->decimal('metal_value', 14, 2)->default(0);
            $table->decimal('wastage_pct', 5, 2)->default(0);
            $table->decimal('wastage_weight', 10, 3)->default(0);
            $table->decimal('wastage_value', 14, 2)->default(0);
            $table->string('making_charge_type', 20)->default('per_gram');
            $table->decimal('making_charge_rate', 12, 2)->default(0);
            $table->decimal('making_charge_amount', 12, 2)->default(0);
            $table->decimal('stone_charge', 12, 2)->default(0);
            $table->decimal('diamond_charge', 12, 2)->default(0);
            $table->decimal('hallmark_charge', 10, 2)->default(0);
            $table->decimal('other_charge', 10, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(3.00);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30); // cash, upi, card, bank_transfer, advance, old_gold, scheme
            $table->decimal('amount', 14, 2);
            $table->string('reference_no', 80)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->default(5);
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 40)->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('return_type', 20)->default('refund'); // return, exchange, refund
            $table->string('condition', 30)->default('good');
            $table->decimal('return_weight', 10, 3)->default(0);
            $table->string('tested_purity', 20)->nullable();
            $table->decimal('return_value', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->string('reason');
            $table->string('status', 30)->default('pending_approval'); // pending_approval, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('old_gold_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description');
            $table->decimal('gross_weight', 10, 3);
            $table->decimal('stone_weight', 10, 3)->default(0);
            $table->decimal('net_weight', 10, 3);
            $table->string('tested_purity', 20);
            $table->decimal('purity_pct', 5, 2)->default(91.60);
            $table->decimal('fine_gold_weight', 10, 3);
            $table->decimal('applied_rate', 12, 2);
            $table->decimal('deduction_pct', 5, 2)->default(2.00);
            $table->decimal('melting_loss_pct', 5, 2)->default(1.00);
            $table->decimal('valuation_amount', 14, 2);
            $table->string('settlement_type', 20)->default('exchange'); // exchange, cash, bank
            $table->boolean('customer_declaration')->default(true);
            $table->string('status', 30)->default('pending_approval'); // pending_approval, approved, melted, refinery_transferred
            $table->string('refinery_name')->nullable();
            $table->date('refinery_transfer_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('old_gold_purchases');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('customer_feedbacks');
        Schema::dropIfExists('invoice_modifications');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_wishlists');
        Schema::dropIfExists('customers');
    }
};


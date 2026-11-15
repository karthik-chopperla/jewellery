<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('total_weight', 10, 3)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('ordered'); // ordered, received, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('bill_date');
            $table->string('metal_type', 30)->default('Gold');
            $table->string('purity', 20)->default('22K');
            $table->decimal('metal_weight', 10, 3)->default(0);
            $table->decimal('stone_amount', 12, 2)->default(0);
            $table->decimal('making_amount', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, partial, paid, returned
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->default('bank_transfer');
            $table->string('reference_no', 80)->nullable();
            $table->date('payment_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artisan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('design_name');
            $table->string('metal_type', 30)->default('Gold');
            $table->string('purity', 20)->default('22K');
            $table->decimal('issued_weight', 10, 3)->default(0);
            $table->decimal('expected_weight', 10, 3)->default(0);
            $table->decimal('actual_weight', 10, 3)->default(0);
            $table->decimal('wastage_weight', 10, 3)->default(0);
            $table->decimal('metal_loss', 10, 3)->default(0);
            $table->decimal('labour_charge', 12, 2)->default(0);
            $table->string('stage', 30)->default('metal_issued'); // metal_issued, stone_setting, polishing, finishing, qc, completed
            $table->date('expected_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('design_notes')->nullable();
            $table->string('reference_image')->nullable();
            $table->string('metal_type', 30)->default('Gold');
            $table->string('purity', 20)->default('22K');
            $table->decimal('estimated_weight', 10, 3)->default(0);
            $table->string('stone_details')->nullable();
            $table->decimal('locked_rate', 12, 2)->nullable();
            $table->decimal('estimated_amount', 14, 2)->default(0);
            $table->decimal('advance_paid', 14, 2)->default(0);
            $table->date('expected_delivery')->nullable();
            $table->string('status', 30)->default('pending_approval'); // pending_approval, approved, in_production, ready, delivered, cancelled
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gold_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('scheme_name')->default('Jewel Dhan 11+1 Plan');
            $table->decimal('monthly_amount', 12, 2);
            $table->integer('duration_months')->default(11);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('maturity_date');
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('active'); // active, matured, redeemed, cancelled
            $table->timestamps();
        });

        Schema::create('scheme_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gold_scheme_id')->constrained()->cascadeOnDelete();
            $table->integer('installment_no');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('method', 30)->nullable();
            $table->string('receipt_no', 40)->nullable();
            $table->string('status', 20)->default('pending'); // pending, paid, missed
            $table->timestamps();
        });

        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('item_description');
            $table->string('before_photo')->nullable();
            $table->decimal('receiving_weight', 10, 3)->default(0);
            $table->foreignId('artisan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('repair_charges', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('expected_delivery')->nullable();
            $table->string('status', 30)->default('received'); // received, in_progress, qc, ready, delivered
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20); // asset, liability, equity, income, expense, cash, bank
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // payment, receipt, journal, contra
            $table->foreignId('account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->foreignId('contra_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('voucher_date');
            $table->string('narration');
            $table->string('reference', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('paid_via', 30)->default('cash');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('repair_jobs');
        Schema::dropIfExists('scheme_installments');
        Schema::dropIfExists('gold_schemes');
        Schema::dropIfExists('custom_orders');
        Schema::dropIfExists('manufacturing_orders');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('purchase_orders');
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('order_reference', 100)->nullable();
            $table->string('customer_state', 100)->nullable();
            $table->decimal('other_adjusted', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['order_reference', 'customer_state', 'other_adjusted']);
        });
    }
};

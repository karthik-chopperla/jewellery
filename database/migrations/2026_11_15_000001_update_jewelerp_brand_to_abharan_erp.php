<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'business_name')
            ->whereIn('value', ['JewelERP', 'JewelERP Showroom'])
            ->update(['value' => 'Abharan ERP', 'updated_at' => now()]);

        DB::table('branches')
            ->where('code', 'DEMO')
            ->where('name', 'JewelERP Demo Showroom')
            ->update(['name' => 'Abharan ERP Demo Showroom']);

        DB::table('users')
            ->where('email', 'admin@jewelerp.in')
            ->where('name', 'JewelERP Demo Admin')
            ->update(['name' => 'Abharan ERP Demo Admin']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'business_name')
            ->where('value', 'Abharan ERP')
            ->update(['value' => 'JewelERP', 'updated_at' => now()]);

        DB::table('branches')
            ->where('code', 'DEMO')
            ->where('name', 'Abharan ERP Demo Showroom')
            ->update(['name' => 'JewelERP Demo Showroom']);

        DB::table('users')
            ->where('email', 'admin@jewelerp.in')
            ->where('name', 'Abharan ERP Demo Admin')
            ->update(['name' => 'JewelERP Demo Admin']);
    }
};

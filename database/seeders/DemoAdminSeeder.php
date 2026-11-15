<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.admin_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_ADMIN_PASSWORD to a password at least 12 characters long.');
        }

        $branch = Branch::query()->where('is_head_office', true)->first()
            ?? Branch::firstOrCreate(
                ['code' => 'DEMO'],
                ['name' => 'JewelERP Demo Showroom', 'is_head_office' => true],
            );

        User::updateOrCreate(
            ['email' => 'admin@jewelerp.in'],
            [
                'branch_id' => $branch->id,
                'name' => 'JewelERP Demo Admin',
                'password' => $password,
                'role' => 'admin',
                'is_active' => true,
            ],
        );
    }
}

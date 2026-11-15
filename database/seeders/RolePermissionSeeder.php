<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'admin' => ['*', 'staff_manage'],
            'manager' => ['dashboard', 'pos', 'inventory', 'rates', 'approvals', 'customers', 'reports', 'staff', 'old_gold'],
            'cashier' => ['pos', 'customers', 'schemes', 'repairs'],
            'salesperson' => ['pos', 'customers', 'custom_orders'],
            'artisan' => ['manufacturing', 'repairs'],
            'accountant' => ['accounts', 'reports', 'purchases'],
        ] as $role => $permissions) {
            RolePermission::updateOrCreate(
                ['role' => $role],
                ['permissions' => $permissions],
            );
        }
    }
}

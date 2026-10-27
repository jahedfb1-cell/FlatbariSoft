<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for Phase 1.x (billing, gas, payments, expenses). Admin gets
 * full access (matches PRD: "হিসাব দেখা, ভাউচার/বিল Approve, রিপোর্ট").
 * Manager gets exactly the write-actions PRD lists for that role (meter
 * reading entry, collection entry, expense entry) plus view - never
 * approve, since Maker-Checker (BR-17) requires approval by Admin/Super
 * Admin, not the same role that made the entry.
 */
class MoneyCorePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminOnly = [
            'billing.manage', 'billing.view',
            'payment.approve',
            'expense.approve',
            'accounting.manage', 'accounting.view',
        ];
        $managerAlso = [
            'gas.manage', 'gas.view',
            'payment.create', 'payment.view',
            'expense.create', 'expense.view',
        ];

        foreach (array_merge($adminOnly, $managerAlso) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo(array_merge($adminOnly, $managerAlso));
        Role::where('name', 'manager')->first()?->givePermissionTo($managerAlso);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Product catalog
            'view products',
            'create products',
            'edit products',
            'delete products',
            'publish products',

            // Pricing & contracts
            'view pricing',
            'manage pricing',
            'view contracts',
            'manage contracts',

            // Punchout
            'view punchout sessions',
            'manage punchout settings',

            // Orders
            'view orders',
            'manage orders',

            // Vendors
            'view vendors',
            'manage vendors',

            // Users & teams
            'view users',
            'manage users',
            'assign roles',

            // Admin
            'access admin panel',
            'view analytics',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // ── Roles ──

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — manage platform (no super-admin exclusive)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions([
            'view products', 'create products', 'edit products', 'delete products', 'publish products',
            'view pricing', 'manage pricing', 'view contracts', 'manage contracts',
            'view punchout sessions', 'manage punchout settings',
            'view orders', 'manage orders',
            'view vendors', 'manage vendors',
            'view users', 'manage users', 'assign roles',
            'access admin panel', 'view analytics', 'manage settings',
        ]);

        // Vendor — manage own products, view orders
        $vendor = Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'api']);
        $vendor->syncPermissions([
            'view products', 'create products', 'edit products',
            'view pricing', 'manage pricing',
            'view orders',
        ]);

        // Buyer — browse catalog, punchout
        $buyer = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'api']);
        $buyer->syncPermissions([
            'view products',
            'view pricing',
            'view orders',
        ]);

        // Guest — new user default (no permissions)
        Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'api']);

        $this->command->info('Roles & permissions seeded for HTAShop.');
        $this->command->info('super-admin | admin | vendor | buyer | Guest');
    }
}

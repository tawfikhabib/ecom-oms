<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            'view-orders',
            'create-orders',
            'confirm-orders',
            'cancel-orders',
            'view-inventory',
            'manage-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $vendor = Role::firstOrCreate(['name' => 'vendor']);
        $customer = Role::firstOrCreate(['name' => 'customer']);

        // Assign permissions to roles
        $admin->givePermissionTo(Permission::all());

        $vendor->givePermissionTo([
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            'view-orders',
        ]);

        $customer->givePermissionTo([
            'view-products',
            'create-orders',
            'cancel-orders',
        ]);

        // Create test users
        $admin_user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin_user->assignRole('admin');

        $vendor_user = User::firstOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Vendor User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $vendor_user->assignRole('vendor');

        $customer_user = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $customer_user->assignRole('customer');

        $this->command->info('Roles, permissions, and test users created successfully.');
    }
}

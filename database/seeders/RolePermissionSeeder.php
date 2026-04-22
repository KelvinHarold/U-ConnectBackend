<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            // Product permissions
            'view products',
            'create products',
            'edit products',
            'delete products',
            'manage inventory',
            
            // Order permissions
            'view orders',
            'create orders',
            'update orders',
            'cancel orders',
            'deliver orders',
            
            // User permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',
            
            // Category permissions
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            
            // Report permissions
            'view reports',
            'export reports',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $sellerRole = Role::create(['name' => 'seller']);
        $sellerRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'delete products',
            'manage inventory',
            'view orders',
            'update orders',
            'cancel orders',
            'deliver orders',
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyerRole->givePermissionTo([
            'view products',
            'create orders',
            'view orders',
            'cancel orders',
        ]);

    }
}
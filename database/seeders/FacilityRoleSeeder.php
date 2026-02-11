<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FacilityRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run($roleable_type, $roleable_id): void
    {
        $this->createRoles($roleable_type, $roleable_id);
    }

    /**
     * Create roles for a facility (static method for controller usage)
     */
    public static function createRoles($roleable_type, $roleable_id): void
    {
        pinfo([$roleable_type, $roleable_id]);
        // ====================
        // Roles
        // ====================
        $roles = [];
        $roles[] = Role::create(['name' => 'Sorter', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Driver', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Warehouse Supervisor', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Warehouse Cashier', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Financial Accountant', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Vendor Driver', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);
        $roles[] = Role::create(['name' => 'Customer Service', 'guard_name' => 'web', 'roleable_type' => $roleable_type, 'roleable_id' => $roleable_id]);

        foreach ($roles as $role) {
            $role->syncPermissions(PermissionSeeder::getRolePermissions()[$role->name]);
        }
    }
}

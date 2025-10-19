<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class GlobalConfigPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the permission for the GlobalConfig page
        $permission = Permission::firstOrCreate(
            ['name' => 'page_GlobalConfig'],
            [
                'name' => 'page_GlobalConfig',
                'guard_name' => 'web',
            ]
        );

        // Assign the permission to admin and super_admin roles
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if ($adminUser && !$adminUser->hasPermissionTo('page_GlobalConfig')) {
            $adminUser->givePermissionTo('page_GlobalConfig');
        }

        // Also assign to users with admin or super_admin roles
        $adminUsers = User::role(['admin', 'super_admin'])->get();
        foreach ($adminUsers as $user) {
            if (!$user->hasPermissionTo('page_GlobalConfig')) {
                $user->givePermissionTo('page_GlobalConfig');
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ProjectTimesheetSelectorPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the permission for the ProjectTimesheetSelector page
        $permission = Permission::firstOrCreate(
            ['name' => 'page_ProjectTimesheetSelector'],
            [
                'name' => 'page_ProjectTimesheetSelector',
                'guard_name' => 'web',
            ]
        );

        // Assign the permission to the admin user
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if ($adminUser && !$adminUser->hasPermissionTo('page_ProjectTimesheetSelector')) {
            $adminUser->givePermissionTo('page_ProjectTimesheetSelector');
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@admin.com'], // match condition
            [
                'name' => 'Admin',
                'password' => Hash::make('123'), // ⚠️ change to a secure password
            ]
        );
    }
}

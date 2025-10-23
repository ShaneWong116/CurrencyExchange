<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        $admin = User::create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $finance = User::create([
            'username' => 'finance',
            'password' => Hash::make('finance123'),
            'role' => 'finance',
            'status' => 'active',
        ]);
        $finance->assignRole('finance');
    }
}

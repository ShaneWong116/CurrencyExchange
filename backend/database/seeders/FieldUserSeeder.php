<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\FieldUser;

class FieldUserSeeder extends Seeder
{
    public function run()
    {
        FieldUser::create([
            'username' => 'field001',
            'password' => Hash::make('123456'),
            'name' => '张三',
            'status' => 'active',
        ]);

        FieldUser::create([
            'username' => 'field002',
            'password' => Hash::make('123456'),
            'name' => '李四',
            'status' => 'active',
        ]);

        FieldUser::create([
            'username' => 'field003',
            'password' => Hash::make('123456'),
            'name' => '王五',
            'status' => 'active',
        ]);
    }
}

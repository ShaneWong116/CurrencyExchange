<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 添加结余确认密码设置项
        // 默认密码为 "123456" 的哈希值
        DB::table('settings')->insert([
            'key_name' => 'settlement_password',
            'key_value' => password_hash('123456', PASSWORD_DEFAULT),
            'description' => '结余确认密码（用于二次验证）',
            'type' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('settings')->where('key_name', 'settlement_password')->delete();
    }
};


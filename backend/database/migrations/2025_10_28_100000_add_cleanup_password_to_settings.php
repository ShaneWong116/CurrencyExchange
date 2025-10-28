<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        // 添加数据清理密码到 settings 表
        $defaultPassword = '123456';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        Setting::firstOrCreate(
            ['key_name' => 'cleanup_password'],
            [
                'key_value' => $hashedPassword,
                'description' => '数据清理二次验证密码(哈希加密)',
                'type' => 'string',
            ]
        );
    }

    public function down(): void
    {
        Setting::where('key_name', 'cleanup_password')->delete();
    }
};


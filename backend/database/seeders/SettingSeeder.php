<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run()
    {
        // 默认结余密码
        $defaultPassword = '123456';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        $settings = [
            ['key_name' => 'access_token_expire', 'key_value' => '30', 'description' => 'Access Token过期时间（分钟）', 'type' => 'number'],
            ['key_name' => 'refresh_token_expire', 'key_value' => '10080', 'description' => 'Refresh Token过期时间（分钟，7天）', 'type' => 'number'],
            ['key_name' => 'auto_logout_time', 'key_value' => '15', 'description' => '自动登出时间（分钟）', 'type' => 'number'],
            ['key_name' => 'image_max_size', 'key_value' => '5242880', 'description' => '图片最大尺寸（字节，5MB）', 'type' => 'number'],
            ['key_name' => 'image_quality', 'key_value' => '80', 'description' => '图片压缩质量（1-100）', 'type' => 'number'],
            ['key_name' => 'image_formats', 'key_value' => '["jpg","jpeg","png","gif"]', 'description' => '允许的图片格式', 'type' => 'json'],
            ['key_name' => 'exchange_rate_precision', 'key_value' => '5', 'description' => '汇率精度（小数位数）', 'type' => 'number'],
            ['key_name' => 'log_retention_days', 'key_value' => '90', 'description' => '日志保存天数', 'type' => 'number'],
            ['key_name' => 'system_capital_hkd', 'key_value' => '0', 'description' => '系统初始本金（港币）', 'type' => 'number'],
            ['key_name' => 'capital', 'key_value' => '0', 'description' => '系统本金(HKD)', 'type' => 'number'],
            ['key_name' => 'hkd_balance', 'key_value' => '0', 'description' => '港币结余(HKD)', 'type' => 'number'],
            ['key_name' => 'settlement_password', 'key_value' => $hashedPassword, 'description' => '结余确认密码(哈希加密)', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key_name' => $setting['key_name']],
                $setting
            );
        }
        
        $this->command->info('✓ 系统设置初始化完成');
        $this->command->warn('⚠ 默认结余密码: 123456 (请在后台修改)');
    }
}

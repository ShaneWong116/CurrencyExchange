<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['key_name' => 'access_token_expire', 'key_value' => '30', 'description' => 'Access Token过期时间（分钟）', 'type' => 'number'],
            ['key_name' => 'refresh_token_expire', 'key_value' => '10080', 'description' => 'Refresh Token过期时间（分钟，7天）', 'type' => 'number'],
            ['key_name' => 'auto_logout_time', 'key_value' => '15', 'description' => '自动登出时间（分钟）', 'type' => 'number'],
            ['key_name' => 'image_max_size', 'key_value' => '5242880', 'description' => '图片最大尺寸（字节，5MB）', 'type' => 'number'],
            ['key_name' => 'image_quality', 'key_value' => '80', 'description' => '图片压缩质量（1-100）', 'type' => 'number'],
            ['key_name' => 'image_formats', 'key_value' => '["jpg","jpeg","png","gif"]', 'description' => '允许的图片格式', 'type' => 'json'],
            ['key_name' => 'exchange_rate_precision', 'key_value' => '5', 'description' => '汇率精度（小数位数）', 'type' => 'number'],
            ['key_name' => 'log_retention_days', 'key_value' => '90', 'description' => '日志保存天数', 'type' => 'number'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}

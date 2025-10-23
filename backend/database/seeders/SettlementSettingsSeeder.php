<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettlementSettingsSeeder extends Seeder
{
    /**
     * 初始化结余相关的系统设置
     */
    public function run()
    {
        // 设置默认本金(如果不存在)
        $capitalExists = Setting::where('key_name', 'capital')->exists();
        if (!$capitalExists) {
            Setting::create([
                'key_name' => 'capital',
                'key_value' => '0',
                'description' => '系统本金(HKD)',
                'type' => 'number',
            ]);
            $this->command->info('✓ 已初始化系统本金: 0 HKD');
        } else {
            $this->command->info('→ 系统本金已存在,跳过初始化');
        }

        // 设置默认港币结余(如果不存在)
        $hkdBalanceExists = Setting::where('key_name', 'hkd_balance')->exists();
        if (!$hkdBalanceExists) {
            Setting::create([
                'key_name' => 'hkd_balance',
                'key_value' => '0',
                'description' => '港币结余(HKD)',
                'type' => 'number',
            ]);
            $this->command->info('✓ 已初始化港币结余: 0 HKD');
        } else {
            $this->command->info('→ 港币结余已存在,跳过初始化');
        }
    }
}

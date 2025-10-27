<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;

class InitSettlementPassword extends Command
{
    protected $signature = 'settlement:init-password';
    protected $description = '初始化结余确认密码为 123456';

    public function handle()
    {
        $this->info('正在初始化结余密码...');
        
        // 检查是否已存在
        $existing = Setting::where('key_name', 'settlement_password')->first();
        
        if ($existing) {
            $this->warn('密码已存在!');
            $this->line("ID: {$existing->id}");
            $this->line("类型: {$existing->type}");
            $this->line("哈希长度: " . strlen($existing->key_value));
            
            // 测试密码
            if (password_verify('123456', $existing->key_value)) {
                $this->info('✓ 当前密码确实是 123456');
                return 0;
            } else {
                $this->error('✗ 当前密码不是 123456');
                
                if ($this->confirm('是否重置为 123456?', true)) {
                    $hash = password_hash('123456', PASSWORD_DEFAULT);
                    $existing->key_value = $hash;
                    $existing->save();
                    $this->info('✓ 密码已重置为 123456');
                }
                return 0;
            }
        }
        
        // 创建新密码
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        
        Setting::create([
            'key_name' => 'settlement_password',
            'key_value' => $hash,
            'description' => '结余确认密码(哈希加密)',
            'type' => 'string',
        ]);
        
        $this->info('✓ 结余密码已初始化为: 123456');
        $this->warn('⚠ 请在后台"系统管理 → 结余设置"中修改密码!');
        
        return 0;
    }
}


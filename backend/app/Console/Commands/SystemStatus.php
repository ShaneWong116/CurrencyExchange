<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionDraft;
use App\Models\ChannelBalance;
use App\Models\BalanceAdjustment;
use App\Models\Image;

class SystemStatus extends Command
{
    protected $signature = 'system:status';
    protected $description = '检查系统状态和数据统计';

    public function handle()
    {
        $this->info('🔍 财务管理系统状态检查');
        $this->newLine();

        // 数据库连接检查
        $this->checkDatabaseConnection();

        // 数据统计
        $this->showDataStatistics();

        // 系统配置检查
        $this->checkSystemConfiguration();

        $this->newLine();
        $this->info('✅ 系统状态检查完成');

        return Command::SUCCESS;
    }

    private function checkDatabaseConnection()
    {
        $this->info('📊 数据库连接检查');
        
        try {
            \DB::connection()->getPdo();
            $this->line('✅ 数据库连接正常');
        } catch (\Exception $e) {
            $this->error('❌ 数据库连接失败: ' . $e->getMessage());
        }

        $this->newLine();
    }

    private function showDataStatistics()
    {
        $this->info('📈 数据统计');

        $stats = [
            '用户总数' => User::count(),
            '活跃用户' => User::where('status', 'active')->count(),
            '支付渠道' => Channel::count(),
            '活跃渠道' => Channel::where('status', 'active')->count(),
            '交易记录' => Transaction::count(),
            '今日交易' => Transaction::whereDate('created_at', today())->count(),
            '草稿数量' => TransactionDraft::count(),
            '余额记录' => ChannelBalance::count(),
            '调整记录' => BalanceAdjustment::count(),
            '图片数量' => Image::count(),
        ];

        foreach ($stats as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        $this->newLine();
    }

    private function checkSystemConfiguration()
    {
        $this->info('⚙️ 系统配置检查');

        $checks = [
            'PHP版本 >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'Laravel框架' => class_exists('Illuminate\Foundation\Application'),
            'Filament扩展' => class_exists('Filament\Filament'),
            'Excel扩展' => class_exists('Maatwebsite\Excel\Excel'),
            '权限扩展' => class_exists('Spatie\Permission\Models\Role'),
            'GD扩展' => extension_loaded('gd'),
            '存储目录可写' => is_writable(storage_path()),
        ];

        foreach ($checks as $check => $status) {
            if ($status) {
                $this->line("  ✅ {$check}");
            } else {
                $this->line("  ❌ {$check}");
            }
        }

        $this->newLine();
    }
}

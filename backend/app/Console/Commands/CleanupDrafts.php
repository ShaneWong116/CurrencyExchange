<?php

namespace App\Console\Commands;

use App\Models\TransactionDraft;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupDrafts extends Command
{
    protected $signature = 'drafts:cleanup';
    protected $description = '清理过期的交易草稿';

    public function handle()
    {
        $this->info('开始清理交易草稿...');
        
        try {
            // 删除所有草稿记录
            $deletedCount = TransactionDraft::count();
            TransactionDraft::truncate();
            
            $this->info("已清理 {$deletedCount} 条草稿记录");
            
        } catch (\Exception $e) {
            $this->error('草稿清理失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}

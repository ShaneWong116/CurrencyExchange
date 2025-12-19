<?php

namespace App\Services;

use App\Models\CleanupLog;
use App\Models\Transaction;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CleanupService
{
    /**
     * 执行数据清理
     */
    public function cleanup(array $payload, string $operator): array
    {
        $timeRange = $payload['time_range'] ?? 'all';
        $startDate = $payload['start_date'] ?? null;
        $endDate = $payload['end_date'] ?? null;
        $contentTypes = $payload['content_types'] ?? [];

        $deleted = [
            'bills' => 0,
            'drafts' => 0,
            'settlements' => 0,
            'channels' => 0,
            'balances' => 0,
            'accounts' => 0,
            'locations' => 0,
            'images' => 0,
            'adjustments' => 0,
            'statistics' => 0,
            'audit_logs' => 0,
            'notifications' => 0,
            'carry_forward' => 0,
            'other_expenses' => 0,
        ];

        DB::transaction(function () use ($timeRange, $startDate, $endDate, $contentTypes, &$deleted, $operator) {

            // 1. 清理结算记录（需要先清理，因为交易记录依赖结算）
            if (in_array('settlements', $contentTypes)) {
                $deleted['settlements'] = $this->cleanupSettlements();
            }

            // 2. 清理交易记录（账单）
            if (in_array('bills', $contentTypes)) {
                $deleted['bills'] = $this->cleanupTransactions();
            }

            // 3. 清理交易草稿
            if (in_array('drafts', $contentTypes)) {
                $deleted['drafts'] = $this->cleanupDrafts();
            }

            // 4. 清理渠道余额
            if (in_array('balances', $contentTypes)) {
                $deleted['balances'] = $this->cleanupBalances();
            }

            // 5. 清理渠道（仅删除未被引用的）
            if (in_array('channels', $contentTypes)) {
                $deleted['channels'] = $this->cleanupChannels();
            }

            // 6. 清理外勤账号（会删除所有关联数据）
            if (in_array('accounts', $contentTypes)) {
                $deleted['accounts'] = $this->cleanupAccounts();
            }

            // 7. 清理地点
            if (in_array('locations', $contentTypes)) {
                $deleted['locations'] = $this->cleanupLocations();
            }

            // 8. 清理图片
            if (in_array('images', $contentTypes)) {
                $deleted['images'] = $this->cleanupImages();
            }

            // 9. 清理余额/本金调整记录
            if (in_array('adjustments', $contentTypes)) {
                $deleted['adjustments'] = $this->cleanupAdjustments();
            }

            // 10. 清理统计数据
            if (in_array('statistics', $contentTypes)) {
                $deleted['statistics'] = $this->cleanupStatistics();
            }

            // 11. 清理审计日志
            if (in_array('audit_logs', $contentTypes)) {
                $deleted['audit_logs'] = $this->cleanupAuditLogs();
            }

            // 12. 清理通知
            if (in_array('notifications', $contentTypes)) {
                $deleted['notifications'] = $this->cleanupNotifications();
            }

            // 13. 清理余额结转
            if (in_array('carry_forward', $contentTypes)) {
                $deleted['carry_forward'] = $this->cleanupCarryForward();
            }

            // 14. 清理其他支出
            if (in_array('other_expenses', $contentTypes)) {
                $deleted['other_expenses'] = $this->cleanupOtherExpenses();
            }

            // 记录清理日志
            CleanupLog::create([
                'operator' => $operator,
                'time_range' => $timeRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'content_types' => $contentTypes,
                'deleted_records' => $deleted,
            ]);
        });

        // 对 SQLite 数据库执行 VACUUM 以释放磁盘空间
        try {
            $connection = DB::connection()->getDriverName();
            if ($connection === 'sqlite') {
                DB::statement('VACUUM');
                Log::info('SQLite VACUUM completed after cleanup');
            }
        } catch (\Exception $e) {
            Log::warning('VACUUM failed: ' . $e->getMessage());
        }

        return $deleted;
    }


    /**
     * 清理结算记录（删除所有结算记录）
     */
    private function cleanupSettlements(): int
    {
        // 获取所有结算记录（不按时间过滤，因为结算是独立的业务操作）
        $settlements = Settlement::all();
        $deletedCount = 0;
        
        foreach ($settlements as $settlement) {
            try {
                // 先将关联的交易记录状态改回未结算
                Transaction::where('settlement_id', $settlement->id)
                    ->update([
                        'settlement_status' => 'unsettled',
                        'settlement_id' => null,
                        'settlement_date' => null,
                    ]);
                
                // 删除关联的结算支出记录
                if (Schema::hasTable('settlement_expenses')) {
                    DB::table('settlement_expenses')
                        ->where('settlement_id', $settlement->id)
                        ->delete();
                }
                
                // 删除关联的余额调整记录（只删除与结算关联的）
                if (Schema::hasTable('balance_adjustments')) {
                    DB::table('balance_adjustments')
                        ->where('settlement_id', $settlement->id)
                        ->delete();
                }
                
                // 删除结算记录
                $settlement->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                Log::warning('结算记录删除失败', [
                    'settlement_id' => $settlement->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $deletedCount;
    }

    /**
     * 清理交易记录（删除所有交易，包括已结算的）
     */
    private function cleanupTransactions(): int
    {
        // 先删除所有关联的图片
        DB::table('images')->whereNotNull('transaction_id')->delete();
        
        // 删除所有交易记录
        return DB::table('transactions')->delete();
    }

    /**
     * 清理交易草稿（删除所有草稿）
     */
    private function cleanupDrafts(): int
    {
        // 先删除所有关联的图片
        DB::table('images')->whereNotNull('draft_id')->delete();
        
        // 删除所有草稿
        return DB::table('transaction_drafts')->delete();
    }

    /**
     * 清理渠道余额（删除所有渠道余额记录）
     */
    private function cleanupBalances(): int
    {
        if (!Schema::hasTable('channel_balances')) {
            return 0;
        }
        
        return DB::table('channel_balances')->delete();
    }

    /**
     * 清理渠道（完全删除所有渠道）
     * 注意：会先删除所有关联的交易记录、草稿、渠道余额等
     */
    private function cleanupChannels(): int
    {
        // 先删除关联的图片
        DB::table('images')
            ->whereIn('transaction_id', function ($q) {
                $q->select('id')->from('transactions');
            })
            ->delete();
        DB::table('images')
            ->whereIn('draft_id', function ($q) {
                $q->select('id')->from('transaction_drafts');
            })
            ->delete();
        
        // 删除所有交易记录和草稿
        DB::table('transactions')->delete();
        DB::table('transaction_drafts')->delete();
        
        // 删除渠道余额
        if (Schema::hasTable('channel_balances')) {
            DB::table('channel_balances')->delete();
        }
        
        // 删除余额结转
        if (Schema::hasTable('balance_carry_forward')) {
            DB::table('balance_carry_forward')->delete();
        }
        
        // 删除所有渠道
        return DB::table('channels')->delete();
    }

    /**
     * 清理外勤账号（完全删除所有外勤账号）
     * 注意：会先删除所有关联的交易记录和草稿
     */
    private function cleanupAccounts(): int
    {
        // 先删除关联的图片
        DB::table('images')->whereNotNull('transaction_id')->delete();
        DB::table('images')->whereNotNull('draft_id')->delete();
        
        // 删除所有交易记录和草稿
        DB::table('transactions')->delete();
        DB::table('transaction_drafts')->delete();
        
        // 删除所有外勤账号
        return DB::table('field_users')->delete();
    }

    /**
     * 清理地点（完全删除所有地点）
     */
    private function cleanupLocations(): int
    {
        if (!Schema::hasTable('locations')) {
            return 0;
        }
        
        // 先清除所有关联表的 location_id 外键引用
        if (Schema::hasColumn('channels', 'location_id')) {
            DB::table('channels')->update(['location_id' => null]);
        }
        
        if (Schema::hasColumn('transactions', 'location_id')) {
            DB::table('transactions')->update(['location_id' => null]);
        }
        
        if (Schema::hasTable('transaction_drafts') && Schema::hasColumn('transaction_drafts', 'location_id')) {
            DB::table('transaction_drafts')->update(['location_id' => null]);
        }
        
        if (Schema::hasColumn('field_users', 'location_id')) {
            DB::table('field_users')->update(['location_id' => null]);
        }
        
        if (Schema::hasColumn('settlements', 'location_id')) {
            DB::table('settlements')->update(['location_id' => null]);
        }
        
        if (Schema::hasTable('balance_adjustments') && Schema::hasColumn('balance_adjustments', 'location_id')) {
            DB::table('balance_adjustments')->update(['location_id' => null]);
        }
        
        // 删除所有地点
        return DB::table('locations')->delete();
    }


    /**
     * 清理图片（删除所有图片）
     */
    private function cleanupImages(): int
    {
        return DB::table('images')->delete();
    }

    /**
     * 清理余额/本金调整记录（删除所有调整记录）
     * 同时重置 settings 表中的本金和港币余额为 0
     */
    private function cleanupAdjustments(): int
    {
        $totalDeleted = 0;
        
        // 清理 balance_adjustments 表（统一的余额调整表）
        if (Schema::hasTable('balance_adjustments')) {
            $totalDeleted += DB::table('balance_adjustments')->delete();
        }
        
        // 清理 capital_adjustments 表（旧的本金调整表，向后兼容）
        if (Schema::hasTable('capital_adjustments')) {
            $totalDeleted += DB::table('capital_adjustments')->delete();
        }
        
        // 清理 hkd_balance_adjustments 表（旧的港币余额调整表，向后兼容）
        if (Schema::hasTable('hkd_balance_adjustments')) {
            $totalDeleted += DB::table('hkd_balance_adjustments')->delete();
        }
        
        // 重置 settings 表中的本金和港币余额为 0
        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->whereIn('key_name', ['capital', 'system_capital_hkd', 'hkd_balance'])
                ->update(['key_value' => '0']);
        }
        
        return $totalDeleted;
    }

    /**
     * 清理余额结转记录（删除所有结转记录）
     */
    private function cleanupCarryForward(): int
    {
        if (!Schema::hasTable('balance_carry_forward')) {
            return 0;
        }
        
        return DB::table('balance_carry_forward')->delete();
    }

    /**
     * 清理其他支出记录（删除所有其他支出）
     */
    private function cleanupOtherExpenses(): int
    {
        if (!Schema::hasTable('other_expenses')) {
            return 0;
        }
        
        return DB::table('other_expenses')->delete();
    }

    /**
     * 清理统计数据
     */
    private function cleanupStatistics(): int
    {
        $totalDeleted = 0;
        
        // 清理 current_statistics 表
        if (Schema::hasTable('current_statistics')) {
            $totalDeleted += DB::table('current_statistics')->delete();
        }
        
        // 清理 daily_statistics 表（如果存在）
        if (Schema::hasTable('daily_statistics')) {
            $totalDeleted += DB::table('daily_statistics')->delete();
        }
        
        return $totalDeleted;
    }

    /**
     * 清理审计日志（删除所有审计日志）
     */
    private function cleanupAuditLogs(): int
    {
        if (!Schema::hasTable('audit_logs')) {
            return 0;
        }
        
        return DB::table('audit_logs')->delete();
    }

    /**
     * 清理通知记录（删除所有通知）
     */
    private function cleanupNotifications(): int
    {
        if (!Schema::hasTable('notifications')) {
            return 0;
        }
        
        return DB::table('notifications')->delete();
    }
}

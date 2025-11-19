<?php

namespace App\Services;

use App\Models\CleanupLog;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
            'channels' => 0,
            'balances' => 0,
            'accounts' => 0,
            'bills' => 0,
            'locations' => 0,
        ];

        DB::transaction(function () use ($timeRange, $startDate, $endDate, $contentTypes, &$deleted, $operator) {
            // 统一日期范围（起止为 DateTime 字符串）
            $range = match ($timeRange) {
                'day' => [now()->startOfDay()->toDateTimeString(), now()->endOfDay()->toDateTimeString()],
                'month' => [now()->startOfMonth()->toDateTimeString(), now()->endOfMonth()->toDateTimeString()],
                'year' => [now()->startOfYear()->toDateTimeString(), now()->endOfYear()->toDateTimeString()],
                'custom' => [Carbon::parse($startDate)->startOfDay()->toDateTimeString(), Carbon::parse($endDate)->endOfDay()->toDateTimeString()],
                default => null,
            };

            // 针对不同类型执行清理（示范性实现，保守删除）
            if (in_array('balances', $contentTypes)) {
                $q = DB::table('channel_balances');
                // 按业务日期字段 `date` 过滤
                if ($range) {
                    $q->whereBetween('date', [
                        Carbon::parse($range[0])->toDateString(),
                        Carbon::parse($range[1])->toDateString(),
                    ]);
                }
                $deleted['balances'] = $q->delete();
            }

            if (in_array('accounts', $contentTypes)) {
                // 删除未被交易引用的外勤人员账号。若同时勾选 bills 且 time_range=all，将先清空交易，再清空全部账号。
                $q = DB::table('field_users');
                if ($range) {
                    $q->whereBetween('created_at', $range);
                }
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transactions')
                        ->whereColumn('transactions.user_id', 'field_users.id');
                });
                $deleted['accounts'] = $q->delete();
            }

            if (in_array('bills', $contentTypes)) {
                // 使用 Eloquent 模型删除，以触发 deleted 事件回滚渠道余额
                // 注意：只删除未结算的交易记录，已结算的记录会被跳过
                $q = Transaction::query()
                    ->where('settlement_status', 'unsettled'); // 仅删除未结算的交易
                
                // 以提交时间 `submit_time` 为准进行时间范围过滤
                if ($range) {
                    $q->whereBetween('submit_time', $range);
                }
                
                // 分批删除以避免内存问题，同时确保每条记录的deleted事件都被触发
                $transactions = $q->get();
                $deletedCount = 0;
                foreach ($transactions as $transaction) {
                    try {
                        $transaction->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        // 如果删除失败（比如已结算的记录），跳过该记录
                        // 记录日志但不中断清理流程
                        \Log::warning('交易记录删除失败', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                $deleted['bills'] = $deletedCount;
            }

            if (in_array('channels', $contentTypes)) {
                // 删除所有“未被交易引用”的渠道（不再限制状态）。
                // 若已选择清理 bills 且 time_range 为 all，则会先删除所有交易，进而可清空全部渠道。
                $deleted['channels'] = DB::table('channels')
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn('transactions.channel_id', 'channels.id');
                    })
                    ->delete();
            }

            if (in_array('locations', $contentTypes)) {
                $deleted['locations'] = DB::table('locations')->delete();
            }

            // accounts 类型示例（无表占位，保持0）

            CleanupLog::create([
                'operator' => $operator,
                'time_range' => $timeRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'content_types' => $contentTypes,
                'deleted_records' => $deleted,
            ]);
        });

        return $deleted;
    }
}



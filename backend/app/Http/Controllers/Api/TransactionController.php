<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['channel', 'user'])
            ->where('user_id', $request->user()->id);

        // 筛选条件
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // 按交易类型标签筛选
        if ($request->has('transaction_label')) {
            $query->where('transaction_label', $request->transaction_label);
        }

        // 按结余状态筛选
        if ($request->has('settlement_status')) {
            $query->where('settlement_status', $request->settlement_status);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['income', 'outcome', 'exchange', 'instant_buyout'])],
            'rmb_amount' => 'required|numeric|min:0',
            'hkd_amount' => 'required|numeric|min:0',
            'exchange_rate' => 'required|numeric|min:0',
            'instant_rate' => 'nullable|numeric|min:0',
            'channel_id' => 'required|exists:channels,id',
            'location_id' => 'nullable|exists:locations,id',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
            'uuid' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();
            
            // 计算即时买断利润
            $instantProfit = null;
            if ($request->type === 'instant_buyout') {
                $instantProfit = $this->calculateInstantBuyoutProfit(
                    $request->rmb_amount,
                    $request->instant_rate,
                    $request->hkd_amount
                );
            }
            
            $transaction = Transaction::create([
                'uuid' => $request->uuid ?: \Illuminate\Support\Str::uuid(),
                'user_id' => $user->id,
                'type' => $request->type,
                'rmb_amount' => $request->rmb_amount,
                'hkd_amount' => $request->hkd_amount,
                'exchange_rate' => $request->exchange_rate,
                'instant_rate' => $request->instant_rate,
                'instant_profit' => $instantProfit,
                'channel_id' => $request->channel_id,
                'location_id' => $request->location_id ?: $user->location_id,
                'location' => $request->location,
                'notes' => $request->notes,
                'status' => 'success',
                'settlement_status' => 'unsettled',
                'submit_time' => now(),
            ]);

            // 更新渠道交易计数
            $channel = Channel::find($request->channel_id);
            $channel->incrementTransactionCount();

            // 注意：渠道余额更新已在 Transaction::created 事件中自动处理

            DB::commit();

            return response()->json([
                'message' => '交易记录创建成功',
                'transaction' => $transaction->load(['channel', 'user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // 记录详细错误到日志
            Log::error('Transaction creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 返回更详细的错误信息以便调试
            return response()->json([
                'message' => '操作失败，请稍后重试',
                'error_code' => 'TRANSACTION_CREATE_FAILED',
                'debug_info' => [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    public function show(Transaction $transaction, Request $request)
    {
        // 确保用户只能查看自己的交易
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $transaction->load(['channel', 'user', 'images']);
        
        return response()->json($transaction);
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'transactions' => 'required|array',
            'transactions.*.type' => ['required', Rule::in(['income', 'outcome', 'exchange', 'instant_buyout'])],
            'transactions.*.rmb_amount' => 'required|numeric|min:0',
            'transactions.*.hkd_amount' => 'required|numeric',
            'transactions.*.exchange_rate' => 'required|numeric|min:0',
            'transactions.*.instant_rate' => 'nullable|numeric|min:0',
            'transactions.*.channel_id' => 'required|exists:channels,id',
            'transactions.*.location_id' => 'nullable|exists:locations,id',
            'transactions.*.location' => 'nullable|string|max:200',
            'transactions.*.notes' => 'nullable|string',
            'transactions.*.uuid' => 'required|string',
        ]);

        $results = [];
        $user = $request->user();
        $userId = $user->id;

        DB::beginTransaction();
        try {
            foreach ($request->transactions as $transactionData) {
                // 检查UUID是否已存在
                if (Transaction::where('uuid', $transactionData['uuid'])->exists()) {
                    $results[] = [
                        'uuid' => $transactionData['uuid'],
                        'status' => 'skipped',
                        'message' => '记录已存在'
                    ];
                    continue;
                }

                // 计算即时买断利润
                $instantProfit = null;
                if ($transactionData['type'] === 'instant_buyout') {
                    $instantProfit = $this->calculateInstantBuyoutProfit(
                        $transactionData['rmb_amount'],
                        $transactionData['instant_rate'] ?? null,
                        $transactionData['hkd_amount']
                    );
                }
                
                $transaction = Transaction::create([
                    'uuid' => $transactionData['uuid'],
                    'user_id' => $userId,
                    'type' => $transactionData['type'],
                    'rmb_amount' => $transactionData['rmb_amount'],
                    'hkd_amount' => $transactionData['hkd_amount'],
                    'exchange_rate' => $transactionData['exchange_rate'],
                    'instant_rate' => $transactionData['instant_rate'] ?? null,
                    'instant_profit' => $instantProfit,
                    'channel_id' => $transactionData['channel_id'],
                    'location_id' => $transactionData['location_id'] ?? $user->location_id,
                    'location' => $transactionData['location'] ?? null,
                    'notes' => $transactionData['notes'] ?? null,
                    'status' => 'success',
                    'settlement_status' => 'unsettled',
                    'submit_time' => now(),
                ]);

                // 更新渠道交易计数
                $channel = Channel::find($transactionData['channel_id']);
                $channel->incrementTransactionCount();

                // 注意：渠道余额更新已在 Transaction::created 事件中自动处理

                $results[] = [
                    'uuid' => $transactionData['uuid'],
                    'status' => 'success',
                    'id' => $transaction->id
                ];
            }

            DB::commit();

            return response()->json([
                'message' => '批量同步完成',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // 记录详细错误到日志
            Log::error('Batch transaction creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 生产环境不返回详细错误信息
            $message = app()->environment('production') 
                ? '批量操作失败，请稍后重试' 
                : $e->getMessage();
            
            return response()->json([
                'message' => $message,
                'error_code' => 'BATCH_TRANSACTION_FAILED'
            ], 500);
        }
    }

    /**
     * 计算即时买断利润：
     * 先将人民币按买断汇率折算成港币，并在十位四舍五入得到“实值”，
     * 再与实际使用的港币金额求差得到利润。
     */
    protected function calculateInstantBuyoutProfit(?float $rmbAmount, ?float $instantRate, ?float $hkdAmount): ?float
    {
        if (!$instantRate || $instantRate <= 0 || $rmbAmount === null || $hkdAmount === null) {
            return null;
        }

        $convertedHkd = round($rmbAmount / $instantRate, -1, PHP_ROUND_HALF_UP);

        return $convertedHkd - $hkdAmount;
    }

    /**
     * 交易统计：未结算交易统计
     */
    public function statistics(Request $request)
    {
        $userId = $request->user()->id;

        // 查询未结算的交易
        $base = Transaction::where('user_id', $userId)
            ->where('settlement_status', 'unsettled'); // 使用 settlement_status 字段判断未结算

        $totalCount = (clone $base)->count();
        $totalIncome = (float) (clone $base)->where('type', 'income')->sum('hkd_amount');
        $totalExpense = (float) (clone $base)->where('type', 'outcome')->sum('hkd_amount');

        // 统计按交易类型分组的数据
        $byType = [];
        $types = ['income', 'outcome', 'instant_buyout', 'exchange'];
        
        foreach ($types as $type) {
            $typeQuery = (clone $base)->where('type', $type);
            $byType[$type] = [
                'rmb_amount' => round((float) (clone $typeQuery)->sum('rmb_amount'), 2),
                'hkd_amount' => round((float) (clone $typeQuery)->sum('hkd_amount'), 2),
                'count' => (clone $typeQuery)->count(),
                'instant_profit' => $type === 'instant_buyout' 
                    ? round((float) (clone $typeQuery)->sum('instant_profit'), 2) 
                    : 0,
            ];
        }

        // 货币Top3（按HKD汇总绝对值排序）
        $currencyTop3 = [
            ['currency' => 'HKD', 'amount' => (float) (clone $base)->sum('hkd_amount')],
            ['currency' => 'CNY', 'amount' => (float) (clone $base)->sum('rmb_amount')],
        ];
        usort($currencyTop3, fn($a, $b) => abs($b['amount']) <=> abs($a['amount']));
        $currencyTop3 = array_slice($currencyTop3, 0, 3);

        // 渠道Top3（按HKD净额）
        $channelTop3 = Transaction::selectRaw("channel_id, SUM(CASE WHEN type='income' THEN hkd_amount WHEN type='outcome' THEN -hkd_amount ELSE 0 END) as amount")
            ->where('user_id', $userId)
            ->where('settlement_status', 'unsettled')
            ->groupBy('channel_id')
            ->orderByDesc('amount')
            ->with('channel')
            ->limit(3)
            ->get()
            ->map(fn($row) => ['channel' => $row->channel->name ?? (string)$row->channel_id, 'amount' => (float) $row->amount])
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'today_stats' => [
                    'total_count' => $totalCount,
                    'total_income' => round($totalIncome, 2),
                    'total_expense' => round($totalExpense, 2),
                    'net_amount' => round($totalIncome - $totalExpense, 2),
                ],
                'by_type' => $byType,
                'currency_top3' => $currencyTop3,
                'channel_top3' => $channelTop3,
            ],
        ]);
    }

    /**
     * 更新未结算的交易
     */
    public function update(Request $request, Transaction $transaction)
    {
        // 确保用户只能修改自己的交易
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权修改此交易'], 403);
        }

        // 只允许修改未结算的交易
        if ($transaction->settlement_status !== 'unsettled') {
            return response()->json(['message' => '已结算的交易不能修改'], 400);
        }

        $request->validate([
            'rmb_amount' => 'required|numeric|min:0',
            'hkd_amount' => 'required|numeric|min:0',
            'exchange_rate' => 'required|numeric|min:0',
            'instant_rate' => 'nullable|numeric|min:0',
            'channel_id' => 'required|exists:channels,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // 保存旧渠道ID（用于更新交易计数）
            $oldChannelId = $transaction->channel_id;

            // 计算即时买断利润
            $instantProfit = null;
            if ($transaction->type === 'instant_buyout') {
                $instantProfit = $this->calculateInstantBuyoutProfit(
                    $request->rmb_amount,
                    $request->instant_rate,
                    $request->hkd_amount
                );
            }

            // 使用模型 update 方法以触发事件（处理余额变更）
            $transaction->update([
                'rmb_amount' => $request->rmb_amount,
                'hkd_amount' => $request->hkd_amount,
                'exchange_rate' => $request->exchange_rate,
                'instant_rate' => $request->instant_rate,
                'instant_profit' => $instantProfit,
                'channel_id' => $request->channel_id,
                'notes' => $request->notes,
            ]);

            // 如果渠道变更，更新交易计数
            if ($oldChannelId != $request->channel_id) {
                Channel::where('id', $oldChannelId)->decrement('transaction_count');
                Channel::where('id', $request->channel_id)->increment('transaction_count');
            }

            DB::commit();

            // 重新加载交易数据
            $transaction = Transaction::with(['channel', 'user'])->find($transaction->id);

            return response()->json([
                'success' => true,
                'message' => '交易更新成功',
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Transaction update failed', [
                'transaction_id' => $transaction->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 始终返回详细错误信息以便调试
            return response()->json([
                'message' => '更新失败，请稍后重试',
                'error_code' => 'TRANSACTION_UPDATE_FAILED',
                'debug' => [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * 删除未结算的交易
     */
    public function destroy(Request $request, Transaction $transaction)
    {
        // 确保用户只能删除自己的交易
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权删除此交易'], 403);
        }

        // 只允许删除未结算的交易
        if ($transaction->settlement_status !== 'unsettled') {
            return response()->json(['message' => '已结算的交易不能删除'], 400);
        }

        DB::beginTransaction();
        try {
            // 更新渠道交易计数
            Channel::where('id', $transaction->channel_id)->decrement('transaction_count');

            // 使用模型 delete 方法以触发事件（处理余额回滚）
            $transaction->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '交易删除成功'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Transaction delete failed', [
                'transaction_id' => $transaction->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => '删除失败，请稍后重试',
                'error_code' => 'TRANSACTION_DELETE_FAILED'
            ], 500);
        }
    }

    /**
     * 获取渠道余额总览（外勤端使用）
     * 返回当前人民币余额（各渠道汇总）和港币余额（系统设置）
     */
    public function balanceOverview(Request $request)
    {
        // API 版本号，用于确认代码是否更新
        $apiVersion = '2.0.2';
        
        // 计算人民币余额：各渠道人民币余额汇总（动态计算）
        // 注意：需要统计所有渠道（包括停用的），因为停用渠道可能仍有余额
        $channels = Channel::all();
        $totalRmb = 0;
        $channelDetails = [];
        
        foreach ($channels as $channel) {
            $rmbBalance = $channel->getRmbBalance();
            $totalRmb += $rmbBalance;
            $channelDetails[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'rmb_balance' => round($rmbBalance, 2),
            ];
        }
        
        // 获取港币余额：从系统设置中获取（与管理后台保持一致）
        $totalHkd = Setting::get('hkd_balance', 0);
        
        return response()->json([
            'success' => true,
            'api_version' => $apiVersion,
            'data' => [
                'total_rmb' => round($totalRmb, 2),
                'total_hkd' => round($totalHkd, 2),
                'channel_details' => $channelDetails,
            ],
        ]);
    }

}

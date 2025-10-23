<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use App\Models\Channel;
use App\Models\ChannelBalance;
use App\Models\BalanceAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BalanceController extends Controller
{
    protected BalanceService $balanceService;

    public function __construct(BalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
        $this->middleware('auth:sanctum');
    }

    /**
     * 获取余额总览
     */
    public function overview(): JsonResponse
    {
        $this->authorize('view', ChannelBalance::class);
        
        $overview = $this->balanceService->getAllChannelsBalanceOverview();
        
        return response()->json([
            'success' => true,
            'data' => $overview
        ]);
    }

    /**
     * 获取渠道余额历史
     */
    public function history(Request $request, int $channelId): JsonResponse
    {
        $this->authorize('view', ChannelBalance::class);
        
        $request->validate([
            'currency' => 'required|in:RMB,HKD',
            'days' => 'integer|min:1|max:90'
        ]);

        $currency = $request->input('currency');
        $days = $request->input('days', 30);

        $history = $this->balanceService->getBalanceHistory($channelId, $currency, $days);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * 手动调整余额
     */
    public function adjust(Request $request): JsonResponse
    {
        $this->authorize('create', BalanceAdjustment::class);
        
        $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'currency' => 'required|in:RMB,HKD',
            'adjustment_amount' => 'required|numeric',
            'reason' => 'required|string|max:500'
        ]);

        try {
            $adjustment = $this->balanceService->adjustBalance(
                $request->input('channel_id'),
                $request->input('currency'),
                $request->input('adjustment_amount'),
                $request->input('reason'),
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => '余额调整成功',
                'data' => $adjustment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '余额调整失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取调整记录
     */
    public function adjustments(Request $request): JsonResponse
    {
        $this->authorize('view', BalanceAdjustment::class);
        
        $query = BalanceAdjustment::with(['channel', 'user']);

        // 筛选条件
        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        if ($request->has('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // 分页
        $perPage = $request->input('per_page', 15);
        $adjustments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $adjustments
        ]);
    }

    /**
     * 重新计算余额
     */
    public function recalculate(Request $request): JsonResponse
    {
        $this->authorize('create', BalanceAdjustment::class);
        
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'channel_id' => 'nullable|exists:channels,id'
        ]);

        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $channelId = $request->input('channel_id');

            $this->balanceService->recalculateBalances($startDate, $endDate, $channelId);

            return response()->json([
                'success' => true,
                'message' => '余额重新计算完成'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '余额重新计算失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取渠道余额详情
     */
    public function channelDetail(int $channelId): JsonResponse
    {
        $this->authorize('view', ChannelBalance::class);
        
        $channel = Channel::findOrFail($channelId);
        
        // 当前余额
        $rmbBalance = $channel->getRmbBalance();
        $hkdBalance = $channel->getHkdBalance();
        
        // 今日交易统计
        $today = Carbon::today();
        $todayStats = [
            'rmb_income' => $channel->transactions()
                ->where('type', 'income')
                ->whereDate('created_at', $today)
                ->sum('rmb_amount'),
            'rmb_outcome' => $channel->transactions()
                ->where('type', 'outcome')
                ->whereDate('created_at', $today)
                ->sum('rmb_amount'),
            'hkd_income' => $channel->transactions()
                ->where('type', 'income')
                ->whereDate('created_at', $today)
                ->sum('hkd_amount'),
            'hkd_outcome' => $channel->transactions()
                ->where('type', 'outcome')
                ->whereDate('created_at', $today)
                ->sum('hkd_amount'),
            'transaction_count' => $channel->transactions()
                ->whereDate('created_at', $today)
                ->count(),
        ];
        
        // 最近调整记录
        $recentAdjustments = $channel->balanceAdjustments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'channel' => $channel,
                'balances' => [
                    'rmb' => $rmbBalance,
                    'hkd' => $hkdBalance,
                ],
                'today_stats' => $todayStats,
                'recent_adjustments' => $recentAdjustments,
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\ChannelBalance;
use App\Exports\TransactionsExport;
use App\Exports\BalancesExport;
use App\Exports\ChannelSummaryExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 导出交易记录
     */
    public function transactions(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('export_transactions');
        
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'in:excel,csv',
            'channel_id' => 'nullable|exists:channels,id',
            'type' => 'nullable|in:income,outcome,exchange'
        ]);

        try {
            $filename = '交易记录_' . $request->input('start_date') . '_to_' . $request->input('end_date') . '_' . now()->format('YmdHis') . '.xlsx';
            
            $export = new TransactionsExport(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('channel_id'),
                $request->input('type')
            );

            // 直接下载Excel文件
            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出余额报表
     */
    public function balances(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('export_transactions');
        
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'channel_id' => 'nullable|exists:channels,id'
        ]);

        try {
            $filename = '余额报表_' . $request->input('start_date') . '_to_' . $request->input('end_date') . '_' . now()->format('YmdHis') . '.xlsx';
            
            $export = new BalancesExport(
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('channel_id')
            );

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出渠道汇总报表
     */
    public function channelSummary(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('export_transactions');
        
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $filename = '渠道汇总报表_' . $request->input('start_date') . '_to_' . $request->input('end_date') . '_' . now()->format('YmdHis') . '.xlsx';
            
            $export = new ChannelSummaryExport(
                $request->input('start_date'),
                $request->input('end_date')
            );

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTypeLabel(string $type): string
    {
        return match($type) {
            'income' => '入账',
            'outcome' => '出账',
            'exchange' => '兑换',
            default => $type
        };
    }

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => '处理中',
            'success' => '成功',
            'failed' => '失败',
            default => $status
        };
    }

    private function getCategoryLabel(string $category): string
    {
        return match($category) {
            'bank' => '银行',
            'ewallet' => '电子钱包',
            'cash' => '现金',
            'other' => '其他',
            default => $category
        };
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CleanupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CleanupController extends Controller
{
    public function __construct(private CleanupService $cleanupService)
    {
        $this->middleware('auth:sanctum');
    }

    public function cleanup(Request $request)
    {
        $request->validate([
            'time_range' => 'required|in:day,month,year,all,custom',
            'start_date' => 'required_if:time_range,custom|date',
            'end_date' => 'required_if:time_range,custom|date|after_or_equal:start_date',
            'content_types' => 'required|array',
            'content_types.*' => 'in:channels,balances,accounts,bills,locations',
            'verification_password' => 'required|string',
        ]);

        // 二次验证（示例：与env中的ADMIN_VERIFY_PASSWORD比对）
        $expected = env('ADMIN_VERIFY_PASSWORD');
        if (!$expected || $request->verification_password !== $expected) {
            return response()->json([
                'success' => false,
                'error_code' => 1003,
                'message' => '二次验证密码错误',
                'details' => [
                    'field' => 'verification_password',
                    'reason' => '密码不匹配'
                ]
            ], 403);
        }

        $deleted = $this->cleanupService->cleanup($request->all(), $request->user()->name ?? 'system');

        return response()->json([
            'success' => true,
            'message' => '清空成功',
            'deleted_records' => $deleted,
        ]);
    }
}



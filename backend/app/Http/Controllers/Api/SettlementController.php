<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettlementController extends Controller
{
    protected $settlementService;

    public function __construct(SettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    /**
     * 检查今日是否已结余
     * GET /api/settlements/check-today
     */
    public function checkToday()
    {
        try {
            $result = $this->settlementService->checkTodaySettlement();
            
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '检查失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取已有结余的日期列表
     * GET /api/settlements/used-dates
     */
    public function getUsedDates()
    {
        try {
            $dates = $this->settlementService->getUsedSettlementDates();
            
            return response()->json([
                'success' => true,
                'data' => $dates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取日期列表失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取推荐的结余日期
     * GET /api/settlements/recommended-date
     */
    public function getRecommendedDate()
    {
        try {
            $recommendation = $this->settlementService->getRecommendedSettlementDate();
            
            return response()->json([
                'success' => true,
                'data' => $recommendation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取推荐日期失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 验证结余确认密码
     * POST /api/settlements/verify-password
     * 
     * @body {
     *   "password": "用户输入的密码"
     * }
     */
    public function verifyPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
            ], [
                'password.required' => '密码不能为空',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '密码不能为空',
                ], 422);
            }

            $valid = $this->settlementService->verifyPassword($request->input('password'));
            
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => $valid,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '验证失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取结余预览
     * GET /api/settlements/preview?instant_buyout_rate=0.929
     */
    public function preview(Request $request)
    {
        try {
            $instantBuyoutRate = $request->input('instant_buyout_rate');
            $preview = $this->settlementService->getPreview($instantBuyoutRate);
            
            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取结余预览失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 执行结余操作
     * POST /api/settlements
     * 
     * @body {
     *   "password": "确认密码",
     *   "expenses": [
     *     {"item_name": "薪金", "amount": 100},
     *     {"item_name": "金流费用", "amount": 200}
     *   ],
     *   "instant_buyout_rate": 0.929,
     *   "notes": "备注信息"
     * }
     */
    public function store(Request $request)
    {
        try {
            // 验证输入
            $validator = Validator::make($request->all(), [
                'password' => 'required|string',
                'settlement_date' => 'required|date|after_or_equal:today', // 只能选择今天或之后
                'expenses' => 'nullable|array',
                'expenses.*.item_name' => 'required|string|max:100',
                'expenses.*.amount' => 'required|numeric|min:0',
                'instant_buyout_rate' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ], [
                'password.required' => '确认密码不能为空',
                'settlement_date.required' => '结余日期不能为空',
                'settlement_date.date' => '结余日期格式不正确',
                'settlement_date.after_or_equal' => '该日期不可用，请选择其他可用日期',
                'expenses.*.item_name.required' => '支出项目名称不能为空',
                'expenses.*.item_name.max' => '支出项目名称不能超过100个字符',
                'expenses.*.amount.required' => '支出金额不能为空',
                'expenses.*.amount.numeric' => '支出金额必须是数字',
                'expenses.*.amount.min' => '支出金额不能为负数',
                'instant_buyout_rate.numeric' => '即时买断汇率必须是数字',
                'instant_buyout_rate.min' => '即时买断汇率不能为负数',
                'notes.max' => '备注不能超过1000个字符',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '数据验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 获取当前登录用户ID和类型
            $user = $request->user();
            $userId = $user ? $user->id : null;
            
            // 判断用户类型：FieldUser 表示外勤人员，User 表示后台管理员
            $userType = 'admin'; // 默认为管理员
            if ($user && get_class($user) === 'App\Models\FieldUser') {
                $userType = 'field';
            }

            // 执行结余(传入日期)
            $settlement = $this->settlementService->execute(
                $request->input('password'),
                $request->input('expenses', []),
                $request->input('notes'),
                $userId,
                $userType,
                $request->input('settlement_date')  // 传入日期
            );

            // 返回结余详情
            $detail = $this->settlementService->getDetail($settlement->id);

            return response()->json([
                'success' => true,
                'message' => '结余操作成功',
                'data' => $detail,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 获取结余详情
     * GET /api/settlements/{id}
     */
    public function show($id)
    {
        try {
            $detail = $this->settlementService->getDetail($id);
            
            return response()->json([
                'success' => true,
                'data' => $detail,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取结余详情失败: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * 获取结余历史列表
     * GET /api/settlements?page=1&per_page=20
     */
    public function index(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $settlements = $this->settlementService->getHistory($page, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $settlements->items(),
                'pagination' => [
                    'total' => $settlements->total(),
                    'per_page' => $settlements->perPage(),
                    'current_page' => $settlements->currentPage(),
                    'last_page' => $settlements->lastPage(),
                    'from' => $settlements->firstItem(),
                    'to' => $settlements->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取结余历史失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}

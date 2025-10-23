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
     * 获取结余预览
     * GET /api/settlements/preview
     */
    public function preview()
    {
        try {
            $preview = $this->settlementService->getPreview();
            
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
     *   "expenses": [
     *     {"item_name": "薪金", "amount": 100},
     *     {"item_name": "金流费用", "amount": 200}
     *   ],
     *   "notes": "备注信息"
     * }
     */
    public function store(Request $request)
    {
        try {
            // 验证输入
            $validator = Validator::make($request->all(), [
                'expenses' => 'nullable|array',
                'expenses.*.item_name' => 'required|string|max:100',
                'expenses.*.amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ], [
                'expenses.*.item_name.required' => '支出项目名称不能为空',
                'expenses.*.item_name.max' => '支出项目名称不能超过100个字符',
                'expenses.*.amount.required' => '支出金额不能为空',
                'expenses.*.amount.numeric' => '支出金额必须是数字',
                'expenses.*.amount.min' => '支出金额不能为负数',
                'notes.max' => '备注不能超过1000个字符',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '数据验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 执行结余
            $settlement = $this->settlementService->execute(
                $request->input('expenses', []),
                $request->input('notes')
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
                'message' => '结余操作失败: ' . $e->getMessage(),
            ], 500);
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

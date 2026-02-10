<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommonNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 常用备注控制器
 * 
 * 提供常用备注的增删查API接口
 * 
 * @package App\Http\Controllers\Api
 */
class CommonNoteController extends Controller
{
    protected CommonNoteService $commonNoteService;

    public function __construct(CommonNoteService $commonNoteService)
    {
        $this->commonNoteService = $commonNoteService;
    }

    /**
     * 获取当前用户的常用备注列表
     * 
     * GET /api/common-notes
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @response {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "content": "月度结算",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // 获取当前认证用户
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '未认证或认证已过期',
                ], 401);
            }

            // 判断用户类型
            $userType = $this->getUserType($user);
            
            // 获取常用备注列表
            $notes = $this->commonNoteService->getUserCommonNotes($user->id, $userType);
            
            return response()->json([
                'success' => true,
                'data' => $notes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取常用备注列表失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 创建新的常用备注
     * 
     * POST /api/common-notes
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * @bodyParam content string required 备注内容（最大500字符）
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "常用备注添加成功",
     *   "data": {
     *     "id": 1,
     *     "content": "月度结算",
     *     "created_at": "2024-01-01T00:00:00.000000Z",
     *     "updated_at": "2024-01-01T00:00:00.000000Z"
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "备注内容不能为空",
     *   "errors": {
     *     "content": ["备注内容不能为空"]
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // 获取当前认证用户
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '未认证或认证已过期',
                ], 401);
            }

            // 验证请求数据
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:500',
            ], [
                'content.required' => '备注内容不能为空',
                'content.string' => '备注内容必须是字符串',
                'content.max' => '备注内容不能超过500字符',
            ]);

            if ($validator->fails()) {
                $firstError = $validator->errors()->first();
                return response()->json([
                    'success' => false,
                    'message' => $firstError ?: '数据验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 判断用户类型
            $userType = $this->getUserType($user);
            
            // 创建常用备注
            $note = $this->commonNoteService->createCommonNote(
                $user->id,
                $userType,
                $request->input('content')
            );
            
            return response()->json([
                'success' => true,
                'message' => '常用备注添加成功',
                'data' => $note,
            ], 201);
        } catch (ValidationException $e) {
            // 处理服务层的验证异常
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '添加常用备注失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 删除指定的常用备注
     * 
     * DELETE /api/common-notes/{id}
     * 
     * @param Request $request
     * @param int $id 备注ID
     * @return JsonResponse
     * 
     * @response {
     *   "success": true,
     *   "message": "常用备注删除成功"
     * }
     * 
     * @response 403 {
     *   "success": false,
     *   "message": "无权操作此资源"
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "message": "常用备注不存在"
     * }
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // 获取当前认证用户
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '未认证或认证已过期',
                ], 401);
            }

            // 判断用户类型
            $userType = $this->getUserType($user);
            
            // 删除常用备注（服务层会验证权限）
            $this->commonNoteService->deleteCommonNote($user->id, $userType, $id);
            
            return response()->json([
                'success' => true,
                'message' => '常用备注删除成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // 备注不存在或不属于当前用户
            return response()->json([
                'success' => false,
                'message' => '常用备注不存在或无权操作此资源',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除常用备注失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 判断用户类型
     * 
     * @param mixed $user
     * @return string 'admin' 或 'field'
     */
    private function getUserType($user): string
    {
        // 判断用户类型：FieldUser 表示外勤人员，User 表示后台管理员
        if ($user && get_class($user) === 'App\Models\FieldUser') {
            return 'field';
        }
        
        return 'admin';
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminRole
{
    /**
     * 检查用户是否有管理员权限
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // 检查是否已认证
        if (!$user) {
            return response()->json([
                'message' => '未认证',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }
        
        // 检查是否有管理员权限
        // 假设 User 模型有 role 字段或 hasRole 方法
        $isAdmin = false;
        
        if (method_exists($user, 'hasRole')) {
            $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin');
        } elseif (property_exists($user, 'role')) {
            $isAdmin = in_array($user->role, ['admin', 'super_admin']);
        }
        
        if (!$isAdmin) {
            return response()->json([
                'message' => '权限不足',
                'error_code' => 'FORBIDDEN'
            ], 403);
        }
        
        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = FieldUser::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['用户名或密码错误'],
            ]);
        }

        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'username' => ['账户已被禁用'],
            ]);
        }

        // 更新最后登录时间
        $user->update(['last_login_at' => now()]);

        // 删除旧的token
        $user->tokens()->delete();

        // 创建新的token
        $accessToken = $user->createToken('access_token', [], now()->addMinutes(30))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'message' => '登录成功',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'status' => $user->status,
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 30 * 60, // 30分钟
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // 删除当前access token
        $user->tokens()->where('name', 'access_token')->delete();
        
        // 创建新的access token
        $accessToken = $user->createToken('access_token', [], now()->addMinutes(30))->plainTextToken;
        
        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 30 * 60,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => '退出登录成功'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at,
        ]);
    }
}

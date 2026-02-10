<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => '财务管理系统',
        'version' => '1.0.0',
        'api' => url('/api'),
        'admin' => url('/admin'),
        'docs' => url('/api/health')
    ]);
});

// Admin API token generation for Filament components
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/api-token', function (Request $request) {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '未认证'
            ], 401);
        }
        
        // Check if user already has a token named 'filament-api'
        $user->tokens()->where('name', 'filament-api')->delete();
        
        // Create a new token
        $token = $user->createToken('filament-api')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    });
    
    // 常用备注管理 - 专门为Filament提供的web路由
    Route::prefix('admin/common-notes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CommonNoteController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CommonNoteController::class, 'store']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\CommonNoteController::class, 'destroy']);
    });
});



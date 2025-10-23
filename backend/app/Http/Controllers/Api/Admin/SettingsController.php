<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 获取系统设置
     */
    public function index(): JsonResponse
    {
        $this->authorize('view_settings');
        
        $settings = Setting::all()->keyBy('key')->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->value,
                'description' => $setting->description,
                'type' => $setting->type,
                'updated_at' => $setting->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * 更新系统设置
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('edit_settings');
        
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        try {
            foreach ($request->input('settings') as $settingData) {
                Setting::where('key', $settingData['key'])
                    ->update(['value' => $settingData['value']]);
            }

            return response()->json([
                'success' => true,
                'message' => '系统设置更新成功'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '系统设置更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取单个设置
     */
    public function show(string $key): JsonResponse
    {
        $this->authorize('view_settings');
        
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => '设置项不存在'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    /**
     * 重置设置为默认值
     */
    public function reset(Request $request): JsonResponse
    {
        $this->authorize('edit_settings');
        
        $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'string'
        ]);

        try {
            // 这里应该定义默认值
            $defaults = [
                'access_token_expire' => '3600',
                'refresh_token_expire' => '86400',
                'auto_logout_time' => '7200',
                'max_image_size' => '5120',
                'image_quality' => '80',
                'allowed_image_formats' => 'jpg,jpeg,png,gif',
                'exchange_rate_precision' => '5',
                'log_retention_days' => '90',
            ];

            foreach ($request->input('keys') as $key) {
                if (isset($defaults[$key])) {
                    Setting::where('key', $key)
                        ->update(['value' => $defaults[$key]]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => '设置已重置为默认值'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '重置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取系统信息
     */
    public function systemInfo(): JsonResponse
    {
        $this->authorize('view_settings');
        
        $info = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'database' => [
                'driver' => config('database.default'),
                'version' => $this->getDatabaseVersion(),
            ],
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    private function getDatabaseVersion(): string
    {
        try {
            return \DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 设置默认字符串长度
        Schema::defaultStringLength(191);
        
        // 强制HTTPS (生产环境)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // 动态设置会话 Cookie 的 domain，避免返回 localhost 导致 Cookie 不被浏览器接收
        $host = Request::getHost();
        if (!empty($host) && $host !== 'localhost') {
            Config::set('session.domain', $host);
        }

        // 强制在 Filament 面板中加载自定义样式（带版本号避免缓存）
        Filament::registerRenderHook('panels::head.end', function (): string {
            $path = public_path('filament/custom.css');
            $version = is_file($path) ? filemtime($path) : time();
            return '<link rel="stylesheet" href="/filament/custom.css?v=' . $version . '">';
        });
    }
}

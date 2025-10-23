<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\ChannelBalance::class => \App\Policies\ChannelBalancePolicy::class,
        \App\Models\BalanceAdjustment::class => \App\Policies\BalanceAdjustmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // 定义权限
        Gate::define('admin', function ($user) {
            return $user instanceof \App\Models\User && $user->role === 'admin';
        });

        Gate::define('finance', function ($user) {
            return $user instanceof \App\Models\User && in_array($user->role, ['admin', 'finance']);
        });

        // 权限详细定义
        Gate::define('view_settings', function ($user) {
            return $user instanceof \App\Models\User && $user->isAdmin();
        });

        Gate::define('edit_settings', function ($user) {
            return $user instanceof \App\Models\User && $user->isAdmin();
        });

        Gate::define('export_transactions', function ($user) {
            return $user instanceof \App\Models\User && ($user->isAdmin() || $user->isFinance());
        });

        // 系统维护权限（用于显示“数据清理”等系统级入口）
        Gate::define('manage_system', function ($user) {
            return $user instanceof \App\Models\User && $user->isAdmin();
        });
    }
}

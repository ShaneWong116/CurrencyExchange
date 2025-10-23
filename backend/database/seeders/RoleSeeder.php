<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // 重置缓存权限
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 创建权限
        $permissions = [
            // 交易管理
            'view_transactions',
            'create_transactions',
            'edit_transactions',
            'delete_transactions',
            'export_transactions',
            
            // 渠道管理
            'view_channels',
            'create_channels',
            'edit_channels',
            'delete_channels',
            
            // 用户管理
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            
            // 余额管理
            'view_balances',
            'adjust_balances',
            
            // 草稿管理
            'view_drafts',
            'create_drafts',
            'edit_drafts',
            'delete_drafts',
            
            // 系统设置
            'view_settings',
            'edit_settings',
            
            // 审计日志
            'view_audit_logs',
            
            // 图片管理
            'view_images',
            'upload_images',
            'delete_images',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // 创建角色并分配权限
        $adminRole = Role::create(['name' => 'admin']);
        $financeRole = Role::create(['name' => 'finance']);

        // 管理员拥有所有权限
        $adminRole->givePermissionTo(Permission::all());

        // 财务人员权限
        $financeRole->givePermissionTo([
            'view_transactions',
            'export_transactions',
            'view_channels',
            'view_drafts',
            'view_balances',
            'view_audit_logs',
            'view_images',
        ]);
    }
}

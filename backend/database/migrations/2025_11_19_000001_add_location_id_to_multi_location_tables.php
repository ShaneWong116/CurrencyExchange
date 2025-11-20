<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为渠道、余额调整、结算表添加 location_id，实现多店铺独立核算
     */
    public function up(): void
    {
        // 1. 清空相关表数据（仅在本地开发环境执行）
        if (app()->environment('local', 'development')) {
            $driver = DB::getDriverName();
            
            // 根据数据库类型使用不同的清空方法
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('channel_balances')->truncate();
                DB::table('balance_adjustments')->truncate();
                DB::table('settlements')->truncate();
                DB::table('settlement_expenses')->truncate();
                DB::table('channels')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } else {
                // SQLite 和其他数据库使用 delete
                DB::table('channel_balances')->delete();
                DB::table('balance_adjustments')->delete();
                DB::table('settlements')->delete();
                DB::table('settlement_expenses')->delete();
                DB::table('channels')->delete();
            }
        }

        // 2. channels 表添加 location_id
        Schema::table('channels', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->after('id')
                ->constrained('locations')
                ->onDelete('cascade')
                ->comment('所属店铺ID');
            
            $table->index('location_id');
        });

        // 3. balance_adjustments 表添加 location_id
        Schema::table('balance_adjustments', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->after('id')
                ->constrained('locations')
                ->onDelete('cascade')
                ->comment('所属店铺ID');
            
            $table->index('location_id');
        });

        // 4. settlements 表添加 location_id
        Schema::table('settlements', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->after('id')
                ->constrained('locations')
                ->onDelete('cascade')
                ->comment('所属店铺ID');
            
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚时删除 location_id 字段
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('balance_adjustments', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};

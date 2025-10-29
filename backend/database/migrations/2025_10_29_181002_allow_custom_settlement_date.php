<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 移除 settlement_date 的唯一索引,允许用户选择结余日期
     * 系统会在前端提示并禁用已使用的日期,但不再强制数据库级别的唯一性
     */
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 移除唯一索引
            $table->dropUnique('unique_settlement_date');
            
            // 保留普通索引用于查询优化
            $table->index('settlement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 恢复唯一索引
            $table->dropIndex(['settlement_date']);
            $table->unique('settlement_date', 'unique_settlement_date');
        });
    }
};

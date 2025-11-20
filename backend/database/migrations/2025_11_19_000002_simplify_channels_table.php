<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 简化渠道表：移除不需要的字段（code、label、category）
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // 移除渠道代码
            if (Schema::hasColumn('channels', 'code')) {
                $table->dropUnique(['code']); // 先删除唯一索引
                $table->dropColumn('code');
            }
            
            // 移除标签
            if (Schema::hasColumn('channels', 'label')) {
                $table->dropColumn('label');
            }
            
            // 移除分类
            if (Schema::hasColumn('channels', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // 恢复字段
            $table->string('code', 50)->unique()->nullable()->comment('渠道代码');
            $table->string('label', 100)->nullable()->comment('标签（线上/线下/第三方）');
            $table->enum('category', ['bank', 'ewallet', 'cash', 'other'])
                ->default('other')
                ->comment('分类');
        });
    }
};

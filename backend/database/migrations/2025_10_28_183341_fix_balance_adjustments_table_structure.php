<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite 不支持直接修改列,需要重建表
        // 1. 创建新表
        Schema::create('balance_adjustments_new', function (Blueprint $table) {
            $table->id();
            // 新字段: adjustment_category
            $table->enum('adjustment_category', ['capital', 'channel', 'hkd_balance'])
                ->default('channel')
                ->comment('调整分类:本金/渠道余额/港币余额');
            // 新字段: settlement_id
            $table->foreignId('settlement_id')
                ->nullable()
                ->comment('关联结算ID(如果是结算触发)');
            // 原有字段,但 channel_id 改为可空
            $table->foreignId('channel_id')->nullable()->comment('支付渠道ID');
            $table->foreignId('user_id')->comment('操作用户ID');
            $table->enum('currency', ['RMB', 'HKD'])->comment('货币类型');
            $table->decimal('before_amount', 15, 2)->comment('调整前金额');
            $table->decimal('adjustment_amount', 15, 2)->comment('调整金额(正数为增加,负数为减少)');
            $table->decimal('after_amount', 15, 2)->comment('调整后金额');
            $table->enum('type', ['manual', 'system'])->default('manual')->comment('调整类型:手动/系统');
            $table->text('reason')->nullable()->comment('调整原因');
            $table->timestamps();
            
            $table->index(['channel_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('adjustment_category');
        });
        
        // 2. 复制旧表数据到新表,为旧数据设置 adjustment_category = 'channel'
        DB::statement("
            INSERT INTO balance_adjustments_new 
                (id, adjustment_category, settlement_id, channel_id, user_id, currency, 
                 before_amount, adjustment_amount, after_amount, type, reason, created_at, updated_at)
            SELECT 
                id, 'channel', NULL, channel_id, user_id, currency, 
                before_amount, adjustment_amount, after_amount, type, reason, created_at, updated_at
            FROM balance_adjustments
        ");
        
        // 3. 删除旧表
        Schema::dropIfExists('balance_adjustments');
        
        // 4. 重命名新表
        Schema::rename('balance_adjustments_new', 'balance_adjustments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚:重建旧表结构
        Schema::create('balance_adjustments_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->comment('支付渠道ID');
            $table->foreignId('user_id')->comment('操作用户ID');
            $table->enum('currency', ['RMB', 'HKD'])->comment('货币类型');
            $table->decimal('before_amount', 15, 2)->comment('调整前金额');
            $table->decimal('adjustment_amount', 15, 2)->comment('调整金额(正数为增加,负数为减少)');
            $table->decimal('after_amount', 15, 2)->comment('调整后金额');
            $table->enum('type', ['manual', 'system'])->default('manual')->comment('调整类型:手动/系统');
            $table->text('reason')->nullable()->comment('调整原因');
            $table->timestamps();
            
            $table->index(['channel_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
        
        // 复制数据(只复制 adjustment_category = 'channel' 的记录)
        DB::statement("
            INSERT INTO balance_adjustments_old 
                (id, channel_id, user_id, currency, 
                 before_amount, adjustment_amount, after_amount, type, reason, created_at, updated_at)
            SELECT 
                id, channel_id, user_id, currency, 
                before_amount, adjustment_amount, after_amount, type, reason, created_at, updated_at
            FROM balance_adjustments
            WHERE adjustment_category = 'channel' AND channel_id IS NOT NULL
        ");
        
        Schema::dropIfExists('balance_adjustments');
        Schema::rename('balance_adjustments_old', 'balance_adjustments');
    }
};

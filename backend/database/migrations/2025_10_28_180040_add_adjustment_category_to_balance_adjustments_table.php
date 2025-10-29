<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('balance_adjustments', function (Blueprint $table) {
            // 添加调整分类字段：capital=本金, channel=渠道余额, hkd_balance=港币余额
            $table->enum('adjustment_category', ['capital', 'channel', 'hkd_balance'])
                ->default('channel')
                ->after('id')
                ->comment('调整分类：本金/渠道余额/港币余额');
            
            // channel_id 改为可空，因为本金和港币余额调整不需要渠道
            $table->foreignId('channel_id')->nullable()->change();
            
            // 添加结算关联字段（用于本金和港币余额的结算调整）
            $table->foreignId('settlement_id')
                ->nullable()
                ->after('adjustment_category')
                ->constrained('settlements')
                ->onDelete('set null')
                ->comment('关联结算ID（如果是结算触发）');
            
            // 添加索引
            $table->index('adjustment_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_adjustments', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn(['adjustment_category', 'settlement_id']);
            $table->foreignId('channel_id')->nullable(false)->change();
        });
    }
};

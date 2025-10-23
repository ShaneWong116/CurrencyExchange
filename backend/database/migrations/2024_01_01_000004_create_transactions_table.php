<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('唯一标识符（幂等性保证）');
            $table->foreignId('user_id')->constrained('field_users')->comment('外勤人员ID');
            $table->enum('type', ['income', 'outcome', 'exchange'])->comment('交易类型：入账/出账/兑换');
            $table->decimal('rmb_amount', 15, 2)->comment('人民币金额');
            $table->decimal('hkd_amount', 15, 2)->comment('港币金额');
            $table->decimal('exchange_rate', 10, 5)->comment('交易汇率');
            $table->decimal('instant_rate', 10, 5)->nullable()->comment('即时汇率（兑换交易专用）');
            $table->foreignId('channel_id')->constrained('channels')->comment('支付渠道ID');
            $table->string('location', 200)->nullable()->comment('交易地点');
            $table->text('notes')->nullable()->comment('备注');
            $table->enum('status', ['pending', 'success', 'failed'])->default('success')->comment('状态');
            $table->timestamp('submit_time')->useCurrent()->comment('提交时间');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};

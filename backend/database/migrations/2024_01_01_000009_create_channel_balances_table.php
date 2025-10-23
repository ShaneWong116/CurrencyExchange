<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('channel_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->onDelete('cascade')->comment('支付渠道ID');
            $table->date('date')->comment('日期');
            $table->enum('currency', ['RMB', 'HKD'])->comment('货币类型');
            $table->decimal('initial_amount', 15, 2)->default(0)->comment('初始金额');
            $table->decimal('income_amount', 15, 2)->default(0)->comment('当日入账');
            $table->decimal('outcome_amount', 15, 2)->default(0)->comment('当日出账');
            $table->decimal('current_balance', 15, 2)->default(0)->comment('当前余额');
            $table->timestamps();
            
            $table->unique(['channel_id', 'date', 'currency']);
            $table->index(['channel_id', 'date']);
            $table->index(['date', 'currency']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('channel_balances');
    }
};

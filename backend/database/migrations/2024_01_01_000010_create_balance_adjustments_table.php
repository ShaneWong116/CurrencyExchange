<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->onDelete('cascade')->comment('支付渠道ID');
            $table->foreignId('user_id')->constrained('users')->comment('操作用户ID');
            $table->enum('currency', ['RMB', 'HKD'])->comment('货币类型');
            $table->decimal('before_amount', 15, 2)->comment('调整前金额');
            $table->decimal('adjustment_amount', 15, 2)->comment('调整金额（正数为增加，负数为减少）');
            $table->decimal('after_amount', 15, 2)->comment('调整后金额');
            $table->enum('type', ['manual', 'system'])->default('manual')->comment('调整类型：手动/系统');
            $table->text('reason')->nullable()->comment('调整原因');
            $table->timestamps();
            
            $table->index(['channel_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('balance_adjustments');
    }
};

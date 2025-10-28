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
        Schema::create('hkd_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->decimal('before_amount', 15, 2)->comment('调整前港币余额（港币）');
            $table->decimal('after_amount', 15, 2)->comment('调整后港币余额（港币）');
            $table->decimal('adjustment_amount', 15, 2)->comment('调整金额（港币）');
            $table->enum('adjustment_type', ['manual', 'settlement', 'system'])->default('manual')->comment('调整类型：手动、结算、系统');
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->onDelete('set null')->comment('关联结算ID（如果是结算触发）');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('操作人ID');
            $table->text('reason')->nullable()->comment('调整原因');
            $table->timestamps();
            
            $table->index('adjustment_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hkd_balance_adjustments');
    }
};


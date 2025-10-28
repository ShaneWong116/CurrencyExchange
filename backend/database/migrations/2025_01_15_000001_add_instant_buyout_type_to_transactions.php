<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SQLite 不支持直接修改列类型,需要重建表
     */
    public function up(): void
    {
        // SQLite doesn't support modifying column types directly
        // We need to recreate the table
        
        // 禁用外键约束
        DB::statement('PRAGMA foreign_keys = OFF');
        
        // 1. 创建临时表
        Schema::create('transactions_temp', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('唯一标识符（幂等性保证）');
            $table->foreignId('user_id')->constrained('field_users')->comment('外勤人员ID');
            $table->string('type')->comment('交易类型：income/outcome/exchange/instant_buyout');
            $table->decimal('rmb_amount', 15, 2)->comment('人民币金额');
            $table->decimal('hkd_amount', 15, 2)->comment('港币金额');
            $table->decimal('exchange_rate', 10, 5)->comment('交易汇率');
            $table->decimal('instant_rate', 10, 5)->nullable()->comment('即时买断汇率');
            $table->foreignId('channel_id')->constrained('channels')->comment('支付渠道ID');
            $table->foreignId('location_id')->nullable()->constrained('locations')->comment('地点ID');
            $table->string('location', 200)->nullable()->comment('交易地点');
            $table->text('notes')->nullable()->comment('备注');
            $table->string('status')->default('success')->comment('状态');
            $table->string('settlement_status')->default('unsettled')->comment('结余状态');
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->comment('结余ID');
            $table->date('settlement_date')->nullable()->comment('结余日期');
            $table->timestamp('submit_time')->nullable()->useCurrent()->comment('提交时间');
            $table->string('transaction_label')->nullable()->comment('交易类型标签');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
        
        // 2. 复制数据 - 明确指定字段顺序避免错位
        DB::statement('
            INSERT INTO transactions_temp 
            (id, uuid, user_id, type, rmb_amount, hkd_amount, exchange_rate, instant_rate, 
             channel_id, location_id, location, notes, status, settlement_status, 
             settlement_id, settlement_date, submit_time, transaction_label, created_at, updated_at)
            SELECT 
             id, uuid, user_id, type, rmb_amount, hkd_amount, exchange_rate, instant_rate,
             channel_id, location_id, location, notes, status, settlement_status,
             settlement_id, settlement_date, submit_time, transaction_label, created_at, updated_at
            FROM transactions
        ');
        
        // 3. 删除旧表
        Schema::dropIfExists('transactions');
        
        // 4. 重命名临时表
        Schema::rename('transactions_temp', 'transactions');
        
        // 启用外键约束
        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚操作：重新创建不包含 instant_buyout 的表
        // 注意：如果有 instant_buyout 类型的数据，回滚会失败
        
        Schema::create('transactions_temp', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('唯一标识符（幂等性保证）');
            $table->foreignId('user_id')->constrained('field_users')->comment('外勤人员ID');
            $table->string('type')->comment('交易类型：income/outcome/exchange');
            $table->decimal('rmb_amount', 15, 2)->comment('人民币金额');
            $table->decimal('hkd_amount', 15, 2)->comment('港币金额');
            $table->decimal('exchange_rate', 10, 5)->comment('交易汇率');
            $table->decimal('instant_rate', 10, 5)->nullable()->comment('即时汇率');
            $table->foreignId('channel_id')->constrained('channels')->comment('支付渠道ID');
            $table->foreignId('location_id')->nullable()->constrained('locations')->comment('地点ID');
            $table->string('location', 200)->nullable()->comment('交易地点');
            $table->text('notes')->nullable()->comment('备注');
            $table->string('status')->default('success')->comment('状态');
            $table->string('settlement_status')->default('unsettled')->comment('结余状态');
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->comment('结余ID');
            $table->date('settlement_date')->nullable()->comment('结余日期');
            $table->timestamp('submit_time')->nullable()->useCurrent()->comment('提交时间');
            $table->string('transaction_label')->nullable()->comment('交易类型标签');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
        
        // 删除 instant_buyout 类型的记录
        DB::statement("DELETE FROM transactions WHERE type = 'instant_buyout'");
        
        DB::statement('INSERT INTO transactions_temp SELECT * FROM transactions');
        Schema::dropIfExists('transactions');
        Schema::rename('transactions_temp', 'transactions');
    }
};


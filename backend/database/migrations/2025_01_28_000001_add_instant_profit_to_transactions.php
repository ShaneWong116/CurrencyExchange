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
     * 添加 instant_profit 字段用于存储即时买断利润
     */
    public function up(): void
    {
        // 禁用外键约束
        DB::statement('PRAGMA foreign_keys = OFF');
        
        // 先删除可能存在的临时表和索引
        try {
            DB::statement('DROP INDEX IF EXISTS transactions_temp_user_id_created_at_index');
            DB::statement('DROP INDEX IF EXISTS transactions_temp_channel_id_created_at_index');
            DB::statement('DROP INDEX IF EXISTS transactions_temp_type_created_at_index');
            DB::statement('DROP INDEX IF EXISTS transactions_temp_uuid_unique');
        } catch (\Exception $e) {
            // 忽略索引不存在的错误
        }
        DB::statement('DROP TABLE IF EXISTS transactions_temp');
        
        // 1. 创建临时表（添加 instant_profit 字段）
        Schema::create('transactions_temp', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('唯一标识符（幂等性保证）');
            $table->foreignId('user_id')->constrained('field_users')->comment('外勤人员ID');
            $table->string('type')->comment('交易类型：income/outcome/exchange/instant_buyout');
            $table->decimal('rmb_amount', 15, 2)->comment('人民币金额');
            $table->decimal('hkd_amount', 15, 2)->comment('港币金额');
            $table->decimal('exchange_rate', 10, 5)->comment('交易汇率');
            $table->decimal('instant_rate', 10, 5)->nullable()->comment('即时买断汇率');
            $table->decimal('instant_profit', 15, 2)->nullable()->comment('即时买断利润（录入时计算）');
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
        
        // 2. 复制数据，instant_profit 默认为 NULL
        DB::statement('
            INSERT INTO transactions_temp 
            (id, uuid, user_id, type, rmb_amount, hkd_amount, exchange_rate, instant_rate, instant_profit,
             channel_id, location_id, location, notes, status, settlement_status, 
             settlement_id, settlement_date, submit_time, transaction_label, created_at, updated_at)
            SELECT 
             id, uuid, user_id, type, rmb_amount, hkd_amount, exchange_rate, instant_rate, NULL as instant_profit,
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
        // 禁用外键约束
        DB::statement('PRAGMA foreign_keys = OFF');
        
        // 1. 创建临时表（不包含 instant_profit）
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
        
        // 2. 复制数据（去掉 instant_profit 字段）
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
};


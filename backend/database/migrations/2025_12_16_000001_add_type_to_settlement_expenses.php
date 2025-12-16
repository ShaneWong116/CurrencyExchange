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
        Schema::table('settlement_expenses', function (Blueprint $table) {
            // 添加类型字段：expense（支出）或 income（收入）
            $table->string('type', 20)->default('expense')->after('settlement_id');
        });
        
        // 在 settlements 表添加其他收入总额字段
        Schema::table('settlements', function (Blueprint $table) {
            $table->decimal('other_incomes_total', 15, 2)->default(0)->after('other_expenses_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlement_expenses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn('other_incomes_total');
        });
    }
};

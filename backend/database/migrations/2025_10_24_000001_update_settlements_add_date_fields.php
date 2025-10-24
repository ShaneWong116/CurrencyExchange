<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 添加结余日期字段（唯一，每天最多一条）
            $table->date('settlement_date')->after('id')->comment('结余日期(YYYY-MM-DD)');
            
            // 添加执行结余的用户ID
            $table->foreignId('created_by')->nullable()->after('notes')->comment('执行结余的用户ID');
            
            // 添加唯一索引，确保每天最多一条记录
            $table->unique('settlement_date', 'unique_settlement_date');
        });
    }

    public function down()
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique('unique_settlement_date');
            $table->dropColumn(['settlement_date', 'created_by']);
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 修改 settlement_rate 字段精度从 decimal(10,5) 改为 decimal(10,3)
            $table->decimal('settlement_rate', 10, 3)->comment('结余汇率(CNY/HKD)')->change();
        });
    }

    public function down()
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 回滚到原来的精度
            $table->decimal('settlement_rate', 10, 5)->comment('结余汇率(CNY/HKD)')->change();
        });
    }
};


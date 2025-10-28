<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->decimal('previous_capital', 15, 2)->comment('结余前本金(HKD)');
            $table->decimal('previous_hkd_balance', 15, 2)->comment('结余前港币结余(HKD)');
            $table->decimal('profit', 15, 3)->comment('本次利润(HKD)');
            $table->decimal('other_expenses_total', 15, 2)->default(0)->comment('其他支出总额(HKD)');
            $table->decimal('new_capital', 15, 2)->comment('结余后本金(HKD)');
            $table->decimal('new_hkd_balance', 15, 2)->comment('结余后港币结余(HKD)');
            $table->decimal('settlement_rate', 10, 3)->comment('结余汇率(CNY/HKD)');
            $table->decimal('rmb_balance_total', 15, 2)->comment('人民币余额汇总(CNY)');
            $table->integer('sequence_number')->comment('结余顺序编号');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('settlements');
    }
};

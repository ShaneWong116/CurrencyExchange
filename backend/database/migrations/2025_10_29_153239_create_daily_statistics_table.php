<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('current_statistics', function (Blueprint $table) {
            $table->id();
            
            // 统计类型：dashboard（仪表盘汇总）或 channel_{id}（渠道明细）
            $table->string('stat_type', 50)->comment('统计类型');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联ID（如渠道ID）');
            
            // 交易数量统计
            $table->integer('transaction_count')->default(0)->comment('交易总数');
            $table->integer('income_count')->default(0)->comment('入账笔数');
            $table->integer('outcome_count')->default(0)->comment('出账笔数');
            $table->integer('instant_buyout_count')->default(0)->comment('即时买断笔数');
            
            // 人民币统计
            $table->decimal('rmb_income', 15, 2)->default(0)->comment('人民币入账总额');
            $table->decimal('rmb_outcome', 15, 2)->default(0)->comment('人民币出账总额');
            $table->decimal('rmb_instant_buyout', 15, 2)->default(0)->comment('即时买断人民币总额');
            
            // 港币统计
            $table->decimal('hkd_income', 15, 2)->default(0)->comment('港币入账总额');
            $table->decimal('hkd_outcome', 15, 2)->default(0)->comment('港币出账总额');
            $table->decimal('hkd_instant_buyout', 15, 2)->default(0)->comment('即时买断港币总额');
            
            $table->timestamps();
            
            // 唯一索引：确保每种统计类型只有一条记录
            $table->unique(['stat_type', 'reference_id']);
            $table->index('stat_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('current_statistics');
    }
};

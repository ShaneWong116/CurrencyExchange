<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transaction_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('唯一标识符');
            $table->foreignId('user_id')->constrained('field_users')->onDelete('cascade')->comment('外勤人员ID');
            $table->enum('type', ['income', 'outcome', 'exchange'])->comment('交易类型');
            $table->decimal('rmb_amount', 15, 2)->nullable()->comment('人民币金额');
            $table->decimal('hkd_amount', 15, 2)->nullable()->comment('港币金额');
            $table->decimal('exchange_rate', 10, 5)->nullable()->comment('交易汇率');
            $table->decimal('instant_rate', 10, 5)->nullable()->comment('即时汇率');
            $table->foreignId('channel_id')->nullable()->constrained('channels')->comment('支付渠道ID');
            $table->string('location', 200)->nullable()->comment('交易地点');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamp('last_modified')->useCurrent()->useCurrentOnUpdate()->comment('最后修改时间');
            $table->timestamps();
            
            $table->index(['user_id', 'last_modified']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaction_drafts');
    }
};

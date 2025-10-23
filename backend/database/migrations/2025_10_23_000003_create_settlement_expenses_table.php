<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settlement_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->onDelete('cascade')->comment('所属结余ID');
            $table->string('item_name', 100)->comment('支出项目名称');
            $table->decimal('amount', 15, 2)->comment('支出金额(HKD)');
            $table->timestamps();
            
            $table->index('settlement_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('settlement_expenses');
    }
};

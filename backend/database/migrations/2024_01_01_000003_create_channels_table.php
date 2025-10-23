<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('渠道名称');
            $table->string('code', 50)->unique()->comment('渠道代码');
            $table->string('label', 100)->nullable()->comment('标签（线上/线下/第三方）');
            $table->enum('category', ['bank', 'ewallet', 'cash', 'other'])->default('other')->comment('分类');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('启用状态');
            $table->unsignedInteger('transaction_count')->default(0)->comment('累计交易次数');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('channels');
    }
};

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
        Schema::create('quarterly_dividends', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->decimal('amount', 15, 2)->default(0); // 季度分红金额
            $table->text('remarks')->nullable(); // 备注
            $table->timestamps();
            
            // 唯一索引：每年每月只能有一条记录
            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarterly_dividends');
    }
};

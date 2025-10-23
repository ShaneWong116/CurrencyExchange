<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('other_expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('period_type', ['monthly', 'yearly']);
            $table->string('period_value', 10)->comment('202501 或 2025');
            $table->string('expense_name', 100);
            $table->decimal('amount_hkd', 15, 2);
            $table->timestamps();
            $table->index(['period_type', 'period_value'], 'idx_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('other_expenses');
    }
};



<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('balance_carry_forward', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels');
            $table->date('date');
            $table->decimal('balance_cny', 15, 2)->comment('人民币余额');
            $table->timestamps();
            $table->unique(['channel_id', 'date'], 'unique_channel_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('balance_carry_forward');
    }
};



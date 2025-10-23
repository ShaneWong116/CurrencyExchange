<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('settlement_status', ['unsettled', 'settled'])->default('unsettled')->after('status')->comment('结余状态');
            $table->foreignId('settlement_id')->nullable()->after('settlement_status')->constrained('settlements')->comment('所属结余批次ID');
            
            $table->index(['settlement_status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn(['settlement_status', 'settlement_id']);
        });
    }
};

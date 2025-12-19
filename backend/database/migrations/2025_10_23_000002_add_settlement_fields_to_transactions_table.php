<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'settlement_status')) {
                $table->enum('settlement_status', ['unsettled', 'settled'])->default('unsettled')->after('status')->comment('结余状态');
            }
            if (!Schema::hasColumn('transactions', 'settlement_id')) {
                $table->foreignId('settlement_id')->nullable()->after('settlement_status')->constrained('settlements')->comment('所属结余批次ID');
            }
            
            // Only add index if it doesn't exist
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('transactions');
            if (!isset($indexes['transactions_settlement_status_created_at_index'])) {
                $table->index(['settlement_status', 'created_at']);
            }
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

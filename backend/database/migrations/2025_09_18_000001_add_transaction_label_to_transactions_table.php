<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'transaction_label')) {
                $table->string('transaction_label', 50)
                    ->nullable()
                    ->after('type')
                    ->comment('交易类型标签（如：即时买断）');
                $table->index('transaction_label', 'idx_transaction_label');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'transaction_label')) {
                $table->dropIndex('idx_transaction_label');
                $table->dropColumn('transaction_label');
            }
        });
    }
};



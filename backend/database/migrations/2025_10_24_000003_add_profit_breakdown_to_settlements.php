<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 添加利润明细字段
            $table->decimal('outgoing_profit', 15, 3)->default(0)->after('profit')->comment('出账利润(HKD)');
            $table->decimal('instant_profit', 15, 3)->default(0)->after('outgoing_profit')->comment('即时买断利润(HKD)');
            $table->decimal('instant_buyout_rate', 10, 5)->nullable()->after('instant_profit')->comment('即时买断汇率(CNY/HKD)');
        });
    }

    public function down()
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['outgoing_profit', 'instant_profit', 'instant_buyout_rate']);
        });
    }
};


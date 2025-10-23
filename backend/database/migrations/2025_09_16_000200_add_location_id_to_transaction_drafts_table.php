<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('transaction_drafts', 'location_id')) {
            Schema::table('transaction_drafts', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('channel_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('transaction_drafts', 'location_id')) {
            Schema::table('transaction_drafts', function (Blueprint $table) {
                $table->dropColumn('location_id');
            });
        }
    }
};



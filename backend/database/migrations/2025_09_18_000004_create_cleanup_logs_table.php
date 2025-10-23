<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operator', 50);
            $table->string('time_range', 20);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('content_types');
            $table->json('deleted_records');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cleanup_logs');
    }
};



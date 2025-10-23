<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 100)->unique()->comment('配置项名称');
            $table->text('key_value')->comment('配置值');
            $table->string('description', 500)->nullable()->comment('描述');
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string')->comment('值类型');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};

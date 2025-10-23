<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('field_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique()->comment('账号');
            $table->string('password')->comment('密码哈希');
            $table->string('name', 100)->comment('姓名');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('field_users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('操作用户ID');
            $table->string('action', 100)->comment('操作类型');
            $table->string('model_type', 100)->comment('模型类型');
            $table->unsignedBigInteger('model_id')->nullable()->comment('模型ID');
            $table->json('old_values')->nullable()->comment('修改前的值');
            $table->json('new_values')->nullable()->comment('修改后的值');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};

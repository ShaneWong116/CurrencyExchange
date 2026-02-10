<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('common_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type', 50); // 'App\Models\User' or 'App\Models\FieldUser'
            $table->text('content'); // 备注内容，最大500字符
            $table->timestamps();
            
            // 复合索引：用于查询特定用户的备注
            $table->index(['user_id', 'user_type'], 'idx_user');
            
            // 索引：用于按创建时间排序
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('common_notes');
    }
};

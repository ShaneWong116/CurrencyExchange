<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('图片唯一标识');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('cascade')->comment('关联交易ID');
            $table->foreignId('draft_id')->nullable()->constrained('transaction_drafts')->onDelete('cascade')->comment('关联草稿ID');
            $table->string('original_name')->comment('原始文件名');
            $table->unsignedInteger('file_size')->comment('文件大小（字节）');
            $table->string('mime_type', 100)->comment('MIME类型');
            $table->unsignedInteger('width')->nullable()->comment('图片宽度');
            $table->unsignedInteger('height')->nullable()->comment('图片高度');
            $table->longText('file_content')->comment('图片Base64内容');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['transaction_id']);
            $table->index(['draft_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('images');
    }
};

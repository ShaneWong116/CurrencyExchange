<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 创建 locations 表
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('地点名称');
            $table->string('code', 50)->unique()->comment('地点编码');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
        });

        // field_users 增加 location_id
        if (!Schema::hasColumn('field_users', 'location_id')) {
            Schema::table('field_users', function (Blueprint $table) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('locations')
                    ->nullOnDelete()
                    ->comment('所属地点');
            });
        }

        // transactions 增加 location_id（保留原有 location 文本以兼容历史数据）
        if (!Schema::hasColumn('transactions', 'location_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('channel_id')
                    ->constrained('locations')
                    ->nullOnDelete()
                    ->comment('地点ID');
            });
        }
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'location_id')) {
                $table->dropConstrainedForeignId('location_id');
            }
        });

        Schema::table('field_users', function (Blueprint $table) {
            if (Schema::hasColumn('field_users', 'location_id')) {
                $table->dropConstrainedForeignId('location_id');
            }
        });

        Schema::dropIfExists('locations');
    }
};



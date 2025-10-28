<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settlements', function (Blueprint $table) {
            // 检查列是否存在再添加
            if (!Schema::hasColumn('settlements', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('notes')->comment('操作人ID');
            }
            
            if (!Schema::hasColumn('settlements', 'created_by_type')) {
                $table->string('created_by_type', 50)->nullable()->after('created_by')->comment('操作人类型: admin 或 field');
            }
            
            // 添加索引(如果不存在)
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('settlements');
            if (!isset($indexesFound['settlements_created_by_created_by_type_index'])) {
                $table->index(['created_by', 'created_by_type']);
            }
        });
    }

    public function down()
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['created_by', 'created_by_type']);
            $table->dropColumn(['created_by', 'created_by_type']);
        });
    }
};


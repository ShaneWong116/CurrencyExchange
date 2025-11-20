<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Location;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 将现有数据迁移到默认店铺（仅在线上生产环境执行）
     */
    public function up(): void
    {
        // 仅在生产环境执行数据迁移
        if (!app()->environment('local', 'development')) {
            // 获取或创建默认店铺
            $defaultLocation = Location::firstOrCreate(
                ['code' => 'DEFAULT'],
                [
                    'name' => '默认店铺',
                    'remark' => '系统升级时自动创建，用于承接历史数据',
                    'status' => 'active'
                ]
            );

            // 将所有 location_id 为 NULL 的渠道分配给默认店铺
            $updatedChannels = DB::table('channels')
                ->whereNull('location_id')
                ->update(['location_id' => $defaultLocation->id]);
            
            \Log::info("迁移渠道数据到默认店铺", ['count' => $updatedChannels]);

            // 将所有 location_id 为 NULL 的余额调整分配给默认店铺
            $updatedAdjustments = DB::table('balance_adjustments')
                ->whereNull('location_id')
                ->update(['location_id' => $defaultLocation->id]);
            
            \Log::info("迁移余额调整数据到默认店铺", ['count' => $updatedAdjustments]);

            // 将所有 location_id 为 NULL 的结算分配给默认店铺
            $updatedSettlements = DB::table('settlements')
                ->whereNull('location_id')
                ->update(['location_id' => $defaultLocation->id]);
            
            \Log::info("迁移结算数据到默认店铺", ['count' => $updatedSettlements]);
            
            echo "\n✅ 数据迁移完成：\n";
            echo "   - 渠道: {$updatedChannels} 条\n";
            echo "   - 余额调整: {$updatedAdjustments} 条\n";
            echo "   - 结算: {$updatedSettlements} 条\n";
            echo "   - 默认店铺ID: {$defaultLocation->id}\n\n";
        } else {
            echo "\n⏭️  本地开发环境，跳过数据迁移\n\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚时将默认店铺的数据的 location_id 设为 NULL
        if (!app()->environment('local', 'development')) {
            $defaultLocation = Location::where('code', 'DEFAULT')->first();
            
            if ($defaultLocation) {
                DB::table('channels')
                    ->where('location_id', $defaultLocation->id)
                    ->update(['location_id' => null]);
                    
                DB::table('balance_adjustments')
                    ->where('location_id', $defaultLocation->id)
                    ->update(['location_id' => null]);
                    
                DB::table('settlements')
                    ->where('location_id', $defaultLocation->id)
                    ->update(['location_id' => null]);
                
                echo "\n✅ 数据回滚完成\n\n";
            }
        }
    }
};

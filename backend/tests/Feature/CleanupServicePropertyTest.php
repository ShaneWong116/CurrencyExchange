<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FieldUser;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionDraft;
use App\Models\Image;
use App\Models\Settlement;
use App\Models\Setting;
use App\Services\CleanupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Property-Based Tests for CleanupService
 * 
 * These tests verify correctness properties of the cleanup logic
 * by running multiple iterations with randomly generated data.
 */
class CleanupServicePropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected CleanupService $cleanupService;
    protected const ITERATIONS = 20;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupService = new CleanupService();
        
        // Initialize required settings if not exists
        if (!Setting::where('key', 'capital')->exists()) {
            Setting::set('capital', 1000000, '系统本金(HKD)', 'number');
        }
        if (!Setting::where('key', 'hkd_balance')->exists()) {
            Setting::set('hkd_balance', 500000, '港币结余(HKD)', 'number');
        }
    }

    /**
     * Helper method to clean test data between iterations
     */
    protected function refreshTestData(): void
    {
        // Delete in correct order to avoid foreign key issues
        DB::table('images')->delete();
        DB::table('transactions')->delete();
        DB::table('transaction_drafts')->delete();
        
        if (Schema::hasTable('settlement_expenses')) {
            DB::table('settlement_expenses')->delete();
        }
        if (Schema::hasTable('balance_adjustments')) {
            DB::table('balance_adjustments')->where('settlement_id', '!=', null)->delete();
        }
        
        DB::table('settlements')->delete();
        DB::table('channels')->delete();
        DB::table('field_users')->delete();
        
        if (Schema::hasTable('cleanup_logs')) {
            DB::table('cleanup_logs')->delete();
        }
        if (Schema::hasTable('channel_balances')) {
            DB::table('channel_balances')->delete();
        }
    }


    /**
     * Property 1: Referenced entities preservation
     * 
     * **Feature: data-cleanup-update, Property 1: Referenced entities preservation**
     * 
     * *For any* field user or channel that is referenced by existing transactions or drafts,
     * after cleanup with the corresponding option selected, that entity should still exist.
     * 
     * **Validates: Requirements 4.2, 12.2**
     * 
     * @test
     */
    public function property_referenced_entities_are_preserved_after_cleanup(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $referencedChannel = Channel::create([
                'name' => 'Referenced Channel ' . $i,
                'code' => 'REF' . $i,
                'status' => 'active',
            ]);
            
            $referencedFieldUser = FieldUser::create([
                'username' => 'referenced_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Referenced User ' . $i,
                'status' => 'active',
            ]);
            
            // Create a settled transaction that references both
            Transaction::create([
                'user_id' => $referencedFieldUser->id,
                'channel_id' => $referencedChannel->id,
                'type' => 'income',
                'rmb_amount' => 1000,
                'hkd_amount' => 1000,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'settlement_status' => 'settled',
                'submit_time' => now(),
            ]);
            
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['accounts', 'channels'],
            ], 'test_operator');
            
            $this->assertDatabaseHas('channels', ['id' => $referencedChannel->id]);
            $this->assertDatabaseHas('field_users', ['id' => $referencedFieldUser->id]);
        }
    }

    /**
     * Property 2: Unreferenced entities deletion
     * 
     * **Feature: data-cleanup-update, Property 2: Unreferenced entities deletion**
     * 
     * *For any* field user or channel that has no references from transactions or drafts,
     * after cleanup with the corresponding option selected, that entity should not exist.
     * 
     * **Validates: Requirements 4.2, 12.2**
     * 
     * @test
     */
    public function property_unreferenced_entities_are_deleted_after_cleanup(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $unreferencedChannel = Channel::create([
                'name' => 'Unreferenced Channel ' . $i,
                'code' => 'UNR' . $i,
                'status' => 'active',
            ]);
            
            $unreferencedFieldUser = FieldUser::create([
                'username' => 'unreferenced_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Unreferenced User ' . $i,
                'status' => 'active',
            ]);
            
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['accounts', 'channels'],
            ], 'test_operator');
            
            $this->assertDatabaseMissing('channels', ['id' => $unreferencedChannel->id]);
            $this->assertDatabaseMissing('field_users', ['id' => $unreferencedFieldUser->id]);
        }
    }


    /**
     * Property 3: Settled transactions preservation
     * 
     * **Feature: data-cleanup-update, Property 3: Settled transactions preservation**
     * 
     * *For any* transaction with settlement_status = 'settled',
     * after cleanup with "交易记录" selected, that transaction should still exist.
     * 
     * **Validates: Requirements 5.2**
     * 
     * @test
     */
    public function property_settled_transactions_are_preserved_after_cleanup(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $channel = Channel::create([
                'name' => 'Test Channel ' . $i,
                'code' => 'TST' . $i,
                'status' => 'active',
            ]);
            
            $fieldUser = FieldUser::create([
                'username' => 'test_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Test User ' . $i,
                'status' => 'active',
            ]);
            
            $settledTransaction = Transaction::create([
                'user_id' => $fieldUser->id,
                'channel_id' => $channel->id,
                'type' => 'income',
                'rmb_amount' => rand(100, 10000),
                'hkd_amount' => rand(100, 10000),
                'exchange_rate' => 0.95,
                'status' => 'completed',
                'settlement_status' => 'settled',
                'submit_time' => now(),
            ]);
            
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['bills'],
            ], 'test_operator');
            
            $this->assertDatabaseHas('transactions', ['id' => $settledTransaction->id]);
        }
    }

    /**
     * Property 4: Cascade deletion for transactions
     * 
     * **Feature: data-cleanup-update, Property 4: Cascade deletion for transactions**
     * 
     * *For any* deleted transaction, all its associated image records should also be deleted.
     * 
     * **Validates: Requirements 5.3, 6.3**
     * 
     * @test
     */
    public function property_transaction_images_are_cascade_deleted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $channel = Channel::create([
                'name' => 'Test Channel ' . $i,
                'code' => 'TST' . $i,
                'status' => 'active',
            ]);
            
            $fieldUser = FieldUser::create([
                'username' => 'test_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Test User ' . $i,
                'status' => 'active',
            ]);
            
            $transaction = Transaction::create([
                'user_id' => $fieldUser->id,
                'channel_id' => $channel->id,
                'type' => 'income',
                'rmb_amount' => rand(100, 10000),
                'hkd_amount' => rand(100, 10000),
                'exchange_rate' => 0.95,
                'status' => 'completed',
                'settlement_status' => 'unsettled',
                'submit_time' => now(),
            ]);
            
            $imageCount = rand(1, 3);
            $imageIds = [];
            for ($j = 0; $j < $imageCount; $j++) {
                $image = Image::create([
                    'transaction_id' => $transaction->id,
                    'original_name' => 'test_' . $j . '.jpg',
                    'file_size' => 1000,
                    'mime_type' => 'image/jpeg',
                    'width' => 100,
                    'height' => 100,
                    'file_content' => base64_encode('test'),
                ]);
                $imageIds[] = $image->id;
            }
            
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['bills'],
            ], 'test_operator');
            
            $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
            foreach ($imageIds as $imageId) {
                $this->assertDatabaseMissing('images', ['id' => $imageId]);
            }
        }
    }


    /**
     * Property 5: Settlement cleanup cascade
     * 
     * **Feature: data-cleanup-update, Property 5: Settlement cleanup cascade**
     * 
     * *For any* deleted settlement record, all associated settlement_expenses and balance_adjustments
     * should also be deleted, and associated transactions should have settlement_status = 'unsettled'.
     * 
     * **Validates: Requirements 7.3, 7.4**
     * 
     * @test
     */
    public function property_settlement_cleanup_cascades_correctly(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $channel = Channel::create([
                'name' => 'Test Channel ' . $i,
                'code' => 'TST' . $i,
                'status' => 'active',
            ]);
            
            $fieldUser = FieldUser::create([
                'username' => 'test_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Test User ' . $i,
                'status' => 'active',
            ]);
            
            // 创建结算记录
            $settlement = Settlement::create([
                'settlement_date' => now()->toDateString(),
                'previous_capital' => 100000,
                'previous_hkd_balance' => 50000,
                'profit' => 1000,
                'other_expenses_total' => 100,
                'new_capital' => 100900,
                'new_hkd_balance' => 50000,
                'settlement_rate' => 0.95,
                'rmb_balance_total' => 100000,
                'sequence_number' => $i + 1,
            ]);
            
            // 创建结算支出记录
            $expenseIds = [];
            for ($j = 0; $j < rand(1, 3); $j++) {
                $expenseId = DB::table('settlement_expenses')->insertGetId([
                    'settlement_id' => $settlement->id,
                    'item_name' => 'Expense ' . $j,
                    'amount' => rand(10, 100),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $expenseIds[] = $expenseId;
            }
            
            // 创建余额调整记录（关联到结算）
            $adjustmentIds = [];
            for ($j = 0; $j < rand(1, 2); $j++) {
                $amount = rand(100, 1000);
                $adjustmentId = DB::table('balance_adjustments')->insertGetId([
                    'settlement_id' => $settlement->id,
                    'user_id' => 1,
                    'currency' => 'HKD',
                    'adjustment_category' => 'capital',
                    'before_amount' => 10000,
                    'adjustment_amount' => $amount,
                    'after_amount' => 10000 + $amount,
                    'type' => 'increase',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $adjustmentIds[] = $adjustmentId;
            }
            
            // 创建已结算的交易记录
            $transaction = Transaction::create([
                'user_id' => $fieldUser->id,
                'channel_id' => $channel->id,
                'type' => 'income',
                'rmb_amount' => 1000,
                'hkd_amount' => 1000,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'settlement_status' => 'settled',
                'settlement_id' => $settlement->id,
                'settlement_date' => now()->toDateString(),
                'submit_time' => now(),
            ]);
            
            // 执行清理
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['settlements'],
            ], 'test_operator');
            
            // 验证结算记录被删除
            $this->assertDatabaseMissing('settlements', ['id' => $settlement->id]);
            
            // 验证结算支出记录被删除
            foreach ($expenseIds as $expenseId) {
                $this->assertDatabaseMissing('settlement_expenses', ['id' => $expenseId]);
            }
            
            // 验证关联的余额调整记录被删除
            foreach ($adjustmentIds as $adjustmentId) {
                $this->assertDatabaseMissing('balance_adjustments', ['id' => $adjustmentId]);
            }
            
            // 验证交易记录状态变为未结算
            $this->assertDatabaseHas('transactions', [
                'id' => $transaction->id,
                'settlement_status' => 'unsettled',
                'settlement_id' => null,
            ]);
        }
    }


    /**
     * Property 6: Orphaned images deletion
     * 
     * **Feature: data-cleanup-update, Property 6: Orphaned images deletion**
     * 
     * *For any* image record with both transaction_id = NULL and draft_id = NULL,
     * after cleanup with "图片" selected, that image record should not exist.
     * 
     * **Validates: Requirements 8.2, 8.3**
     * 
     * @test
     */
    public function property_orphaned_images_are_deleted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $orphanedImageIds = [];
            for ($j = 0; $j < rand(1, 5); $j++) {
                $image = Image::create([
                    'transaction_id' => null,
                    'draft_id' => null,
                    'original_name' => 'orphaned_' . $j . '.jpg',
                    'file_size' => 1000,
                    'mime_type' => 'image/jpeg',
                    'width' => 100,
                    'height' => 100,
                    'file_content' => base64_encode('test'),
                ]);
                $orphanedImageIds[] = $image->id;
            }
            
            $channel = Channel::create([
                'name' => 'Test Channel ' . $i,
                'code' => 'TST' . $i,
                'status' => 'active',
            ]);
            
            $fieldUser = FieldUser::create([
                'username' => 'test_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Test User ' . $i,
                'status' => 'active',
            ]);
            
            $transaction = Transaction::create([
                'user_id' => $fieldUser->id,
                'channel_id' => $channel->id,
                'type' => 'income',
                'rmb_amount' => 1000,
                'hkd_amount' => 1000,
                'exchange_rate' => 1.0,
                'status' => 'completed',
                'settlement_status' => 'settled',
                'submit_time' => now(),
            ]);
            
            $linkedImage = Image::create([
                'transaction_id' => $transaction->id,
                'draft_id' => null,
                'original_name' => 'linked.jpg',
                'file_size' => 1000,
                'mime_type' => 'image/jpeg',
                'width' => 100,
                'height' => 100,
                'file_content' => base64_encode('test'),
            ]);
            
            $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['images'],
            ], 'test_operator');
            
            foreach ($orphanedImageIds as $imageId) {
                $this->assertDatabaseMissing('images', ['id' => $imageId]);
            }
            
            $this->assertDatabaseHas('images', ['id' => $linkedImage->id]);
        }
    }

    /**
     * Property 7: Cleanup result accuracy
     * 
     * **Feature: data-cleanup-update, Property 7: Cleanup result accuracy**
     * 
     * *For any* cleanup operation, the returned deleted counts should match
     * the actual number of records deleted from each table.
     * 
     * **Validates: Requirements 18.4**
     * 
     * @test
     */
    public function property_cleanup_result_counts_are_accurate(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $this->refreshTestData();
            
            $channel = Channel::create([
                'name' => 'Test Channel ' . $i,
                'code' => 'TST' . $i,
                'status' => 'active',
            ]);
            
            $fieldUser = FieldUser::create([
                'username' => 'test_user_' . $i,
                'password' => bcrypt('password'),
                'name' => 'Test User ' . $i,
                'status' => 'active',
            ]);
            
            $transactionCount = rand(1, 5);
            for ($j = 0; $j < $transactionCount; $j++) {
                Transaction::create([
                    'user_id' => $fieldUser->id,
                    'channel_id' => $channel->id,
                    'type' => 'income',
                    'rmb_amount' => rand(100, 10000),
                    'hkd_amount' => rand(100, 10000),
                    'exchange_rate' => 0.95,
                    'status' => 'completed',
                    'settlement_status' => 'unsettled',
                    'submit_time' => now(),
                ]);
            }
            
            $draftCount = rand(1, 5);
            for ($j = 0; $j < $draftCount; $j++) {
                TransactionDraft::create([
                    'user_id' => $fieldUser->id,
                    'channel_id' => $channel->id,
                    'type' => 'income',
                    'rmb_amount' => rand(100, 10000),
                    'hkd_amount' => rand(100, 10000),
                    'exchange_rate' => 0.95,
                ]);
            }
            
            $orphanedImageCount = rand(1, 5);
            for ($j = 0; $j < $orphanedImageCount; $j++) {
                Image::create([
                    'transaction_id' => null,
                    'draft_id' => null,
                    'original_name' => 'orphaned_' . $j . '.jpg',
                    'file_size' => 1000,
                    'mime_type' => 'image/jpeg',
                    'width' => 100,
                    'height' => 100,
                    'file_content' => base64_encode('test'),
                ]);
            }
            
            $transactionsBefore = Transaction::where('settlement_status', 'unsettled')->count();
            $draftsBefore = TransactionDraft::count();
            $orphanedImagesBefore = Image::whereNull('transaction_id')->whereNull('draft_id')->count();
            
            $result = $this->cleanupService->cleanup([
                'time_range' => 'all',
                'content_types' => ['bills', 'drafts', 'images'],
            ], 'test_operator');
            
            $transactionsAfter = Transaction::where('settlement_status', 'unsettled')->count();
            $draftsAfter = TransactionDraft::count();
            $orphanedImagesAfter = Image::whereNull('transaction_id')->whereNull('draft_id')->count();
            
            $this->assertEquals(
                $transactionsBefore - $transactionsAfter,
                $result['bills'],
                "Transaction deletion count mismatch in iteration $i"
            );
            
            $this->assertEquals(
                $draftsBefore - $draftsAfter,
                $result['drafts'],
                "Draft deletion count mismatch in iteration $i"
            );
            
            $this->assertEquals(0, $orphanedImagesAfter, "Orphaned images should be deleted in iteration $i");
        }
    }
}

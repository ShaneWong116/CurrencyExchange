<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FieldUser;
use App\Models\CommonNote;
use App\Services\CommonNoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Property-Based Tests for CommonNoteService
 * 
 * These tests verify correctness properties of the common notes management logic
 * by running multiple iterations with randomly generated data.
 * 
 * Feature: common-notes-management
 */
class CommonNoteServicePropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected CommonNoteService $service;
    protected const ITERATIONS = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommonNoteService();
    }

    /**
     * Helper method to generate random valid content
     */
    protected function generateRandomContent(int $minLength = 1, int $maxLength = 500): string
    {
        $length = rand($minLength, $maxLength);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789测试备注内容 ';
        $content = '';
        for ($i = 0; $i < $length; $i++) {
            $content .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $content;
    }

    /**
     * Helper method to generate random user type
     */
    protected function generateRandomUserType(): string
    {
        return rand(0, 1) === 0 ? 'admin' : 'field';
    }

    /**
     * Property 1: 添加备注的持久化
     * 
     * **Feature: common-notes-management, Property 1: 添加备注的持久化**
     * 
     * *对于任何*用户和任何有效的备注内容，当用户添加常用备注后，
     * 从数据库查询该用户的备注列表应该包含刚添加的备注，
     * 且备注内容、用户ID和用户类型应该正确关联。
     * 
     * **Validates: Requirements 1.2**
     * 
     * @test
     */
    public function property_added_note_persists_in_database(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate random user
                $userType = $this->generateRandomUserType();
                if ($userType === 'admin') {
                    $user = User::factory()->create();
                } else {
                    $user = FieldUser::factory()->create();
                }
                
                // Generate random valid content (1-500 characters)
                $content = $this->generateRandomContent(1, 500);
                
                // Create note
                $note = $this->service->createCommonNote($user->id, $userType, $content);
                
                // Verify note exists in database
                $this->assertDatabaseHas('common_notes', [
                    'id' => $note->id,
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'content' => trim($content),
                ]);
                
                // Verify note appears in user's list
                $notes = $this->service->getUserCommonNotes($user->id, $userType);
                $this->assertTrue(
                    $notes->contains('id', $note->id),
                    "Iteration $i: Created note should appear in user's note list"
                );
                
                // Verify content matches
                $retrievedNote = $notes->firstWhere('id', $note->id);
                $this->assertEquals(
                    trim($content),
                    $retrievedNote->content,
                    "Iteration $i: Note content should match"
                );
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 2: 删除备注的持久化
     * 
     * **Feature: common-notes-management, Property 2: 删除备注的持久化**
     * 
     * *对于任何*用户已有的常用备注，当用户删除该备注后，
     * 从数据库查询该用户的备注列表应该不再包含该备注。
     * 
     * **Validates: Requirements 1.3**
     * 
     * @test
     */
    public function property_deleted_note_is_removed_from_database(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate random user
                $userType = $this->generateRandomUserType();
                if ($userType === 'admin') {
                    $user = User::factory()->create();
                } else {
                    $user = FieldUser::factory()->create();
                }
                
                // Create a note
                $content = $this->generateRandomContent();
                $note = $this->service->createCommonNote($user->id, $userType, $content);
                $noteId = $note->id;
                
                // Delete the note
                $result = $this->service->deleteCommonNote($user->id, $userType, $noteId);
                
                $this->assertTrue($result, "Iteration $i: Delete should return true");
                
                // Verify note no longer exists in database
                $this->assertDatabaseMissing('common_notes', [
                    'id' => $noteId,
                ]);
                
                // Verify note does not appear in user's list
                $notes = $this->service->getUserCommonNotes($user->id, $userType);
                $this->assertFalse(
                    $notes->contains('id', $noteId),
                    "Iteration $i: Deleted note should not appear in user's note list"
                );
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 3: 用户数据隔离
     * 
     * **Feature: common-notes-management, Property 3: 用户数据隔离**
     * 
     * *对于任何*两个不同的用户（无论是否同类型），
     * 用户A添加的常用备注不应该出现在用户B的备注列表中，反之亦然。
     * 
     * **Validates: Requirements 1.4**
     * 
     * @test
     */
    public function property_user_data_is_isolated(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate two random users (can be same or different types)
                $userTypeA = $this->generateRandomUserType();
                $userTypeB = $this->generateRandomUserType();
                
                if ($userTypeA === 'admin') {
                    $userA = User::factory()->create();
                } else {
                    $userA = FieldUser::factory()->create();
                }
                
                if ($userTypeB === 'admin') {
                    $userB = User::factory()->create();
                } else {
                    $userB = FieldUser::factory()->create();
                }
                
                // Create notes for both users
                $contentA = $this->generateRandomContent();
                $contentB = $this->generateRandomContent();
                
                $noteA = $this->service->createCommonNote($userA->id, $userTypeA, $contentA);
                $noteB = $this->service->createCommonNote($userB->id, $userTypeB, $contentB);
                
                // Verify user A's notes don't include user B's note
                $notesA = $this->service->getUserCommonNotes($userA->id, $userTypeA);
                $this->assertTrue(
                    $notesA->contains('id', $noteA->id),
                    "Iteration $i: User A should see their own note"
                );
                $this->assertFalse(
                    $notesA->contains('id', $noteB->id),
                    "Iteration $i: User A should not see user B's note"
                );
                
                // Verify user B's notes don't include user A's note
                $notesB = $this->service->getUserCommonNotes($userB->id, $userTypeB);
                $this->assertTrue(
                    $notesB->contains('id', $noteB->id),
                    "Iteration $i: User B should see their own note"
                );
                $this->assertFalse(
                    $notesB->contains('id', $noteA->id),
                    "Iteration $i: User B should not see user A's note"
                );
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 4: 多用户类型支持
     * 
     * **Feature: common-notes-management, Property 4: 多用户类型支持**
     * 
     * *对于任何*管理员用户（User模型）和外勤用户（FieldUser模型），
     * 两种类型的用户都应该能够成功执行添加、查询和删除常用备注操作。
     * 
     * **Validates: Requirements 1.5**
     * 
     * @test
     */
    public function property_both_user_types_can_manage_notes(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Create both types of users
                $adminUser = User::factory()->create();
                $fieldUser = FieldUser::factory()->create();
                
                // Test admin user
                $adminContent = $this->generateRandomContent();
                $adminNote = $this->service->createCommonNote($adminUser->id, 'admin', $adminContent);
                
                $this->assertNotNull($adminNote, "Iteration $i: Admin user should be able to create note");
                
                $adminNotes = $this->service->getUserCommonNotes($adminUser->id, 'admin');
                $this->assertGreaterThan(0, $adminNotes->count(), "Iteration $i: Admin user should be able to query notes");
                
                $adminDeleteResult = $this->service->deleteCommonNote($adminUser->id, 'admin', $adminNote->id);
                $this->assertTrue($adminDeleteResult, "Iteration $i: Admin user should be able to delete note");
                
                // Test field user
                $fieldContent = $this->generateRandomContent();
                $fieldNote = $this->service->createCommonNote($fieldUser->id, 'field', $fieldContent);
                
                $this->assertNotNull($fieldNote, "Iteration $i: Field user should be able to create note");
                
                $fieldNotes = $this->service->getUserCommonNotes($fieldUser->id, 'field');
                $this->assertGreaterThan(0, $fieldNotes->count(), "Iteration $i: Field user should be able to query notes");
                
                $fieldDeleteResult = $this->service->deleteCommonNote($fieldUser->id, 'field', $fieldNote->id);
                $this->assertTrue($fieldDeleteResult, "Iteration $i: Field user should be able to delete note");
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 6: 内容验证
     * 
     * **Feature: common-notes-management, Property 6: 内容验证**
     * 
     * *对于任何*创建常用备注的请求，如果备注内容为空或长度超过500字符，
     * 系统应该抛出ValidationException。
     * 
     * **Validates: Requirements 4.5, 2.8, 3.8**
     * 
     * @test
     */
    public function property_content_validation_is_enforced(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate random user
                $userType = $this->generateRandomUserType();
                if ($userType === 'admin') {
                    $user = User::factory()->create();
                } else {
                    $user = FieldUser::factory()->create();
                }
                
                // Test empty content
                $emptyContents = ['', '   ', "\t", "\n"];
                $emptyContent = $emptyContents[array_rand($emptyContents)];
                
                $exceptionThrown = false;
                try {
                    $this->service->createCommonNote($user->id, $userType, $emptyContent);
                } catch (ValidationException $e) {
                    $exceptionThrown = true;
                }
                
                $this->assertTrue(
                    $exceptionThrown,
                    "Iteration $i: Empty content should throw ValidationException"
                );
                
                // Test content exceeding 500 characters
                $longContent = $this->generateRandomContent(501, 600);
                
                $exceptionThrown = false;
                try {
                    $this->service->createCommonNote($user->id, $userType, $longContent);
                } catch (ValidationException $e) {
                    $exceptionThrown = true;
                }
                
                $this->assertTrue(
                    $exceptionThrown,
                    "Iteration $i: Content exceeding 500 characters should throw ValidationException"
                );
                
                // Test valid content (should not throw exception)
                $validContent = $this->generateRandomContent(1, 500);
                $note = $this->service->createCommonNote($user->id, $userType, $validContent);
                
                $this->assertNotNull(
                    $note,
                    "Iteration $i: Valid content should create note successfully"
                );
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 7: 权限验证
     * 
     * **Feature: common-notes-management, Property 7: 权限验证**
     * 
     * *对于任何*删除常用备注的请求，如果请求删除的备注不属于当前认证用户，
     * 系统应该抛出ModelNotFoundException。
     * 
     * **Validates: Requirements 4.6**
     * 
     * @test
     */
    public function property_permission_validation_is_enforced(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate two random users
                $userTypeA = $this->generateRandomUserType();
                $userTypeB = $this->generateRandomUserType();
                
                if ($userTypeA === 'admin') {
                    $userA = User::factory()->create();
                } else {
                    $userA = FieldUser::factory()->create();
                }
                
                if ($userTypeB === 'admin') {
                    $userB = User::factory()->create();
                } else {
                    $userB = FieldUser::factory()->create();
                }
                
                // User A creates a note
                $content = $this->generateRandomContent();
                $noteA = $this->service->createCommonNote($userA->id, $userTypeA, $content);
                
                // User B tries to delete user A's note
                $exceptionThrown = false;
                try {
                    $this->service->deleteCommonNote($userB->id, $userTypeB, $noteA->id);
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $exceptionThrown = true;
                }
                
                $this->assertTrue(
                    $exceptionThrown,
                    "Iteration $i: User B should not be able to delete user A's note"
                );
                
                // Verify note still exists
                $this->assertDatabaseHas('common_notes', [
                    'id' => $noteA->id,
                ]);
                
            } finally {
                DB::rollBack();
            }
        }
    }

    /**
     * Property 15: 时间倒序排列
     * 
     * **Feature: common-notes-management, Property 15: 时间倒序排列**
     * 
     * *对于任何*用户的常用备注列表，备注应该按创建时间倒序排列，
     * 最新创建的备注显示在最前面。
     * 
     * **Validates: Requirements 5.7**
     * 
     * @test
     */
    public function property_notes_are_sorted_by_creation_time_descending(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            DB::beginTransaction();
            
            try {
                // Generate random user
                $userType = $this->generateRandomUserType();
                if ($userType === 'admin') {
                    $user = User::factory()->create();
                } else {
                    $user = FieldUser::factory()->create();
                }
                
                // Create multiple notes with different timestamps
                $noteCount = rand(3, 10);
                $createdNotes = [];
                
                for ($j = 0; $j < $noteCount; $j++) {
                    $content = $this->generateRandomContent();
                    $note = CommonNote::create([
                        'user_id' => $user->id,
                        'user_type' => $userType,
                        'content' => $content,
                        'created_at' => now()->subMinutes($noteCount - $j),
                    ]);
                    $createdNotes[] = $note;
                }
                
                // Get notes from service
                $notes = $this->service->getUserCommonNotes($user->id, $userType);
                
                // Verify notes are in descending order by created_at
                $previousTimestamp = null;
                foreach ($notes as $note) {
                    if ($previousTimestamp !== null) {
                        $this->assertLessThanOrEqual(
                            $previousTimestamp,
                            $note->created_at->timestamp,
                            "Iteration $i: Notes should be sorted in descending order by creation time"
                        );
                    }
                    $previousTimestamp = $note->created_at->timestamp;
                }
                
                // Verify the first note is the most recently created
                $this->assertEquals(
                    $createdNotes[$noteCount - 1]->id,
                    $notes[0]->id,
                    "Iteration $i: Most recent note should be first"
                );
                
            } finally {
                DB::rollBack();
            }
        }
    }
}

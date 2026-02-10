<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FieldUser;
use App\Models\CommonNote;
use App\Services\CommonNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class CommonNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CommonNoteService $service;
    protected User $adminUser;
    protected FieldUser $fieldUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new CommonNoteService();
        
        // 创建测试用户
        $this->adminUser = User::factory()->create();
        $this->fieldUser = FieldUser::factory()->create();
    }

    /** @test */
    public function 可以创建常用备注()
    {
        $note = $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            '月度结算'
        );

        $this->assertInstanceOf(CommonNote::class, $note);
        $this->assertEquals($this->adminUser->id, $note->user_id);
        $this->assertEquals('admin', $note->user_type);
        $this->assertEquals('月度结算', $note->content);
        
        $this->assertDatabaseHas('common_notes', [
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '月度结算',
        ]);
    }

    /** @test */
    public function 创建备注时会去除首尾空白字符()
    {
        $note = $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            '  月度结算  '
        );

        $this->assertEquals('月度结算', $note->content);
    }

    /** @test */
    public function 不能创建空内容的备注()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            ''
        );
    }

    /** @test */
    public function 不能创建只有空白字符的备注()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            '   '
        );
    }

    /** @test */
    public function 不能创建超过500字符的备注()
    {
        $this->expectException(ValidationException::class);
        
        $longContent = str_repeat('测', 501);
        
        $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            $longContent
        );
    }

    /** @test */
    public function 可以创建正好500字符的备注()
    {
        $content = str_repeat('测', 500);
        
        $note = $this->service->createCommonNote(
            $this->adminUser->id,
            'admin',
            $content
        );

        $this->assertEquals(500, mb_strlen($note->content));
    }

    /** @test */
    public function 可以获取用户的常用备注列表()
    {
        // 创建多个备注
        CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '备注1',
        ]);
        
        CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '备注2',
        ]);

        $notes = $this->service->getUserCommonNotes($this->adminUser->id, 'admin');

        $this->assertCount(2, $notes);
    }

    /** @test */
    public function 获取备注列表按创建时间倒序排列()
    {
        // 创建多个备注（使用不同的时间）
        $note1 = CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '第一条备注',
            'created_at' => now()->subHours(2),
        ]);
        
        $note2 = CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '第二条备注',
            'created_at' => now()->subHours(1),
        ]);
        
        $note3 = CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '第三条备注',
            'created_at' => now(),
        ]);

        $notes = $this->service->getUserCommonNotes($this->adminUser->id, 'admin');

        // 最新的应该在最前面
        $this->assertEquals('第三条备注', $notes[0]->content);
        $this->assertEquals('第二条备注', $notes[1]->content);
        $this->assertEquals('第一条备注', $notes[2]->content);
    }

    /** @test */
    public function 用户只能获取自己的备注()
    {
        // 创建用户1的备注
        CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '用户1的备注',
        ]);
        
        // 创建用户2的备注
        $anotherUser = User::factory()->create();
        CommonNote::create([
            'user_id' => $anotherUser->id,
            'user_type' => 'admin',
            'content' => '用户2的备注',
        ]);

        $notes = $this->service->getUserCommonNotes($this->adminUser->id, 'admin');

        $this->assertCount(1, $notes);
        $this->assertEquals('用户1的备注', $notes[0]->content);
    }

    /** @test */
    public function 管理员用户和外勤用户的备注相互隔离()
    {
        // 创建管理员备注
        CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '管理员备注',
        ]);
        
        // 创建外勤用户备注
        CommonNote::create([
            'user_id' => $this->fieldUser->id,
            'user_type' => 'field',
            'content' => '外勤备注',
        ]);

        $adminNotes = $this->service->getUserCommonNotes($this->adminUser->id, 'admin');
        $fieldNotes = $this->service->getUserCommonNotes($this->fieldUser->id, 'field');

        $this->assertCount(1, $adminNotes);
        $this->assertCount(1, $fieldNotes);
        $this->assertEquals('管理员备注', $adminNotes[0]->content);
        $this->assertEquals('外勤备注', $fieldNotes[0]->content);
    }

    /** @test */
    public function 可以删除自己的备注()
    {
        $note = CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '待删除的备注',
        ]);

        $result = $this->service->deleteCommonNote(
            $this->adminUser->id,
            'admin',
            $note->id
        );

        $this->assertTrue($result);
        $this->assertDatabaseMissing('common_notes', [
            'id' => $note->id,
        ]);
    }

    /** @test */
    public function 不能删除其他用户的备注()
    {
        $anotherUser = User::factory()->create();
        
        $note = CommonNote::create([
            'user_id' => $anotherUser->id,
            'user_type' => 'admin',
            'content' => '其他用户的备注',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->deleteCommonNote(
            $this->adminUser->id,
            'admin',
            $note->id
        );
    }

    /** @test */
    public function 不能删除不存在的备注()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->deleteCommonNote(
            $this->adminUser->id,
            'admin',
            99999
        );
    }

    /** @test */
    public function 管理员不能删除外勤用户的备注()
    {
        $note = CommonNote::create([
            'user_id' => $this->fieldUser->id,
            'user_type' => 'field',
            'content' => '外勤用户的备注',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->deleteCommonNote(
            $this->adminUser->id,
            'admin',
            $note->id
        );
    }

    /** @test */
    public function 获取空列表时返回空集合()
    {
        $notes = $this->service->getUserCommonNotes($this->adminUser->id, 'admin');

        $this->assertCount(0, $notes);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $notes);
    }

    /** @test */
    public function 外勤用户可以创建和管理备注()
    {
        // 创建备注
        $note = $this->service->createCommonNote(
            $this->fieldUser->id,
            'field',
            '外勤备注'
        );

        $this->assertEquals($this->fieldUser->id, $note->user_id);
        $this->assertEquals('field', $note->user_type);

        // 获取备注
        $notes = $this->service->getUserCommonNotes($this->fieldUser->id, 'field');
        $this->assertCount(1, $notes);

        // 删除备注
        $result = $this->service->deleteCommonNote(
            $this->fieldUser->id,
            'field',
            $note->id
        );
        $this->assertTrue($result);
    }
}

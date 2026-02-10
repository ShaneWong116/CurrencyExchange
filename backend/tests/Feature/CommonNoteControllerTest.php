<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FieldUser;
use App\Models\CommonNote;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommonNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected FieldUser $fieldUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建测试用户
        $this->adminUser = User::factory()->create();
        $this->fieldUser = FieldUser::factory()->create();
    }

    /** @test */
    public function 未认证用户不能访问常用备注接口()
    {
        // 测试 GET /api/common-notes
        $response = $this->getJson('/api/common-notes');
        $response->assertStatus(401);

        // 测试 POST /api/common-notes
        $response = $this->postJson('/api/common-notes', ['content' => '测试备注']);
        $response->assertStatus(401);

        // 测试 DELETE /api/common-notes/{id}
        $response = $this->deleteJson('/api/common-notes/1');
        $response->assertStatus(401);
    }

    /** @test */
    public function 可以获取当前用户的常用备注列表()
    {
        // 创建当前用户的备注
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

        // 创建其他用户的备注（不应该返回）
        $anotherUser = User::factory()->create();
        CommonNote::create([
            'user_id' => $anotherUser->id,
            'user_type' => 'admin',
            'content' => '其他用户的备注',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'content', 'created_at', 'updated_at']
                ]
            ]);
    }

    /** @test */
    public function 获取空列表时返回空数组()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    /** @test */
    public function 可以创建新的常用备注()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => '月度结算',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '常用备注添加成功',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'content', 'created_at', 'updated_at']
            ]);

        $this->assertDatabaseHas('common_notes', [
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '月度结算',
        ]);
    }

    /** @test */
    public function 创建备注时内容不能为空()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => '',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['content']
            ]);

        $this->assertDatabaseCount('common_notes', 0);
    }

    /** @test */
    public function 创建备注时内容不能超过500字符()
    {
        $longContent = str_repeat('测', 501);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => $longContent,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);

        $this->assertDatabaseCount('common_notes', 0);
    }

    /** @test */
    public function 创建备注时缺少content字段返回验证错误()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function 可以删除自己的常用备注()
    {
        $note = CommonNote::create([
            'user_id' => $this->adminUser->id,
            'user_type' => 'admin',
            'content' => '待删除的备注',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/common-notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '常用备注删除成功',
            ]);

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

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/common-notes/{$note->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '常用备注不存在或无权操作此资源',
            ]);

        // 备注应该仍然存在
        $this->assertDatabaseHas('common_notes', [
            'id' => $note->id,
        ]);
    }

    /** @test */
    public function 删除不存在的备注返回404()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/common-notes/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function 管理员不能删除外勤用户的备注()
    {
        $note = CommonNote::create([
            'user_id' => $this->fieldUser->id,
            'user_type' => 'field',
            'content' => '外勤用户的备注',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/common-notes/{$note->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);

        // 备注应该仍然存在
        $this->assertDatabaseHas('common_notes', [
            'id' => $note->id,
        ]);
    }

    /** @test */
    public function 外勤用户可以管理自己的备注()
    {
        // 创建备注
        $response = $this->actingAs($this->fieldUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => '外勤备注',
            ]);

        $response->assertStatus(201);
        
        $noteId = $response->json('data.id');

        $this->assertDatabaseHas('common_notes', [
            'id' => $noteId,
            'user_id' => $this->fieldUser->id,
            'user_type' => 'field',
            'content' => '外勤备注',
        ]);

        // 获取备注列表
        $response = $this->actingAs($this->fieldUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 删除备注
        $response = $this->actingAs($this->fieldUser, 'sanctum')
            ->deleteJson("/api/common-notes/{$noteId}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('common_notes', [
            'id' => $noteId,
        ]);
    }

    /** @test */
    public function 管理员和外勤用户的备注相互隔离()
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

        // 管理员只能看到自己的备注
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['content' => '管理员备注']);

        // 外勤用户只能看到自己的备注
        $response = $this->actingAs($this->fieldUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['content' => '外勤备注']);
    }

    /** @test */
    public function 备注列表按创建时间倒序返回()
    {
        // 创建多个备注
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

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/common-notes');

        $response->assertStatus(200);

        $data = $response->json('data');
        
        // 最新的应该在最前面
        $this->assertEquals('第三条备注', $data[0]['content']);
        $this->assertEquals('第二条备注', $data[1]['content']);
        $this->assertEquals('第一条备注', $data[2]['content']);
    }

    /** @test */
    public function 创建备注时会去除首尾空白字符()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => '  月度结算  ',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('common_notes', [
            'user_id' => $this->adminUser->id,
            'content' => '月度结算',
        ]);
    }

    /** @test */
    public function 可以创建正好500字符的备注()
    {
        $content = str_repeat('测', 500);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/common-notes', [
                'content' => $content,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('common_notes', [
            'user_id' => $this->adminUser->id,
            'content' => $content,
        ]);
    }
}

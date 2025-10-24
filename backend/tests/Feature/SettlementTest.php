<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\Setting;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $channel;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建测试用户
        $this->user = User::factory()->create();
        
        // 创建测试渠道
        $this->channel = Channel::create([
            'name' => '测试渠道',
            'code' => 'TEST',
            'type' => 'alipay',
            'is_active' => true,
        ]);
        
        // 初始化系统设置
        Setting::set('capital', 1000000, '系统本金(HKD)', 'number');
        Setting::set('hkd_balance', 526315, '港币结余(HKD)', 'number');
    }

    /** @test */
    public function 可以获取结余预览数据()
    {
        // 创建未结余的交易
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 9500,
            'hkd_amount' => 10000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/settlements/preview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_capital',
                    'current_hkd_balance',
                    'rmb_balance_total',
                    'settlement_rate',
                    'profit',
                    'can_settle',
                ]
            ]);
    }

    /** @test */
    public function 可以执行结余操作()
    {
        // 创建入账交易
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 9500,
            'hkd_amount' => 10000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        // 创建出账交易
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'outcome',
            'rmb_amount' => 4750,
            'hkd_amount' => 5000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/settlements', [
                'expenses' => [
                    ['item_name' => '薪金', 'amount' => 100],
                    ['item_name' => '金流费用', 'amount' => 200],
                ],
                'notes' => '测试结余',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'settlement',
                    'expenses',
                    'transactions_count',
                ]
            ]);

        // 验证结余记录已创建
        $this->assertDatabaseHas('settlements', [
            'sequence_number' => 1,
            'other_expenses_total' => 300,
            'notes' => '测试结余',
        ]);

        // 验证支出明细已创建
        $this->assertDatabaseHas('settlement_expenses', [
            'item_name' => '薪金',
            'amount' => 100,
        ]);

        // 验证交易状态已更新
        $this->assertEquals(2, Transaction::where('settlement_status', 'settled')->count());
    }

    /** @test */
    public function 没有未结余交易时不能执行结余()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/settlements', [
                'expenses' => [],
                'notes' => '测试',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function 可以查看结余详情()
    {
        // 创建交易并执行结余
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 9500,
            'hkd_amount' => 10000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        $settlementService = app(SettlementService::class);
        $settlement = $settlementService->execute([
            ['item_name' => '测试支出', 'amount' => 100]
        ], '测试备注');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/settlements/{$settlement->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'settlement',
                    'expenses',
                    'transactions_count',
                ]
            ]);
    }

    /** @test */
    public function 可以获取结余历史列表()
    {
        // 创建交易并执行结余
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 9500,
            'hkd_amount' => 10000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        $settlementService = app(SettlementService::class);
        $settlementService->execute([], '第一次结余');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/settlements?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ]
            ]);
    }

    /** @test */
    public function 结余汇率计算正确()
    {
        $settlementService = app(SettlementService::class);
        
        // 设置初始人民币余额为500000
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 500000,
            'hkd_amount' => 500000,
            'exchange_rate' => 1.0,
            'settlement_status' => 'settled',
            'status' => 'completed',
        ]);
        
        // 创建新的入账交易
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 9500,
            'hkd_amount' => 10000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'income',
            'rmb_amount' => 19000,
            'hkd_amount' => 20000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        // 创建出账交易
        Transaction::create([
            'channel_id' => $this->channel->id,
            'type' => 'outcome',
            'rmb_amount' => 14250,
            'hkd_amount' => 15000,
            'exchange_rate' => 0.950,
            'settlement_status' => 'unsettled',
            'status' => 'completed',
        ]);

        $preview = $settlementService->getPreview();

        // 人民币总量 = 514250（渠道余额）+ 28500（未结余入账）= 542750
        // 港币总量 = 526315 + 30000 = 556315
        // 结余汇率 = 542750 / 556315 ≈ 0.976
        $this->assertEquals(0.976, round($preview['settlement_rate'], 3));
    }
}


<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionDraft;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DraftController extends Controller
{
    public function index(Request $request)
    {
        $drafts = TransactionDraft::with(['channel', 'user'])
            ->where('user_id', $request->user()->id)
            ->orderBy('last_modified', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($drafts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['income', 'outcome', 'exchange', 'instant-buyout'])],
            'rmb_amount' => 'nullable|numeric|min:0',
            'hkd_amount' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'instant_rate' => 'nullable|numeric|min:0',
            'channel_id' => 'nullable|exists:channels,id',
            'location_id' => 'nullable|exists:locations,id',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
            'uuid' => 'nullable|string',
        ]);

        $normalizedType = $request->type === 'instant-buyout' ? 'exchange' : $request->type;

        $draft = TransactionDraft::create([
            'uuid' => $request->uuid ?: \Illuminate\Support\Str::uuid(),
            'user_id' => $request->user()->id,
            'type' => $normalizedType,
            'rmb_amount' => $request->rmb_amount,
            'hkd_amount' => $request->hkd_amount,
            'exchange_rate' => $request->exchange_rate,
            'instant_rate' => $request->instant_rate,
            'channel_id' => $request->channel_id,
            'location_id' => $request->location_id,
            'location' => $request->location,
            'notes' => $request->notes,
            'last_modified' => now(),
        ]);

        return response()->json([
            'message' => '草稿保存成功',
            'draft' => $draft->load('channel')
        ], 201);
    }

    public function show(TransactionDraft $draft, Request $request)
    {
        // 确保用户只能查看自己的草稿
        if ($draft->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $draft->load(['channel', 'images']);
        
        return response()->json($draft);
    }

    public function update(TransactionDraft $draft, Request $request)
    {
        // 确保用户只能更新自己的草稿
        if ($draft->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $request->validate([
            'type' => ['sometimes', Rule::in(['income', 'outcome', 'exchange', 'instant-buyout'])],
            'rmb_amount' => 'nullable|numeric|min:0',
            'hkd_amount' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'instant_rate' => 'nullable|numeric|min:0',
            'channel_id' => 'nullable|exists:channels,id',
            'location_id' => 'nullable|exists:locations,id',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        $updateData = $request->only([
            'type', 'rmb_amount', 'hkd_amount', 'exchange_rate',
            'instant_rate', 'channel_id', 'location_id', 'location', 'notes'
        ]);
        if (array_key_exists('type', $updateData) && $updateData['type'] === 'instant-buyout') {
            $updateData['type'] = 'exchange';
        }

        $draft->update([
            ...$updateData,
            'last_modified' => now(),
        ]);

        return response()->json([
            'message' => '草稿更新成功',
            'draft' => $draft->load('channel')
        ]);
    }

    public function destroy(TransactionDraft $draft, Request $request)
    {
        // 确保用户只能删除自己的草稿
        if ($draft->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $draft->delete();

        return response()->json([
            'message' => '草稿删除成功'
        ]);
    }

    public function submit(TransactionDraft $draft, Request $request)
    {
        // 确保用户只能提交自己的草稿
        if ($draft->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权访问'], 403);
        }

        // 验证草稿数据完整性
        if (!$draft->rmb_amount || !$draft->exchange_rate || !$draft->channel_id) {
            return response()->json([
                'message' => '草稿数据不完整，无法提交'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = $request->user();

            // 即时买断：生成两条交易（入账 + 出账），并标记类型标签
            if ($draft->type === 'exchange' && $draft->instant_rate) {
                // 入账记录（按 exchange_rate 与录入金额）
                $income = Transaction::create([
                    'uuid' => $draft->uuid,
                    'user_id' => $draft->user_id,
                    'type' => 'income',
                    'transaction_label' => '即时买断',
                    'rmb_amount' => $draft->rmb_amount,
                    'hkd_amount' => $draft->hkd_amount,
                    'exchange_rate' => $draft->exchange_rate,
                    'instant_rate' => null,
                    'channel_id' => $draft->channel_id,
                    'location_id' => $draft->location_id ?: $user->location_id,
                    'location' => $draft->location,
                    'notes' => $draft->notes,
                ]);

                // 出账记录（按 instant_rate 计算港币：港币 = 人民币 / 即时汇率）
                $calculatedHkd = $draft->instant_rate > 0
                    ? round((float)$draft->rmb_amount / (float)$draft->instant_rate, 4)
                    : null;

                $outcome = Transaction::create([
                    // 单独的 uuid，避免与入账冲突
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'user_id' => $draft->user_id,
                    'type' => 'outcome',
                    'transaction_label' => '即时买断',
                    'rmb_amount' => $draft->rmb_amount,
                    'hkd_amount' => $calculatedHkd,
                    'exchange_rate' => $draft->instant_rate,
                    'instant_rate' => null,
                    'channel_id' => $draft->channel_id,
                    'location_id' => $draft->location_id ?: $user->location_id,
                    'location' => $draft->location,
                    'notes' => $draft->notes,
                ]);

                // 将图片归属迁移到入账交易
                $draft->images()->update(['transaction_id' => $income->id, 'draft_id' => null]);

                // 更新渠道交易计数（两条）
                $income->channel->incrementTransactionCount();
                $outcome->channel->incrementTransactionCount();

                // 删除草稿
                $draft->delete();

                DB::commit();

                return response()->json([
                    'message' => '草稿提交成功',
                    'transactions' => [
                        'income' => $income->load(['channel', 'user']),
                        'outcome' => $outcome->load(['channel', 'user'])
                    ]
                ]);
            }

            // 普通流程：单条转换
            $transaction = $draft->convertToTransaction();

            // 转移图片关联
            $draft->images()->update(['transaction_id' => $transaction->id, 'draft_id' => null]);

            // 更新渠道交易计数
            $transaction->channel->incrementTransactionCount();

            // 删除草稿
            $draft->delete();

            DB::commit();

            return response()->json([
                'message' => '草稿提交成功',
                'transaction' => $transaction->load(['channel', 'user'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '提交失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function batchSync(Request $request)
    {
        $request->validate([
            'drafts' => 'required|array',
            'drafts.*.uuid' => 'required|string',
            'drafts.*.type' => ['required', Rule::in(['income', 'outcome', 'exchange', 'instant-buyout'])],
            'drafts.*.rmb_amount' => 'nullable|numeric|min:0',
            'drafts.*.hkd_amount' => 'nullable|numeric|min:0',
            'drafts.*.exchange_rate' => 'nullable|numeric|min:0',
            'drafts.*.instant_rate' => 'nullable|numeric|min:0',
            'drafts.*.channel_id' => 'nullable|exists:channels,id',
            'drafts.*.location_id' => 'nullable|exists:locations,id',
            'drafts.*.location' => 'nullable|string|max:200',
            'drafts.*.notes' => 'nullable|string',
            'drafts.*.last_modified' => 'required|date',
        ]);

        $results = [];
        $userId = $request->user()->id;

        foreach ($request->drafts as $draftData) {
            // 统一类型
            if (($draftData['type'] ?? null) === 'instant-buyout') {
                $draftData['type'] = 'exchange';
            }

            $existingDraft = TransactionDraft::where('uuid', $draftData['uuid'])->first();

            if ($existingDraft) {
                // 比较时间戳，保留最新版本
                $existingTime = strtotime($existingDraft->last_modified);
                $newTime = strtotime($draftData['last_modified']);

                if ($newTime > $existingTime) {
                    $existingDraft->update([
                        ...$draftData,
                        'user_id' => $userId,
                        'last_modified' => $draftData['last_modified'],
                    ]);
                    $results[] = ['uuid' => $draftData['uuid'], 'status' => 'updated'];
                } else {
                    $results[] = ['uuid' => $draftData['uuid'], 'status' => 'skipped'];
                }
            } else {
                TransactionDraft::create([
                    ...$draftData,
                    'user_id' => $userId,
                ]);
                $results[] = ['uuid' => $draftData['uuid'], 'status' => 'created'];
            }
        }

        return response()->json([
            'message' => '草稿同步完成',
            'results' => $results
        ]);
    }
}

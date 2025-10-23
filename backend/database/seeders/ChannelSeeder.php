<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Channel;

class ChannelSeeder extends Seeder
{
    public function run()
    {
        $channels = [
            ['name' => '中国银行', 'code' => 'BOC', 'label' => '银行', 'category' => 'bank'],
            ['name' => '工商银行', 'code' => 'ICBC', 'label' => '银行', 'category' => 'bank'],
            ['name' => '建设银行', 'code' => 'CCB', 'label' => '银行', 'category' => 'bank'],
            ['name' => '农业银行', 'code' => 'ABC', 'label' => '银行', 'category' => 'bank'],
            ['name' => '招商银行', 'code' => 'CMB', 'label' => '银行', 'category' => 'bank'],
            ['name' => '支付宝', 'code' => 'ALIPAY', 'label' => '第三方', 'category' => 'ewallet'],
            ['name' => '微信支付', 'code' => 'WECHAT', 'label' => '第三方', 'category' => 'ewallet'],
            ['name' => '现金', 'code' => 'CASH', 'label' => '线下', 'category' => 'cash'],
        ];

        // 用户提供的自定义渠道名称（默认分类为 other，状态为 active）
        $customNames = [
            '明(工行)',
            'D仔(支)',
            'C仔(V)',
            'A仔(支)',
            'TT(V)',
            'TT(支)',
            'L仔(V+支)',
            'U2(V)',
            'U2(支)',
            '公(V)-啟',
            '公(支)-啟',
            '中銀',
            'He(V)',
            'He(支)',
            'Lo(V)',
            'Lo(支)',
            'Lo2(V)',
            'Lo3(V)',
            'Lo3(支)',
            'O(v)',
            'O(支)',
            'O2(V)',
            'O2(支)',
            'A1(V)',
            'M(V)',
            'D4(支)',
            'Y(V)'
        ];

        foreach ($customNames as $name) {
            $channels[] = [
                'name' => $name,
                // 稳定唯一的 code，避免非 ASCII 名称转码问题
                'code' => 'CUST_' . strtoupper(substr(md5($name), 0, 8)),
                'label' => null,
                'category' => 'other',
                'status' => 'active',
            ];
        }

        foreach ($channels as $channel) {
            Channel::updateOrCreate(
                ['code' => $channel['code']],
                $channel
            );
        }
    }
}

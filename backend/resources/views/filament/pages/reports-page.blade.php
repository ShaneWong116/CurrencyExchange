<x-filament::page>
    {{-- 标签页切换 --}}
    <div class="flex space-x-4 mb-6 border-b border-gray-200 dark:border-gray-700">
        <button 
            wire:click="$set('activeTab', 'monthly')"
            class="px-4 py-2 -mb-px {{ $activeTab === 'monthly' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
            月度报表
        </button>
        <button 
            wire:click="$set('activeTab', 'monthly-detail')"
            class="px-4 py-2 -mb-px {{ $activeTab === 'monthly-detail' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
            月度收支明细表
        </button>
        <button 
            wire:click="$set('activeTab', 'daily')"
            class="px-4 py-2 -mb-px {{ $activeTab === 'daily' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
            日报表
        </button>
        <button 
            wire:click="$set('activeTab', 'yearly')"
            class="px-4 py-2 -mb-px {{ $activeTab === 'yearly' ? 'border-b-2 border-primary-600 text-primary-600 font-medium' : 'text-gray-500 hover:text-gray-700' }}">
            年度报表
        </button>
    </div>

    {{-- 月度报表 --}}
    @if ($activeTab === 'monthly')
        <x-filament::section heading="月度结余报表">
            <x-slot name="headerEnd">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    共 {{ $monthlyData ? $monthlyData['days_in_month'] : 0 }} 天
                </div>
            </x-slot>
            {{-- 月份切换器 --}}
            <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <button 
                    wire:click="previousMonth"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span>上个月</span>
                </button>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $monthlyData ? $monthlyData['year'] . '年 ' . $monthlyData['month'] . '月' : (data_get($this->monthly, 'year', now()->year) . '年 ' . data_get($this->monthly, 'month', now()->month) . '月') }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        点击左右按钮切换月份
                    </div>
                </div>
                
                <button 
                    wire:click="nextMonth"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    <span>下个月</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
            
            @if ($monthlyData)
                {{-- 汇总统计卡片 --}}
                <div class="flex gap-4 mb-6">
                    {{-- 总利润卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总利润</span>
                            <svg class="w-5 h-5" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #16a34a;">
                            ¥{{ number_format($monthlyData['summary']['total_profit'], 2) }}
                        </div>
                    </div>

                    {{-- 总收入卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总收入</span>
                            <svg class="w-5 h-5" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </div>
                        @if (count($monthlyData['summary']['income_breakdown'] ?? []) > 0)
                            <details class="cursor-pointer group">
                                <summary class="text-3xl font-bold tabular-nums list-none" style="color: #2563eb;">
                                    ¥{{ number_format($monthlyData['summary']['total_income'], 2) }}
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 opacity-60 group-hover:opacity-100">▼ 点击查看明细</span>
                                </summary>
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-1">
                                    @foreach ($monthlyData['summary']['income_breakdown'] as $name => $amount)
                                        <div class="flex justify-between text-xs text-gray-700 dark:text-gray-300">
                                            <span>{{ $name }}</span>
                                            <span class="font-medium tabular-nums">¥{{ number_format($amount, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @else
                            <div class="text-3xl font-bold tabular-nums" style="color: #2563eb;">
                                ¥{{ number_format($monthlyData['summary']['total_income'], 2) }}
                            </div>
                        @endif
                    </div>

                    {{-- 总支出卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总支出</span>
                            <svg class="w-5 h-5" style="color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                        @if (count($monthlyData['summary']['expense_breakdown']) > 0)
                            <details class="cursor-pointer group">
                                <summary class="text-3xl font-bold tabular-nums list-none" style="color: #dc2626;">
                                    ¥{{ number_format($monthlyData['summary']['total_expenses'], 2) }}
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 opacity-60 group-hover:opacity-100">▼ 点击查看明细</span>
                                </summary>
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-1">
                                    @foreach ($monthlyData['summary']['expense_breakdown'] as $name => $amount)
                                        <div class="flex justify-between text-xs text-gray-700 dark:text-gray-300">
                                            <span>{{ $name }}</span>
                                            <span class="font-medium tabular-nums">¥{{ number_format($amount, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @else
                            <div class="text-3xl font-bold tabular-nums" style="color: #dc2626;">
                                ¥{{ number_format($monthlyData['summary']['total_expenses'], 2) }}
                            </div>
                        @endif
                    </div>

                    {{-- 净利润卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">净利润</span>
                            <svg class="w-5 h-5" style="color: {{ $monthlyData['summary']['net_profit'] >= 0 ? '#16a34a' : '#dc2626' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: {{ $monthlyData['summary']['net_profit'] >= 0 ? '#16a34a' : '#dc2626' }};">
                            {{ $monthlyData['summary']['net_profit'] >= 0 ? '+' : '' }}¥{{ number_format($monthlyData['summary']['net_profit'], 2) }}
                        </div>
                    </div>
                </div>

                {{-- 导出按钮 --}}
                <div class="mt-6 mb-4 flex justify-end">
                    <button 
                        wire:click="exportMonthly"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        导出Excel
                    </button>
                </div>

                {{-- 明细表 --}}
                <div class="-mx-6">
                    <div class="px-6 mb-3">
                        <h3 class="text-lg font-semibold">每日明细</h3>
                    </div>
                    <div style="border-top: 1px solid #e5e7eb; overflow: auto; max-height: 600px; position: relative;">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead style="position: sticky; top: 0; z-index: 20; background-color: #f3f4f6;">
                                <tr>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">日期</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">本金</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">利润</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">收入</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">支出</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">结余本金</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">人民币结余</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; white-space: nowrap; border-bottom: 2px solid #d1d5db;">港币结余</th>
                                    <th style="position: sticky; top: 0; background-color: #f3f4f6; padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #374151; text-transform: uppercase; border-bottom: 2px solid #d1d5db;">备注</th>
                                </tr>
                            </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800" x-data="{ expandedRows: {} }">
                                @foreach ($monthlyData['daily_data'] as $loop_index => $day)
                                    @if ($day['has_settlement'])
                                        {{-- 已结算行 - 可点击展开 --}}
                                        <tr class="hover:bg-blue-50 dark:hover:bg-gray-750 transition-colors {{ $loop_index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-850' }}">
                                                <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer whitespace-nowrap" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    <div class="flex items-center gap-1.5">
                                                        <span x-show="!expandedRows[{{ $loop_index }}]" class="text-gray-400 text-xs">▶</span>
                                                        <span x-show="expandedRows[{{ $loop_index }}]" class="text-gray-400 text-xs">▼</span>
                                                        <span>{{ $day['date'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 cursor-pointer text-right tabular-nums" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    {{ number_format($day['previous_capital'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm font-medium cursor-pointer text-right tabular-nums" style="color: #16a34a;" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    +{{ number_format($day['profit'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm font-medium cursor-pointer text-right tabular-nums" style="color: #2563eb;" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    {{ ($day['income'] ?? 0) > 0 ? '+' : '' }}{{ number_format($day['income'] ?? 0, 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm font-medium cursor-pointer text-right tabular-nums" style="color: #dc2626;" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    -{{ number_format($day['expenses'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer text-right tabular-nums" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    {{ number_format($day['new_capital'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 cursor-pointer text-right tabular-nums" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    {{ number_format($day['rmb_balance'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 cursor-pointer text-right tabular-nums" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    {{ number_format($day['hkd_balance'], 0) }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400 cursor-pointer" @click="expandedRows[{{ $loop_index }}] = !expandedRows[{{ $loop_index }}]">
                                                    <div class="max-w-xs truncate">{{ $day['notes'] ?? '-' }}</div>
                                                </td>
                                            </tr>
                                            {{-- 展开的明细内容行 --}}
                                            <tr x-show="expandedRows[{{ $loop_index }}]" 
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 transform scale-95"
                                                x-transition:enter-end="opacity-100 transform scale-100"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 transform scale-100"
                                                x-transition:leave-end="opacity-0 transform scale-95"
                                                style="display: none;"
                                                class="{{ $loop_index % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-850' }}">
                                            <td colspan="9" class="px-4 py-3 bg-blue-50 dark:bg-gray-800 border-t border-blue-100 dark:border-gray-700">
                                                <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                                                    <div class="flex justify-between">
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">出账利润：</span>
                                                        <span class="font-medium tabular-nums" style="color: #16a34a;">¥{{ number_format($day['outgoing_profit'], 2) }}</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">即时买断利润：</span>
                                                        <span class="font-medium tabular-nums" style="color: #16a34a;">¥{{ number_format($day['instant_profit'], 2) }}</span>
                                                    </div>
                                                    @if (count($day['income_items'] ?? []) > 0)
                                                        <div class="col-span-2 mt-2 pt-2 border-t border-blue-200 dark:border-gray-700">
                                                            <span class="font-medium text-gray-700 dark:text-gray-300">收入明细：</span>
                                                            <div class="mt-1 space-y-1 ml-4">
                                                                @foreach ($day['income_items'] as $item)
                                                                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                                                        <span>• {{ $item['name'] }}</span>
                                                                        <span class="font-medium tabular-nums" style="color: #2563eb;">¥{{ number_format($item['amount'], 2) }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                    @if (count($day['expense_items']) > 0)
                                                        <div class="col-span-2 mt-2 pt-2 border-t border-blue-200 dark:border-gray-700">
                                                            <span class="font-medium text-gray-700 dark:text-gray-300">支出明细：</span>
                                                            <div class="mt-1 space-y-1 ml-4">
                                                                @foreach ($day['expense_items'] as $item)
                                                                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                                                        <span>• {{ $item['name'] }}</span>
                                                                        <span class="font-medium tabular-nums">¥{{ number_format($item['amount'], 2) }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                    @if ($day['notes'])
                                                        <div class="col-span-2 mt-2 pt-2 border-t border-blue-200 dark:border-gray-700">
                                                            <span class="font-medium text-gray-700 dark:text-gray-300">完整备注：</span>
                                                            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $day['notes'] }}</p>
                                                        </div>
                                                    @endif
                                                    
                                                    {{-- 查看详细交易按钮 --}}
                                                    <div class="col-span-2 mt-3 pt-3 border-t border-blue-200 dark:border-gray-700">
                                                        <button 
                                                            wire:click="$set('activeTab', 'daily')" 
                                                            @click="$wire.set('daily.date', '{{ $day['settlement_date'] }}')"
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                                                            style="color: #2563eb; background-color: #dbeafe;"
                                                            onmouseover="this.style.backgroundColor='#bfdbfe'"
                                                            onmouseout="this.style.backgroundColor='#dbeafe'">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            查看当天详细交易记录
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        {{-- 未结算行 - 灰色显示 --}}
                                        <tr class="{{ $loop_index % 2 === 0 ? 'bg-gray-100 dark:bg-gray-800/30' : 'bg-gray-50 dark:bg-gray-800/50' }}">
                                            <td class="px-4 py-2.5 text-sm font-medium text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $day['date'] }}</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500 text-right">-</td>
                                            <td class="px-4 py-2.5 text-sm text-gray-400 dark:text-gray-500">-</td>
                                        </tr>
                                    @endif
                                @endforeach
                                </tbody>
                            </table>
                    </div>
                </div>
        @endif
    </x-filament::section>
    @endif

    {{-- 月度收支明细表 --}}
    @if ($activeTab === 'monthly-detail')
        <x-filament::section heading="月度收支明细表">
            <x-slot name="headerEnd">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    共 {{ $monthlyDetailData ? $monthlyDetailData['days_in_month'] : 0 }} 天
                </div>
            </x-slot>
            
            {{-- 月份切换器 --}}
            <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg relative">
                {{-- 加载指示器覆盖层 --}}
                <div wire:loading wire:target="previousMonth,nextMonth" class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 rounded-lg flex items-center justify-center z-10">
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                        <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">加载中...</span>
                    </div>
                </div>
                
                <button 
                    wire:click="previousMonth"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="previousMonth,nextMonth">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span>上个月</span>
                </button>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $monthlyDetailData ? $monthlyDetailData['year'] . '年 ' . $monthlyDetailData['month'] . '月' : (data_get($this->monthly, 'year', now()->year) . '年 ' . data_get($this->monthly, 'month', now()->month) . '月') }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        点击左右按钮切换月份
                    </div>
                </div>
                
                <button 
                    wire:click="nextMonth"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="previousMonth,nextMonth">
                    <span>下个月</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
            
            @if ($monthlyDetailData)
                {{-- 汇总统计卡片 --}}
                <div class="flex gap-4 mb-6 relative">
                    {{-- 加载指示器 --}}
                    <div wire:loading wire:target="previousMonth,nextMonth" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded-lg flex items-center justify-center z-10">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                            <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">更新中...</span>
                        </div>
                    </div>
                    
                    {{-- 总收入卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总收入</span>
                            <svg class="w-5 h-5" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #16a34a;">
                            ¥{{ number_format($monthlyDetailData['summary']['total_income'], 2) }}
                        </div>
                    </div>

                    {{-- 总支出卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总支出</span>
                            <svg class="w-5 h-5" style="color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #dc2626;">
                            ¥{{ number_format($monthlyDetailData['summary']['total_expenses'], 2) }}
                        </div>
                    </div>

                    {{-- 总利润卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总利润</span>
                            <svg class="w-5 h-5" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #16a34a;">
                            ¥{{ number_format($monthlyDetailData['summary']['total_profit'], 2) }}
                        </div>
                    </div>
                </div>

                {{-- 导出按钮 --}}
                <div class="mt-6 mb-4 flex justify-end">
                    <button 
                        wire:click="exportMonthlyDetail"
                        wire:loading.attr="disabled"
                        wire:target="exportMonthlyDetail"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                        {{-- 默认图标 --}}
                        <svg wire:loading.remove wire:target="exportMonthlyDetail" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        {{-- 加载中图标 --}}
                        <svg wire:loading wire:target="exportMonthlyDetail" class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="exportMonthlyDetail">导出Excel</span>
                        <span wire:loading wire:target="exportMonthlyDetail">导出中...</span>
                    </button>
                </div>

                {{-- 双表格布局 - 左右并排，使用更宽的容器 --}}
                <div class="-mx-6 px-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
                    {{-- 加载指示器覆盖层 --}}
                    <div wire:loading wire:target="previousMonth,nextMonth" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded-lg flex items-center justify-center z-10">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                            <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">加载数据中...</span>
                        </div>
                    </div>
                    
                    {{-- 收入表格 --}}
                    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 flex flex-col" style="max-height: 800px;">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <h3 class="text-lg font-semibold text-green-600 dark:text-green-400">收入明细</h3>
                        </div>
                        <div class="overflow-auto flex-1">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">序号</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">日期</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">金额</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">项目</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">备注</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800" x-data="{ expandedIncome: {} }">
                                    @foreach($monthlyDetailData['income_data'] as $index => $income)
                                    @php
                                        // 查询该结算的其他收入项目
                                        $otherIncomes = $income['has_settlement'] && $income['settlement_id'] 
                                            ? \App\Models\SettlementExpense::where('settlement_id', $income['settlement_id'])
                                                ->where('type', 'income')
                                                ->get()
                                            : collect([]);
                                        $hasExpandableContent = $income['has_settlement'] && ($income['base_profit'] > 0 || $otherIncomes->count() > 0);
                                    @endphp
                                    
                                    {{-- 主收入行 --}}
                                    <tr class="{{ $income['has_settlement'] ? 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800' : 'bg-gray-50 dark:bg-gray-850' }} transition-colors {{ $hasExpandableContent ? 'cursor-pointer' : '' }}"
                                        @if($hasExpandableContent)
                                        @click="expandedIncome[{{ $index }}] = !expandedIncome[{{ $index }}]"
                                        @endif>
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">
                                            @if($hasExpandableContent)
                                            <div class="flex items-center gap-1.5">
                                                <span x-show="!expandedIncome[{{ $index }}]" class="text-gray-400 text-xs">▶</span>
                                                <span x-show="expandedIncome[{{ $index }}]" class="text-gray-400 text-xs">▼</span>
                                                <span>{{ $index + 1 }}</span>
                                            </div>
                                            @else
                                            {{ $index + 1 }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $income['date_display'] }}</td>
                                        <td class="px-3 py-3 text-sm text-right font-medium tabular-nums text-green-600 dark:text-green-400">
                                            {{ $income['total_profit'] > 0 ? '¥' . number_format($income['total_profit'], 2) : '-' }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">
                                            {{ $income['items'] }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $income['remarks'] ?: '-' }}
                                        </td>
                                    </tr>
                                    
                                    {{-- 展开的收入明细行 --}}
                                    @if($hasExpandableContent)
                                    <tr x-show="expandedIncome[{{ $index }}]" 
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        style="display: none;"
                                        class="bg-green-50 dark:bg-gray-800">
                                        <td colspan="5" class="px-3 py-3">
                                            <div class="ml-6">
                                                <table class="w-full text-sm">
                                                    <thead class="border-b border-green-200 dark:border-gray-700">
                                                        <tr>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">序号</th>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">日期</th>
                                                            <th class="px-2 py-2 text-right text-xs font-medium text-gray-600 dark:text-gray-400">金额</th>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">项目</th>
                                                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-600 dark:text-gray-400">备注</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-green-100 dark:divide-gray-700">
                                                        @php $subIndex = 1; @endphp
                                                        
                                                        {{-- 日结算利润行（如果有基础利润） --}}
                                                        @if($income['base_profit'] > 0)
                                                        <tr class="hover:bg-green-100 dark:hover:bg-gray-750">
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ $subIndex++ }}</td>
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $income['date_display'] }}</td>
                                                            <td class="px-2 py-2 text-right font-medium tabular-nums text-green-600 dark:text-green-400">
                                                                ¥{{ number_format($income['base_profit'], 2) }}
                                                            </td>
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300">日结算利润</td>
                                                            <td class="px-2 py-2 text-gray-500 dark:text-gray-400">-</td>
                                                        </tr>
                                                        @endif
                                                        
                                                        {{-- 其他收入项目行（可编辑备注） --}}
                                                        @foreach($otherIncomes as $otherIncome)
                                                        <tr class="hover:bg-green-100 dark:hover:bg-gray-750">
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ $subIndex++ }}</td>
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $income['date_display'] }}</td>
                                                            <td class="px-2 py-2 text-right font-medium tabular-nums text-green-600 dark:text-green-400">
                                                                ¥{{ number_format($otherIncome->amount, 2) }}
                                                            </td>
                                                            <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ $otherIncome->item_name }}</td>
                                                            <td class="px-2 py-2">
                                                                <div 
                                                                    x-data="{ editing: false, value: '{{ addslashes($otherIncome->remarks ?? '') }}' }"
                                                                    @click.stop="editing = true"
                                                                    class="cursor-pointer relative">
                                                                    {{-- 加载指示器 --}}
                                                                    <div wire:loading wire:target="updateExpenseRemark" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded flex items-center justify-center">
                                                                        <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                    </div>
                                                                    
                                                                    <span x-show="!editing" class="text-gray-600 dark:text-gray-400">
                                                                        {{ $otherIncome->remarks ?: '点击编辑' }}
                                                                    </span>
                                                                    <input 
                                                                        x-show="editing"
                                                                        x-model="value"
                                                                        @blur="editing = false; $wire.updateExpenseRemark({{ $otherIncome->id }}, value)"
                                                                        @keydown.enter="editing = false; $wire.updateExpenseRemark({{ $otherIncome->id }}, value)"
                                                                        @click.stop
                                                                        class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                                        type="text">
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    {{-- 支出表格 --}}
                    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 flex flex-col" style="max-height: 800px;">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                            <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">支出明细</h3>
                        </div>
                        <div class="overflow-auto flex-1">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">序号</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">日期</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">金额</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">项目</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">备注</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($monthlyDetailData['expense_data'] as $index => $expense)
                                    <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $index + 1 }}</td>
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ \Carbon\Carbon::parse($expense['date'])->format('Y.n.j') }}</td>
                                        <td class="px-3 py-3 text-sm text-right font-medium tabular-nums text-red-600 dark:text-red-400">
                                            ¥{{ number_format($expense['amount'], 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $expense['item_name'] }}</td>
                                        <td class="px-3 py-3 text-sm">
                                            <div 
                                                x-data="{ editing: false, value: '{{ addslashes($expense['remarks']) }}' }"
                                                @click="editing = true"
                                                class="cursor-pointer relative">
                                                {{-- 加载指示器 --}}
                                                <div wire:loading wire:target="updateExpenseRemark" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded flex items-center justify-center">
                                                    <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                                
                                                <span x-show="!editing" class="text-gray-600 dark:text-gray-400">
                                                    {{ $expense['remarks'] ?: '点击编辑' }}
                                                </span>
                                                <input 
                                                    x-show="editing"
                                                    x-model="value"
                                                    @blur="editing = false; $wire.updateExpenseRemark({{ $expense['id'] }}, value)"
                                                    @keydown.enter="editing = false; $wire.updateExpenseRemark({{ $expense['id'] }}, value)"
                                                    class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                                    type="text">
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                </div>
            @else
                <div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <svg class="mx-auto mb-4" width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-lg font-medium">暂无数据</p>
                </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- 日报表 --}}
    @if ($activeTab === 'daily')
        <x-filament::section heading="日度交易报表">
            @if ($dailyData)
                {{-- 日期导航器 --}}
                <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <button 
                        wire:click="previousDay"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-25 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        上一天
                    </button>

                    <div class="flex items-center gap-3">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $dailyData['date_display'] }} {{ $dailyData['day_of_week'] }}
                        </div>
                        <button 
                            wire:click="goToday"
                            class="px-3 py-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-md transition-colors">
                            回到今天
                        </button>
                    </div>

                    <button 
                        wire:click="nextDay"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-25 transition">
                        下一天
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                {{-- 基本信息 --}}
                <div class="flex items-center justify-between mb-4 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-lg border border-blue-200 dark:border-gray-600">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            共 {{ $dailyData['summary']['total_count'] }} 笔交易
                        </span>
                        @if ($dailyData['has_settlement'])
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded text-xs font-medium">已结余</span>
                        @else
                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 rounded text-xs font-medium">未结余</span>
                        @endif
                    </div>
                    
                    {{-- 操作按钮 --}}
                    <div class="flex gap-2">
                        {{ $this->dailyForm }}
                    </div>
                </div>

                {{-- 交易统计（flex一行三个卡片） --}}
                <div style="display: flex; gap: 16px; margin-top: 24px;">
                    {{-- 出账 --}}
                    <div style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 16px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background-color: #dcfce7;">
                            <svg style="width: 24px; height: 24px; color: #15803d;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280;">出账</div>
                            <div style="font-size: 24px; font-weight: bold; color: #15803d;">{{ $dailyData['summary']['outgoing_count'] }} 笔</div>
                        </div>
                    </div>
                    
                    {{-- 入账 --}}
                    <div style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 16px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background-color: #dbeafe;">
                            <svg style="width: 24px; height: 24px; color: #1e40af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280;">入账</div>
                            <div style="font-size: 24px; font-weight: bold; color: #1e40af;">{{ $dailyData['summary']['income_count'] }} 笔</div>
                        </div>
                    </div>
                    
                    {{-- 即时买断 --}}
                    <div style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 16px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div style="display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 50%; background-color: #f3e8ff;">
                            <svg style="width: 24px; height: 24px; color: #7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280;">即时买断</div>
                            <div style="font-size: 24px; font-weight: bold; color: #7c3aed;">{{ $dailyData['summary']['instant_count'] }} 笔</div>
                        </div>
                    </div>
                </div>

                {{-- 结余信息（如果有） --}}
                @if ($dailyData['has_settlement'] && $dailyData['settlement'])
                    <div style="margin-top: 24px; padding: 16px; border: 1px solid #d1d5db; border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <svg style="width: 20px; height: 20px; color: #15803d;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h4 style="font-weight: 600; color: #1f2937; margin: 0;">当日结余信息</h4>
                        </div>
                        
                        {{-- 两列布局 --}}
                        <table style="width: 100%; font-size: 14px;">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">结余前本金：</span>
                                    <span style="font-weight: 600; float: right;">¥{{ number_format($dailyData['settlement']['previous_capital'], 2) }}</span>
                                </td>
                                <td style="padding: 8px 0 8px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">出账利润：</span>
                                    <span style="font-weight: 600; color: #16a34a; float: right;">+¥{{ number_format($dailyData['settlement']['outgoing_profit'], 2) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">即时利润：</span>
                                    <span style="font-weight: 600; color: #16a34a; float: right;">+¥{{ number_format($dailyData['settlement']['instant_profit'], 2) }}</span>
                                </td>
                                <td style="padding: 8px 0 8px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">总利润：</span>
                                    <span style="font-weight: 600; color: #16a34a; float: right;">+¥{{ number_format($dailyData['settlement']['total_profit'], 2) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">支出：</span>
                                    <span style="font-weight: 600; color: #dc2626; float: right;">-¥{{ number_format($dailyData['settlement']['expenses'], 2) }}</span>
                                </td>
                                <td style="padding: 8px 0 8px 32px; border-bottom: 1px solid #e5e7eb;">
                                    <span style="color: #6b7280;">结余后本金：</span>
                                    <span style="font-weight: 600; float: right;">¥{{ number_format($dailyData['settlement']['new_capital'], 2) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0;">
                                    <span style="color: #6b7280;">人民币结余：</span>
                                    <span style="font-weight: 600; float: right;">¥{{ number_format($dailyData['settlement']['rmb_balance_total'], 2) }}</span>
                                </td>
                                <td style="padding: 8px 0 8px 32px;">
                                    <span style="color: #6b7280;">港币结余：</span>
                                    <span style="font-weight: 600; float: right;">${{ number_format($dailyData['settlement']['new_hkd_balance'], 2) }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                @endif

                {{-- 交易明细表 --}}
                <div class="mt-6">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">交易明细</h4>
                    @if (count($dailyData['transactions']) > 0)
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">时间</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">类型</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">人民币</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">港币</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">汇率</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">渠道</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">地点</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">操作人</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">状态</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($dailyData['transactions'] as $transaction)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                {{ $transaction['created_at'] }}
                                            </td>
                                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                                @if ($transaction['label'] === 'income')
                                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: #dbeafe; color: #1e40af;">
                                                        {{ $transaction['label_text'] }}
                                                    </span>
                                                @elseif ($transaction['label'] === 'outgoing')
                                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: #dcfce7; color: #15803d;">
                                                        {{ $transaction['label_text'] }}
                                                    </span>
                                                @elseif ($transaction['label'] === 'instant_buyout')
                                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: #f3e8ff; color: #7c3aed;">
                                                        {{ $transaction['label_text'] }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: #f3f4f6; color: #374151;">
                                                        {{ $transaction['label_text'] }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-right tabular-nums" style="color: #dc2626;">
                                                ¥{{ number_format($transaction['rmb_amount'], 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-right tabular-nums" style="color: #16a34a;">
                                                ${{ number_format($transaction['hkd_amount'], 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right tabular-nums">
                                                {{ number_format($transaction['exchange_rate'], 3) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $transaction['channel'] }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $transaction['location'] }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $transaction['user'] }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded text-xs font-medium {{ $transaction['settlement_status'] === 'settled' ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' }}">
                                                    {{ $transaction['settlement_status'] === 'settled' ? '已结' : '未结' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <svg class="mx-auto mb-4" width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <p class="text-lg font-medium">当天无交易记录</p>
                        </div>
                    @endif
                </div>
        @endif
    </x-filament::section>
        @endif

    {{-- 年度报表 --}}
    @if ($activeTab === 'yearly')
        <x-filament::section heading="年度报表">
            <x-slot name="headerEnd">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    共 12 个月
                </div>
            </x-slot>
            
            {{-- 年份切换器 --}}
            <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg relative">
                {{-- 加载指示器覆盖层 --}}
                <div wire:loading wire:target="previousYear,nextYear" class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 rounded-lg flex items-center justify-center z-10">
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                        <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">加载中...</span>
                    </div>
                </div>
                
                <button 
                    wire:click="previousYear"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="previousYear,nextYear">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span>上一年</span>
                </button>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $yearlyData ? $yearlyData['year'] . '年' : (data_get($this->yearly, 'year', now()->year) . '年') }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        点击左右按钮切换年份
                    </div>
                </div>
                
                <button 
                    wire:click="nextYear"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="previousYear,nextYear">
                    <span>下一年</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
            
            @if ($yearlyData)
                {{-- 汇总统计卡片 --}}
                <div class="flex gap-4 mb-6 relative">
                    {{-- 加载指示器 --}}
                    <div wire:loading wire:target="previousYear,nextYear" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded-lg flex items-center justify-center z-10">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                            <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">更新中...</span>
                        </div>
                    </div>
                    
                    {{-- 净利润卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">净利润</span>
                            <svg class="w-5 h-5" style="color: #16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #16a34a;">
                            ¥{{ number_format($yearlyData['summary']['net_profit'], 2) }}
                        </div>
                    </div>

                    {{-- 总支出卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总支出</span>
                            <svg class="w-5 h-5" style="color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #dc2626;">
                            ¥{{ number_format($yearlyData['summary']['total_expenses'], 2) }}
                        </div>
                    </div>

                    {{-- 总本金卡片 --}}
                    <div class="flex-1 rounded-lg p-4 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">总本金</span>
                            <svg class="w-5 h-5" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold tabular-nums" style="color: #2563eb;">
                            ¥{{ number_format($yearlyData['summary']['final_capital'], 2) }}
                        </div>
                    </div>
                </div>

                {{-- 年度明细表 --}}
                <div class="-mx-6 px-6 relative">
                    {{-- 加载指示器覆盖层 --}}
                    <div wire:loading wire:target="previousYear,nextYear,updateDividend" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded-lg flex items-center justify-center z-10">
                        <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                            <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">加载数据中...</span>
                        </div>
                    </div>
                    
                    <div class="overflow-auto" style="max-height: 700px;">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">序号</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">月份</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">本金</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">总收入</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">总支出</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">总利润</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">季度分红</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($yearlyData['monthly_data'] as $index => $month)
                                <tr class="{{ $month['has_data'] ? 'bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800' : 'bg-gray-50 dark:bg-gray-850' }} transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $month['month_display'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $month['has_data'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ $month['has_data'] ? '¥' . number_format($month['capital'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $month['has_data'] ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ $month['has_data'] ? '¥' . number_format($month['income'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $month['has_data'] ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ $month['has_data'] ? '¥' . number_format($month['expenses'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $month['has_data'] ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                        {{ $month['has_data'] ? '¥' . number_format($month['profit'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        <div 
                                            x-data="{ editing: false, value: '{{ $month['dividend'] }}' }"
                                            @click="editing = true"
                                            class="cursor-pointer relative">
                                            {{-- 加载指示器 --}}
                                            <div wire:loading wire:target="updateDividend" class="absolute inset-0 bg-white/70 dark:bg-gray-900/70 rounded flex items-center justify-center">
                                                <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                            
                                            <span x-show="!editing" class="text-gray-600 dark:text-gray-400 font-medium tabular-nums">
                                                {{ $month['dividend'] > 0 ? '¥' . number_format($month['dividend'], 2) : '点击编辑' }}
                                            </span>
                                            <input 
                                                x-show="editing"
                                                x-model="value"
                                                @blur="editing = false; $wire.updateDividend({{ $yearlyData['year'] }}, {{ $month['month'] }}, parseFloat(value) || 0)"
                                                @keydown.enter="editing = false; $wire.updateDividend({{ $yearlyData['year'] }}, {{ $month['month'] }}, parseFloat(value) || 0)"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-right"
                                                type="number"
                                                step="0.01"
                                                min="0">
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <svg class="mx-auto mb-4" width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-lg font-medium">暂无数据</p>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament::page>



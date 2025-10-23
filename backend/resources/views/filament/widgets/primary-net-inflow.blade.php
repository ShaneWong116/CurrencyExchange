<x-filament-widgets::widget>
    @php
        $rmbPositive = $rmbNet > 0;
        $rmbNegative = $rmbNet < 0;
        $rmbIcon = $rmbPositive ? 'heroicon-m-arrow-trending-up' : ($rmbNegative ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');

        $rmbHex = $rmbPositive ? '#ef4444' : ($rmbNegative ? '#16a34a' : '#6b7280'); // red-500 / green-600 / gray-500
        $rmbNetFormatted = ($rmbNegative ? '-' : '') . '¥' . number_format(abs($rmbNet), 2);

        $hkdPositive = $hkdNet > 0;
        $hkdNegative = $hkdNet < 0;
        $hkdIcon = $hkdPositive ? 'heroicon-m-arrow-trending-up' : ($hkdNegative ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus');
        $hkdHex = $hkdPositive ? '#ef4444' : ($hkdNegative ? '#16a34a' : '#6b7280');
        $hkdNetFormatted = ($hkdNegative ? '-' : '') . 'HK$' . number_format(abs($hkdNet), 2);
    @endphp

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-none bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                <span>
                    @if($rmbNet > 0)
                        今日人民币净流入
                    @elseif($rmbNet < 0)
                        今日人民币净流出
                    @else
                        今日人民币净变动
                    @endif
                </span>
                <x-filament::icon :icon="$rmbIcon" class="h-5 w-5" style="color: #6b7280" />
            </div>
            <div class="mt-1 flex items-baseline gap-x-2">
                <div class="text-5xl font-extrabold leading-none tracking-tight" style="color: {{ $rmbHex }}">{{ $rmbNetFormatted }}</div>
            </div>
            <div class="mt-3 flex items-center gap-x-2 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-5 w-5 text-emerald-500" />
                <span>入账: ¥{{ number_format($rmbIncome, 2) }}</span>
                <span>|</span>
                <span>出账: ¥{{ number_format($rmbOutcome, 2) }}</span>
            </div>
        </div>

        <div class="rounded-none bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-x-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                <span>
                    @if($hkdNet > 0)
                        今日港币净流入
                    @elseif($hkdNet < 0)
                        今日港币净流出
                    @else
                        今日港币净变动
                    @endif
                </span>
                <x-filament::icon :icon="$hkdIcon" class="h-5 w-5" style="color: #6b7280" />
            </div>
            <div class="mt-1 flex items-baseline gap-x-2">
                <div class="text-5xl font-extrabold leading-none tracking-tight" style="color: {{ $hkdHex }}">{{ $hkdNetFormatted }}</div>
            </div>
            <div class="mt-3 flex items-center gap-x-2 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-5 w-5 text-emerald-500" />
                {{-- 对港币：按方向显示，入账=outcome 汇总，出账=income 汇总 --}}
                <span>入账: HK${{ number_format($hkdOutcome, 2) }}</span>
                <span>|</span>
                <span>出账: HK${{ number_format($hkdIncome, 2) }}</span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>



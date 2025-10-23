<x-filament::section>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-none bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">收入-人民币</div>
            <div class="mt-1 text-2xl font-extrabold" style="color:#16a34a">¥{{ number_format($incomeRmb ?? 0, 2) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 text-emerald-500" />
                <span>人民币合计</span>
            </div>
        </div>
        <div class="rounded-none bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">收入-港币</div>
            <div class="mt-1 text-2xl font-extrabold" style="color:#16a34a">HK${{ number_format($incomeHkd ?? 0, 2) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 text-emerald-500" />
                <span>港币合计</span>
            </div>
        </div>
        <div class="rounded-none bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">支出-人民币</div>
            <div class="mt-1 text-2xl font-extrabold" style="color:#ef4444">¥{{ number_format($outcomeRmb ?? 0, 2) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 text-red-500" />
                <span>人民币合计</span>
            </div>
        </div>
        <div class="rounded-none bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-sm text-gray-500 dark:text-gray-400">支出-港币</div>
            <div class="mt-1 text-2xl font-extrabold" style="color:#ef4444">HK${{ number_format($outcomeHkd ?? 0, 2) }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-banknotes" class="h-4 w-4 text-red-500" />
                <span>港币合计</span>
            </div>
        </div>
    </div>
</x-filament::section>

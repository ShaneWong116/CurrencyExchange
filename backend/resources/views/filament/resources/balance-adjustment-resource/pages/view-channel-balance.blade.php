<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 渠道信息卡片 --}}
        <div>
            {{ $this->channelInfolist }}
        </div>

        {{-- 调整记录表格 --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>


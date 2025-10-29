<div class="mb-6">
    <div class="fi-tabs flex items-center overflow-x-auto gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @foreach($locations as $value => $label)
            <button
                wire:click="$set('locationFilter', '{{ $value }}'); $wire.$refresh()"
                type="button"
                @class([
                    'fi-tabs-item flex items-center gap-2 rounded-lg font-medium transition-all duration-200 whitespace-nowrap',
                    // 总览标签样式 - 明显更大
                    'px-6 py-3.5 text-lg scale-105' => $value === 'all',
                    // 普通地点标签样式
                    'px-4 py-2 text-sm' => $value !== 'all',
                    // 选中状态
                    'bg-primary-600 text-white shadow-lg' => $currentLocation == $value,
                    // 未选中状态
                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => $currentLocation != $value,
                ])
            >
                @if($value === 'all')
                    <x-filament::icon
                        icon="heroicon-m-home"
                        class="h-6 w-6"
                    />
                @else
                    <x-filament::icon
                        icon="heroicon-m-map-pin"
                        class="h-4 w-4"
                    />
                @endif
                <span class="{{ $value === 'all' ? 'font-bold' : '' }}">{{ $label }}</span>
            </button>
        @endforeach
    </div>
</div>


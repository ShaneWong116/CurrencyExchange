<x-filament::page>
    <x-filament::section heading="日结">
        {{ $this->dailyForm }}
        @if (session('reports.daily'))
            <pre class="text-xs">{{ json_encode(session('reports.daily'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </x-filament::section>

    <x-filament::section heading="月结" class="mt-6">
        {{ $this->monthlyForm }}
        @if (session('reports.monthly'))
            <pre class="text-xs">{{ json_encode(session('reports.monthly'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </x-filament::section>

    <x-filament::section heading="年结" class="mt-6">
        {{ $this->yearlyForm }}
        @if (session('reports.yearly'))
            <pre class="text-xs">{{ json_encode(session('reports.yearly'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </x-filament::section>
</x-filament::page>



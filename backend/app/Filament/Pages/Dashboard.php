<?php

namespace App\Filament\Pages;

use App\Models\Location;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Attributes\Url;

class Dashboard extends BaseDashboard implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = '财务管理仪表盘';
    protected static ?string $navigationLabel = '仪表盘';

    #[Url(except: 'all')]
    public ?string $locationFilter = 'all';

    public function getWidgets(): array
    {
        // 使用 make() 传递参数到 widgets
        return [
            \App\Filament\Widgets\BalanceOverview::make(['locationFilter' => $this->locationFilter]),
            \App\Filament\Widgets\PrimaryNetInflow::make(['locationFilter' => $this->locationFilter]),
            \App\Filament\Widgets\StatsOverview::make(['locationFilter' => $this->locationFilter]),
            \App\Filament\Widgets\InstantBuyoutTable::make(['locationFilter' => $this->locationFilter]),
            \App\Filament\Widgets\ChannelOverview::make(['locationFilter' => $this->locationFilter]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.dashboard-header', [
            'locations' => $this->getLocationOptions(),
            'currentLocation' => $this->locationFilter,
        ]);
    }

    protected function getLocationOptions(): array
    {
        $options = [
            'all' => '总览',
        ];

        $locations = Location::where('status', 'active')
            ->orderBy('name')
            ->get();

        foreach ($locations as $location) {
            $options[$location->id] = $location->name;
        }

        return $options;
    }

    public function updatedLocationFilter(): void
    {
        // 当筛选器改变时,触发 widgets 重新加载
        $this->dispatch('locationFilterChanged', locationId: $this->locationFilter);
        
        // 强制刷新所有 widgets
        $this->dispatch('$refresh');
    }

    public function getLocationId(): ?int
    {
        return $this->locationFilter === 'all' ? null : (int) $this->locationFilter;
    }
}
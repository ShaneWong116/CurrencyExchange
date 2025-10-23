<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use App\Models\Transaction;
// removed unused: use Filament\Widgets\StatsOverviewWidget\Card;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\TransactionSummary::class,
        ];
    }

    protected function getTableHeader(): View
    {
        $activeTab = request()->query('activeTab', 'all');

        $base = Transaction::query();
        if ($activeTab === 'today') {
            $base = $base->whereDate('created_at', today());
        }

        $incomeRmb = (float) (clone $base)->where('type', 'income')->sum('rmb_amount');
        // 港币方向相反：入账显示为出账类型汇总，出账显示为入账类型汇总
        $incomeHkd = (float) (clone $base)->where('type', 'outcome')->sum('hkd_amount');
        $outcomeRmb = (float) (clone $base)->where('type', 'outcome')->sum('rmb_amount');
        $outcomeHkd = (float) (clone $base)->where('type', 'income')->sum('hkd_amount');

        return view('filament.widgets.transaction-table-summary', [
            'incomeRmb' => $incomeRmb,
            'incomeHkd' => $incomeHkd,
            'outcomeRmb' => $outcomeRmb,
            'outcomeHkd' => $outcomeHkd,
        ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('全部')
                ->badge(fn (): int => $this->getModel()::count()),

            'today' => Tab::make('今日')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today()))
                ->badge(fn (): int => $this->getModel()::whereDate('created_at', today())->count()),

            'income' => Tab::make('入账')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'income'))
                ->badge(fn (): int => $this->getModel()::where('type', 'income')->count()),

            'outcome' => Tab::make('出账')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'outcome'))
                ->badge(fn (): int => $this->getModel()::where('type', 'outcome')->count()),
        ];
    }
}

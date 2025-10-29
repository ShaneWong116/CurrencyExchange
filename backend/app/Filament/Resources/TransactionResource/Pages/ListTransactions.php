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
        $activeTab = request()->query('activeTab', 'unsettled');

        // 根据当前标签页筛选数据
        $base = Transaction::query();
        
        if ($activeTab === 'settled') {
            // 已结算标签页:显示已结算的记录
            $base = $base->where('settlement_status', 'settled');
        } else {
            // 其他标签页:只显示未结算的记录
            $base = $base->where('settlement_status', 'unsettled');
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
            'unsettled' => Tab::make('未结算')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('settlement_status', 'unsettled'))
                ->badge(fn (): int => $this->getModel()::where('settlement_status', 'unsettled')->count()),

            'income' => Tab::make('入账')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('settlement_status', 'unsettled')->where('type', 'income'))
                ->badge(fn (): int => $this->getModel()::where('settlement_status', 'unsettled')->where('type', 'income')->count()),

            'outcome' => Tab::make('出账')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('settlement_status', 'unsettled')->where('type', 'outcome'))
                ->badge(fn (): int => $this->getModel()::where('settlement_status', 'unsettled')->where('type', 'outcome')->count()),

            'settled' => Tab::make('已结算')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('settlement_status', 'settled'))
                ->badge(fn (): int => $this->getModel()::where('settlement_status', 'settled')->count()),
        ];
    }
}

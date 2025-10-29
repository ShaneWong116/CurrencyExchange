<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InstantBuyoutTable extends BaseWidget
{
    protected static ?string $heading = '即时买断汇总';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    // 接收父页面传递的 location filter
    public ?string $locationFilter = 'all';

    protected $listeners = [
        'locationFilterChanged' => 'updateLocationFilter',
        '$refresh' => '$refresh',
    ];

    public function updateLocationFilter($locationId): void
    {
        $this->locationFilter = $locationId;
        // 强制立即刷新表格
        $this->resetTable();
    }

    protected function getLocationId(): ?int
    {
        return $this->locationFilter === 'all' ? null : (int) $this->locationFilter;
    }
    
    // 确保 Widget 始终可见
    public static function canView(): bool
    {
        return true;
    }

    protected function getTableQuery(): Builder
    {
        $locationId = $this->getLocationId();

        if ($locationId) {
            // 🚀 按地点筛选时,查询所有未结算的即时买断交易
            $result = Transaction::where('location_id', $locationId)
                ->where('type', 'instant_buyout')
                ->where('settlement_status', 'unsettled')  // 只查询未结算的
                ->selectRaw('
                    COUNT(*) as count,
                    COALESCE(SUM(rmb_amount), 0) as rmb_amount,
                    COALESCE(SUM(hkd_amount), 0) as hkd_amount,
                    COALESCE(AVG(CASE WHEN instant_rate > 0 THEN instant_rate END), 0) as avg_rate,
                    COALESCE(SUM(instant_profit), 0) as total_profit
                ')
                ->first();

            $count = $result->count ?? 0;
            $rmbAmount = $result->rmb_amount ?? 0;
            $hkdAmount = $result->hkd_amount ?? 0;
            $avgRate = $result->avg_rate ?? 0;
            $totalProfit = $result->total_profit ?? 0;
        } else {
            // 总览时,从统计表读取即时买断数据
            $stats = \App\Models\CurrentStatistic::getDashboardStats();
            
            $count = $stats['instant_buyout_count'];
            $rmbAmount = $stats['rmb_instant_buyout'];
            $hkdAmount = $stats['hkd_instant_buyout'];
            
            // 计算平均即时买断汇率（如果有交易）
            $avgRate = 0;
            $totalProfit = 0;
            
            if ($count > 0) {
                // 🚀 一次性查询平均汇率和总利润
                $result = Transaction::where('type', 'instant_buyout')
                    ->selectRaw('
                        COALESCE(AVG(CASE WHEN instant_rate > 0 THEN instant_rate END), 0) as avg_rate,
                        COALESCE(SUM(instant_profit), 0) as total_profit
                    ')
                    ->first();
                
                $avgRate = $result->avg_rate ?? 0;
                $totalProfit = $result->total_profit ?? 0;
            }
        }
        
        // 构造一个虚拟查询返回汇总行
        return Transaction::query()
            ->selectRaw("
                0 as id,
                '即时买断' as name,
                '即时买断' as category,
                {$count} as transaction_count,
                {$rmbAmount} as rmb_income,
                {$rmbAmount} as rmb_outcome,
                {$hkdAmount} as hkd_income,
                {$hkdAmount} as hkd_outcome,
                " . round($avgRate, 3) . " as instant_rate,
                {$totalProfit} as profit
            ")
            ->whereRaw('1 = 1')
            ->limit(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll(null)  // 禁用轮询,避免延迟
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('类型')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('分类')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('transaction_count')
                    ->label('交易笔数')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('rmb_income')
                    ->label('人民币入账')
                    ->formatStateUsing(fn ($state) => '¥' . number_format($state, 2))
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('rmb_outcome')
                    ->label('人民币出账')
                    ->formatStateUsing(fn ($state) => '¥' . number_format($state, 2))
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('hkd_income')
                    ->label('港币入账')
                    ->formatStateUsing(fn ($state) => 'HK$' . number_format($state, 2))
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('hkd_outcome')
                    ->label('港币出账')
                    ->formatStateUsing(fn ($state) => 'HK$' . number_format($state, 2))
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('instant_rate')
                    ->label('即时买断汇率')
                    ->formatStateUsing(fn ($state) => number_format($state, 3))
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('profit')
                    ->label('今日利润')
                    ->formatStateUsing(fn ($state) => '¥' . number_format($state, 2))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
            ]);
    }
}


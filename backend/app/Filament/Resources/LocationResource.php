<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = '地点';
    protected static ?string $modelLabel = '地点';
    protected static ?string $pluralModelLabel = '地点';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = '基础配置';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('名称')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('code')
                    ->label('编码')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                Forms\Components\Textarea::make('remark')
                    ->label('备注')
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Select::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
                    ])
                    ->default('active')
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('编码')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? '启用' : '停用'),
                Tables\Columns\TextColumn::make('field_users_count')
                    ->label('人员数')
                    ->counts('fieldUsers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('交易数')
                    ->counts('transactions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}



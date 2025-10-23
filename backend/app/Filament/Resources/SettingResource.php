<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = '系统设置';
    protected static ?string $modelLabel = '系统设置';
    protected static ?string $pluralModelLabel = '系统设置';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('配置信息')
                    ->schema([
                        Forms\Components\TextInput::make('key_name')
                            ->label('配置项名称')
                            ->required()
                            ->unique(Setting::class, 'key_name', ignoreRecord: true)
                            ->maxLength(100)
                            ->disabled(fn (string $context) => $context === 'edit'),
                            
                        Forms\Components\Select::make('type')
                            ->label('值类型')
                            ->options([
                                'string' => '字符串',
                                'number' => '数字',
                                'boolean' => '布尔值',
                                'json' => 'JSON',
                            ])
                            ->required()
                            ->default('string')
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('description')
                            ->label('描述')
                            ->maxLength(500),
                    ])->columns(3),
                    
                Forms\Components\Section::make('配置值')
                    ->schema([
                        Forms\Components\TextInput::make('key_value')
                            ->label('配置值')
                            ->required()
                            ->visible(fn (callable $get) => in_array($get('type'), ['string', 'number']))
                            ->columnSpan('full'),
                            
                        Forms\Components\Toggle::make('key_value_boolean')
                            ->label('配置值')
                            ->visible(fn (callable $get) => $get('type') === 'boolean')
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, $state) {
                                $component->state((bool) $state);
                            })
                            ->dehydrateStateUsing(fn ($state) => $state ? '1' : '0')
                            ->columnSpan('full'),
                            
                        Forms\Components\Textarea::make('key_value')
                            ->label('配置值 (JSON格式)')
                            ->visible(fn (callable $get) => $get('type') === 'json')
                            ->rows(5)
                            ->columnSpan('full')
                            ->helperText('请输入有效的JSON格式'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('key_name')
                    ->label('配置项名称')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'string' => 'primary',
                        'number' => 'success',
                        'boolean' => 'warning',
                        'json' => 'info',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'string' => '字符串',
                        'number' => '数字',
                        'boolean' => '布尔值',
                        'json' => 'JSON',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('key_value')
                    ->label('配置值')
                    ->limit(30)
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->type === 'boolean') {
                            return $state === '1' ? '是' : '否';
                        }
                        return $state;
                    }),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'string' => '字符串',
                        'number' => '数字',
                        'boolean' => '布尔值',
                        'json' => 'JSON',
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
            ->defaultSort('key_name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'view' => Pages\ViewSetting::route('/{record}'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}

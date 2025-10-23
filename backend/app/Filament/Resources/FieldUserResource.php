<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FieldUserResource\Pages;
use App\Models\FieldUser;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Support\Facades\Hash;

class FieldUserResource extends Resource
{
    protected static ?string $model = FieldUser::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = '外勤人员';
    protected static ?string $modelLabel = '外勤人员';
    protected static ?string $pluralModelLabel = '外勤人员';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->label('用户名')
                            ->required()
                            ->unique(FieldUser::class, 'username', ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash(),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('姓名')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('location_id')
                            ->label('所属地点')
                            ->options(Location::where('status', 'active')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\TextInput::make('password')
                            ->label('密码')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(6)
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('创建时必填，编辑时留空则不修改'),
                    ])->columns(3),
                    
                Forms\Components\Section::make('状态信息')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                'active' => '启用',
                                'inactive' => '停用',
                            ])
                            ->required()
                            ->default('active'),
                            
                        Forms\Components\DateTimePicker::make('last_login_at')
                            ->label('最后登录时间')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('username')
                    ->label('用户名')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('地点')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? '启用' : '停用'),
                    
                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('交易数量')
                    ->counts('transactions')
                    ->suffix(' 笔')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('drafts_count')
                    ->label('草稿数量')
                    ->counts('drafts')
                    ->suffix(' 个')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('最后登录')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('从未登录'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
                    ]),
                Tables\Filters\SelectFilter::make('location')
                    ->label('地点')
                    ->relationship('location', 'name'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFieldUsers::route('/'),
            'create' => Pages\CreateFieldUser::route('/create'),
            'view' => Pages\ViewFieldUser::route('/{record}'),
            'edit' => Pages\EditFieldUser::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = '后台账号';
    protected static ?string $modelLabel = '后台账号';
    protected static ?string $pluralModelLabel = '后台账号';
    protected static ?int $navigationSort = 99;
    protected static ?string $navigationGroup = '系统管理';

    // 只有 admin 能看到此菜单
    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('账号信息')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->label('用户名')
                            ->required()
                            ->unique(User::class, 'username', ignoreRecord: true)
                            ->maxLength(100)
                            ->alphaDash()
                            ->helperText('只能包含字母、数字、破折号和下划线'),
                            
                        Forms\Components\TextInput::make('password')
                            ->label('密码')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(6)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('创建时必填，编辑时留空则不修改密码'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('角色与状态')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('角色')
                            ->options([
                                'admin' => '管理员',
                                'finance' => '财务人员',
                            ])
                            ->required()
                            ->default('finance')
                            ->disabled()
                            ->helperText(fn ($record) => $record?->isAdmin() 
                                ? '管理员角色不可修改' 
                                : '系统只允许一个管理员账号，新账号只能创建为财务人员'),
                            
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                'active' => '启用',
                                'inactive' => '停用',
                            ])
                            ->required()
                            ->default('active')
                            ->disabled(fn ($record) => $record?->isAdmin())
                            ->helperText(fn ($record) => $record?->isAdmin() ? '管理员账号状态不可修改' : null),
                            
                        Forms\Components\DateTimePicker::make('last_login_at')
                            ->label('最后登录时间')
                            ->disabled()
                            ->displayFormat('Y-m-d H:i:s'),
                    ])->columns(3),
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
                    ->sortable()
                    ->copyable()
                    ->copyMessage('用户名已复制'),
                    
                Tables\Columns\TextColumn::make('role')
                    ->label('角色')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'finance' => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => '管理员',
                        'finance' => '财务人员',
                        default => $state,
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? '启用' : '停用')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('最后登录')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('从未登录')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('角色')
                    ->options([
                        'admin' => '管理员',
                        'finance' => '财务人员',
                    ]),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label(fn ($record) => $record->isAdmin() ? '修改密码' : '编辑'),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->isAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('删除后台账号')
                    ->modalDescription('确定要删除此账号吗？此操作不可恢复。')
                    ->modalSubmitActionLabel('确认删除'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('批量删除后台账号')
                        ->modalDescription('确定要删除选中的账号吗？管理员账号不会被删除。')
                        ->action(function ($records) {
                            // 过滤掉管理员账号
                            $records->reject(fn ($record) => $record->isAdmin())->each->delete();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // 每30秒自动刷新
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['balanceAdjustments', 'auditLogs']);
    }
}

<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return '查看后台账号';
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        // 管理员账号不能编辑和删除
        if (!$this->record->isAdmin()) {
            $actions[] = Actions\EditAction::make();
            $actions[] = Actions\DeleteAction::make()
                ->requiresConfirmation();
        }

        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('账号信息')
                    ->schema([
                        Components\TextEntry::make('id')
                            ->label('ID'),
                        Components\TextEntry::make('username')
                            ->label('用户名')
                            ->copyable()
                            ->copyMessage('用户名已复制'),
                        Components\TextEntry::make('role')
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
                            }),
                        Components\TextEntry::make('status')
                            ->label('状态')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'danger',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => $state === 'active' ? '启用' : '停用'),
                    ])->columns(4),

                Components\Section::make('活动信息')
                    ->schema([
                        Components\TextEntry::make('last_login_at')
                            ->label('最后登录时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('从未登录'),
                        Components\TextEntry::make('created_at')
                            ->label('创建时间')
                            ->dateTime('Y-m-d H:i:s'),
                        Components\TextEntry::make('updated_at')
                            ->label('更新时间')
                            ->dateTime('Y-m-d H:i:s'),
                    ])->columns(3),

                Components\Section::make('统计信息')
                    ->schema([
                        Components\TextEntry::make('balance_adjustments_count')
                            ->label('余额调整次数')
                            ->suffix(' 次')
                            ->default(0),
                        Components\TextEntry::make('audit_logs_count')
                            ->label('操作日志数')
                            ->suffix(' 条')
                            ->default(0),
                    ])->columns(2)
                    ->visible(fn ($record) => $record->isAdmin()),
            ]);
    }
}

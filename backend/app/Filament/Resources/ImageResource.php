<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageResource\Pages;
use App\Models\Image;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class ImageResource extends Resource
{
    protected static ?string $model = Image::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = '图片管理';
    protected static ?string $modelLabel = '图片';
    protected static ?string $pluralModelLabel = '图片';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = '系统管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('图片信息')
                    ->schema([
                        Forms\Components\TextInput::make('uuid')
                            ->label('UUID')
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('original_name')
                            ->label('原始文件名')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Select::make('transaction_id')
                            ->label('关联交易')
                            ->relationship('transaction', 'id')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                            
                        Forms\Components\Select::make('draft_id')
                            ->label('关联草稿')
                            ->relationship('draft', 'id')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('文件详情')
                    ->schema([
                        Forms\Components\TextInput::make('file_size')
                            ->label('文件大小（字节）')
                            ->numeric()
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('mime_type')
                            ->label('MIME类型')
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('width')
                            ->label('宽度')
                            ->numeric()
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('height')
                            ->label('高度')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('图片预览')
                    ->schema([
                        Forms\Components\ViewField::make('preview')
                            ->label('图片预览')
                            ->view('filament.forms.image-preview')
                            ->visible(fn ($record) => $record && $record->isImage()),
                    ])->hidden(fn ($record) => !$record || !$record->isImage()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\ViewColumn::make('thumbnail')
                    ->label('缩略图')
                    ->view('filament.tables.image-thumbnail')
                    ->width(60),
                    
                Tables\Columns\TextColumn::make('original_name')
                    ->label('文件名')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
                    
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, 'image/') ? 'success' : 'info'),
                    
                Tables\Columns\TextColumn::make('file_size_formatted')
                    ->label('文件大小')
                    ->state(fn (Image $record): string => $record->getFileSizeFormatted())
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('file_size', $direction);
                    }),
                    
                Tables\Columns\TextColumn::make('dimensions')
                    ->label('尺寸')
                    ->state(fn (Image $record): string => 
                        $record->width && $record->height ? "{$record->width} × {$record->height}" : '-'
                    ),
                    
                Tables\Columns\TextColumn::make('transaction.id')
                    ->label('关联交易')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('draft.id')
                    ->label('关联草稿')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('mime_type')
                    ->label('文件类型')
                    ->options([
                        'image/jpeg' => 'JPEG',
                        'image/png' => 'PNG',
                        'image/gif' => 'GIF',
                        'image/webp' => 'WebP',
                    ]),
                    
                Tables\Filters\Filter::make('has_transaction')
                    ->label('有关联交易')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('transaction_id')),
                    
                Tables\Filters\Filter::make('has_draft')
                    ->label('有关联草稿')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('draft_id')),
                    
                Tables\Filters\Filter::make('orphaned')
                    ->label('孤立文件')
                    ->query(fn (Builder $query): Builder => $query->whereNull('transaction_id')->whereNull('draft_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('下载')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (Image $record) {
                        $content = base64_decode($record->file_content);
                        return response($content)
                            ->header('Content-Type', $record->mime_type)
                            ->header('Content-Disposition', 'attachment; filename="' . $record->original_name . '"');
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('cleanup_orphaned')
                        ->label('清理孤立文件')
                        ->icon('heroicon-m-trash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->transaction_id && !$record->draft_id) {
                                    $record->delete();
                                    $count++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("已清理 {$count} 个孤立文件")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImages::route('/'),
            'view' => Pages\ViewImage::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // 图片通过API上传，不允许在后台直接创建
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isFinance();
    }
}

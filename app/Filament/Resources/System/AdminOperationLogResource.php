<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\AdminOperationLogResource\Pages;
use App\Models\AdminOperationLog;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminOperationLogResource extends Resource
{
    protected static ?string $model = AdminOperationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1002;

    public static function getNavigationLabel(): string
    {
        return '后台操作日志';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && $user->class >= User::CLASS_SYSOP;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('用户')
                    ->formatStateUsing(fn ($record) => $record->user?->username ?? '用户已删除')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('method')
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // 不允许批量删除，日志只读
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('基本信息')
                    ->schema([
                        Infolists\Components\Grid::make(2)->schema([
                            Infolists\Components\TextEntry::make('id')
                                ->label('日志ID'),
                            Infolists\Components\TextEntry::make('user.username')
                                ->label('操作用户')
                                ->formatStateUsing(fn ($record) => $record->user?->username ?? '用户已删除'),
                            Infolists\Components\TextEntry::make('method')
                                ->label('请求方法')
                                ->badge()
                                ->color(function ($state) {
                                    return match ($state) {
                                        'GET' => 'info',
                                        'POST' => 'success',
                                        'PUT' => 'warning',
                                        'PATCH' => 'warning',
                                        'DELETE' => 'danger',
                                        default => 'gray',
                                    };
                                }),
                            Infolists\Components\TextEntry::make('path')
                                ->label('请求路径')
                                ->copyable(),
                            Infolists\Components\TextEntry::make('ip')
                                ->label('IP地址')
                                ->copyable(),
                            Infolists\Components\TextEntry::make('created_at')
                                ->label('操作时间')
                                ->dateTime(),
                        ]),
                    ]),
                Infolists\Components\Section::make('请求参数')
                    ->schema([
                        Infolists\Components\TextEntry::make('request_query')
                            ->label('Query参数')
                            ->state(function ($record) {
                                $state = $record->request_query;
                                if (is_null($state) || $state === '') {
                                    return '无';
                                }
                                if (is_array($state)) {
                                    return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                }
                                return (string) $state;
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->extraAttributes(['style' => 'font-family: monospace; white-space: pre-wrap;']),
                        Infolists\Components\TextEntry::make('request_body')
                            ->label('Body参数')
                            ->state(function ($record) {
                                $state = $record->request_body;
                                if (is_null($state) || $state === '') {
                                    return '无';
                                }
                                if (is_array($state)) {
                                    return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                }
                                return (string) $state;
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->extraAttributes(['style' => 'font-family: monospace; white-space: pre-wrap;']),
                    ])
                    ->collapsible(),
                Infolists\Components\Section::make('其他信息')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->columnSpanFull()
                            ->copyable(),
                        Infolists\Components\TextEntry::make('old_values')
                            ->label('变更前值')
                            ->state(function ($record) {
                                $state = $record->old_values;
                                if (is_null($state) || $state === '') {
                                    return '无';
                                }
                                if (is_array($state)) {
                                    return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                }
                                return (string) $state;
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->extraAttributes(['style' => 'font-family: monospace; white-space: pre-wrap;']),
                        Infolists\Components\TextEntry::make('new_values')
                            ->label('变更后值')
                            ->state(function ($record) {
                                $state = $record->new_values;
                                if (is_null($state) || $state === '') {
                                    return '无';
                                }
                                if (is_array($state)) {
                                    return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                }
                                return (string) $state;
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->extraAttributes(['style' => 'font-family: monospace; white-space: pre-wrap;']),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminOperationLogs::route('/'),
            'view' => Pages\ViewAdminOperationLog::route('/{record}'),
        ];
    }
}



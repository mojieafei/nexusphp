<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\AdminOperationLogResource\Pages;
use App\Models\AdminOperationLog;
use App\Models\User;
use Filament\Forms;
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
                    ->label('请求方法')
                    ->options([
                        'GET' => 'GET (通常为获取数据)',
                        'POST' => 'POST (通常为创建/提交)',
                        'PUT' => 'PUT (通常为更新)',
                        'PATCH' => 'PATCH (通常为部分更新)',
                        'DELETE' => 'DELETE (删除操作)',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('path')
                    ->label('请求路径')
                    ->form([
                        Forms\Components\TextInput::make('path')
                            ->label('路径包含')
                            ->placeholder('例如: /nexusphp/system/medals')
                            ->helperText('支持模糊匹配，留空则不筛选'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['path'],
                            fn (Builder $query, $value): Builder => $query->where('path', 'like', "%{$value}%")
                        );
                    }),
                Tables\Filters\Filter::make('ip')
                    ->label('IP地址')
                    ->form([
                        Forms\Components\TextInput::make('ip')
                            ->label('IP地址')
                            ->placeholder('例如: 192.168.1.1')
                            ->helperText('支持完整匹配或部分匹配'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['ip'],
                            fn (Builder $query, $value): Builder => $query->where('ip', 'like', "%{$value}%")
                        );
                    }),
                Tables\Filters\Filter::make('user_id')
                    ->label('操作用户')
                    ->form([
                        Forms\Components\TextInput::make('user_id')
                            ->label('用户ID')
                            ->numeric()
                            ->placeholder('输入用户ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['user_id'],
                            fn (Builder $query, $value): Builder => $query->where('user_id', $value)
                        );
                    }),
                Tables\Filters\Filter::make('exclude_livewire')
                    ->label('排除 Livewire 请求')
                    ->query(function (Builder $query): Builder {
                        return $query->where('path', '!=', 'livewire/update');
                    })
                    ->toggle()
                    ->helperText('注意：Livewire 请求可能包含实际业务操作，请谨慎排除'),
                Tables\Filters\Filter::make('only_get_method')
                    ->label('仅显示 GET 请求')
                    ->query(function (Builder $query): Builder {
                        return $query->where('method', 'GET');
                    })
                    ->toggle()
                    ->helperText('用于查看哪些 GET 请求可能执行了操作（通常 GET 只用于获取数据）'),
                Tables\Filters\Filter::make('created_at')
                    ->label('操作时间')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('开始日期'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
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



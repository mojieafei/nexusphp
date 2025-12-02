<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\AdminOperationLogResource\Pages;
use App\Models\AdminOperationLog;
use App\Models\User;
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

    public static function canViewAny(?User $user): bool
    {
        $user = $user ?: auth()->user();
        return $user && $user->class >= User::CLASS_SYSOP;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('用户')
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
                    ->sortable(),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminOperationLogs::route('/'),
            'view' => Pages\ViewAdminOperationLog::route('/{record}'),
        ];
    }
}



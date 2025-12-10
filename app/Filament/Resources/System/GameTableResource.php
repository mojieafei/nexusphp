<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\GameTableResource\Pages;
use App\Models\GameTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GameTableResource extends Resource
{
    protected static ?string $model = GameTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = '系统';

    protected static ?string $navigationLabel = '火星幸运局桌子';

    protected static ?string $pluralModelLabel = '火星幸运局桌子';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('桌子名称')
                    ->required()
                    ->maxLength(64),
                Forms\Components\TextInput::make('bet_amount')
                    ->label('下注额')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('双方各下注同额'),
                Forms\Components\TextInput::make('owner_rake_percent')
                    ->label('老板抽成(%)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(90)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable(),
                Tables\Columns\TextColumn::make('bet_amount')->label('下注额')->sortable(),
                Tables\Columns\TextColumn::make('owner_rake_percent')->label('老板抽成(%)')->sortable(),
                Tables\Columns\TextColumn::make('owner_id')->label('老板ID')->sortable(),
                Tables\Columns\TextColumn::make('owner_until')
                    ->label('老板到期(ms)')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?: '-'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Model $record) {
                        $now = (int)(microtime(true) * 1000);
                        $hasActiveOwner = $record->owner_id !== null && ($record->owner_until ?? 0) > $now;
                        if ($hasActiveOwner) {
                            throw new \RuntimeException('该桌当前有老板，不能删除');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function ($records) {
                        $now = (int)(microtime(true) * 1000);
                        foreach ($records as $record) {
                            $hasActiveOwner = $record->owner_id !== null && ($record->owner_until ?? 0) > $now;
                            if ($hasActiveOwner) {
                                throw new \RuntimeException("桌子 {$record->id} 当前有老板，不能删除");
                            }
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGameTables::route('/'),
            'create' => Pages\CreateGameTable::route('/create'),
            'edit' => Pages\EditGameTable::route('/{record}/edit'),
        ];
    }
}


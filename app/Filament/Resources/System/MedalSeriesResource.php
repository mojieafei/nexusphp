<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\MedalSeriesResource\Pages;
use App\Models\MedalSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MedalSeriesResource extends Resource
{
    protected static ?string $model = MedalSeries::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('medal-series.navigation.title');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('medal-series.sections.basic'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('medal-series.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('medal-series.fields.slug'))
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('medal-series.fields.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('priority')
                        ->label(__('label.priority'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Textarea::make('description')
                        ->label(__('medal-series.fields.description'))
                        ->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make(__('medal-series.sections.visual'))
                ->schema([
                    Forms\Components\TextInput::make('cover_image')
                        ->label(__('medal-series.fields.cover_image'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('banner_image')
                        ->label(__('medal-series.fields.banner_image'))
                        ->maxLength(255),
                ])->columns(2),
            Forms\Components\Section::make(__('medal-series.sections.reward'))
                ->schema([
                    Forms\Components\TextInput::make('reward_title')
                        ->label(__('medal-series.fields.reward_title'))
                        ->maxLength(255),
                    Forms\Components\Textarea::make('reward_description')
                        ->label(__('medal-series.fields.reward_description'))
                        ->columnSpanFull(),
                    Forms\Components\Grid::make()
                        ->schema([
                            Forms\Components\Select::make('reward_currency')
                                ->options([
                                    'seedbonus' => __('medal-series.reward_currency.seedbonus'),
                                ])
                                ->label(__('medal-series.fields.reward_currency'))
                                ->default('seedbonus'),
                            Forms\Components\TextInput::make('reward_amount')
                                ->label(__('medal-series.fields.reward_amount'))
                                ->numeric()
                                ->default(0),
                            Forms\Components\Select::make('reward_interval_unit')
                                ->label(__('medal-series.fields.reward_interval_unit'))
                                ->options(MedalSeries::listRewardIntervalOptions())
                                ->default('none'),
                            Forms\Components\TextInput::make('reward_interval_value')
                                ->label(__('medal-series.fields.reward_interval_value'))
                                ->numeric()
                                ->default(1),
                            Forms\Components\TextInput::make('reward_cooldown_hours')
                                ->label(__('medal-series.fields.reward_cooldown_hours'))
                                ->numeric()
                                ->default(0),
                        ])->columns(5),
                    Forms\Components\Grid::make()
                        ->schema([
                            Forms\Components\DateTimePicker::make('reward_start_at')
                                ->label(__('medal-series.fields.reward_start_at')),
                            Forms\Components\DateTimePicker::make('reward_end_at')
                                ->label(__('medal-series.fields.reward_end_at')),
                        ])->columns(2),
                ]),
            Forms\Components\Section::make(__('medal-series.sections.bonus'))
                ->schema([
                    Forms\Components\TextInput::make('bonus_addition_factor')
                        ->label(__('medal-series.fields.bonus_addition_factor'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Textarea::make('bonus_addition_description')
                        ->label(__('medal-series.fields.bonus_addition_description'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('medal-series.fields.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('medal-series.fields.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('label.priority'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label(__('medal-series.fields.reward_amount'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1)),
                Tables\Columns\TextColumn::make('reward_interval_unit')
                    ->label(__('medal-series.fields.reward_interval_unit'))
                    ->formatStateUsing(fn (MedalSeries $record) => $record->reward_interval_label),
                Tables\Columns\TextColumn::make('bonus_addition_factor')
                    ->label(__('medal-series.fields.bonus_addition_factor')),
                Tables\Columns\TextColumn::make('medals_count')
                    ->label(__('medal-series.fields.medals_count'))
                    ->counts('medals'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('label.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('medal-series.filters.is_active'))
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('priority', 'desc')->orderBy('id', 'desc'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedalSeries::route('/'),
            'create' => Pages\CreateMedalSeries::route('/create'),
            'edit' => Pages\EditMedalSeries::route('/{record}/edit'),
        ];
    }
}


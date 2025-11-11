<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StardustAchievementResource\Pages;
use App\Models\StardustAchievement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StardustAchievementResource extends Resource
{
    protected static ?string $model = StardustAchievement::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = '成就管理';

    protected static ?string $modelLabel = '成就';

    protected static ?string $pluralModelLabel = '成就';

    protected static ?string $navigationGroup = '游戏管理';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('name_en')
                            ->label('英文名称')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('icon')
                            ->label('图标')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\Select::make('type')
                            ->label('类型')
                            ->required()
                            ->options([
                                'collect' => '收集类',
                                'interaction' => '互动类',
                                'special' => '特殊类',
                            ]),
                        Forms\Components\TextInput::make('reward_stardust')
                            ->label('奖励星尘')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_repeatable')
                            ->label('可重复')
                            ->default(false),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('排序')
                            ->numeric()
                            ->default(0),
                    ]),
                Forms\Components\Textarea::make('description')
                    ->label('描述')
                    ->rows(3)
                    ->required(),
                Forms\Components\Textarea::make('conditions')
                    ->label('条件 (JSON)')
                    ->rows(6)
                    ->default('{}')
                    ->required()
                    ->rules(['json'])
                    ->helperText('请填写 JSON，例如 {"land_slots":12} 或 {"water_times":5}。')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        }
                        return $state ?: '{}';
                    })
                    ->dehydrateStateUsing(function ($state) {
                        $decoded = json_decode($state ?: '{}', true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw ValidationException::withMessages([
                                'conditions' => '条件字段必须是合法的 JSON 格式。',
                            ]);
                        }
                        return $decoded;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'collect' => '收集类',
                        'interaction' => '互动类',
                        'special' => '特殊类',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('reward_stardust')
                    ->label('奖励星尘')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_repeatable')
                    ->label('可重复')
                    ->boolean(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('条件')
                    ->limit(40)
                    ->tooltip(fn ($record) => json_encode($record->conditions, JSON_UNESCAPED_UNICODE)),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新于')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStardustAchievements::route('/'),
            'create' => Pages\CreateStardustAchievement::route('/create'),
            'edit' => Pages\EditStardustAchievement::route('/{record}/edit'),
        ];
    }
}


<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StardustFarmResource\Pages;
use App\Models\StardustFarm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StardustFarmResource extends Resource
{
    protected static ?string $model = StardustFarm::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-asia-australia';

    protected static ?string $navigationLabel = '星尘农场';

    protected static ?string $modelLabel = '农场';

    protected static ?string $pluralModelLabel = '星尘农场';

    protected static ?string $navigationGroup = '游戏管理';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('用户')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('stardust')
                    ->label('星尘数量')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('level')
                    ->label('农场等级')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('experience')
                    ->label('经验值')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('land_slots')
                    ->label('土地数量')
                    ->numeric()
                    ->default(3)
                    ->minValue(3)
                    ->maxValue(12)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('用户')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stardust')
                    ->label('星尘')
                    ->numeric()
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('level')
                    ->label('等级')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('experience')
                    ->label('经验值')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('land_slots')
                    ->label('土地数量')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('high_level')
                    ->label('高等级（≥5级）')
                    ->query(fn ($query) => $query->where('level', '>=', 5)),
                Tables\Filters\Filter::make('rich')
                    ->label('土豪（≥10000星尘）')
                    ->query(fn ($query) => $query->where('stardust', '>=', 10000)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_lands')
                    ->label('查看土地')
                    ->icon('heroicon-o-map')
                    ->url(fn (StardustFarm $record): string => StardustLandResource::getUrl('index', ['farm_id' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('stardust', 'desc');
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
            'index' => Pages\ListStardustFarms::route('/'),
            'create' => Pages\CreateStardustFarm::route('/create'),
            'edit' => Pages\EditStardustFarm::route('/{record}/edit'),
        ];
    }
}


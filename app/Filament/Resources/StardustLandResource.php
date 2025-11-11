<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StardustLandResource\Pages;
use App\Models\StardustLand;
use App\Models\StardustCrop;
use App\Models\StardustFarm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class StardustLandResource extends Resource
{
    protected static ?string $model = StardustLand::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = '土地管理';

    protected static ?string $modelLabel = '土地';

    protected static ?string $pluralModelLabel = '土地';

    protected static ?string $navigationGroup = '游戏管理';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('farm_id')
                    ->label('农场')
                    ->options(function () {
                        return StardustFarm::with('user')->get()->mapWithKeys(function ($farm) {
                            return [$farm->id => $farm->user->username . ' (ID: ' . $farm->id . ')'];
                        });
                    })
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('slot_index')
                    ->label('土地序号')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(11)
                    ->required(),
                Forms\Components\Select::make('crop_id')
                    ->label('作物')
                    ->options(StardustCrop::all()->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->label('状态')
                    ->options([
                        'empty' => '空地',
                        'growing' => '生长中',
                        'mature' => '已成熟',
                        'withered' => '已枯萎',
                    ])
                    ->default('empty')
                    ->required()
                    ->live(),
                Forms\Components\DateTimePicker::make('planted_at')
                    ->label('种植时间')
                    ->nullable()
                    ->visible(fn (Get $get) => in_array($get('status'), ['growing', 'mature', 'withered'])),
                Forms\Components\DateTimePicker::make('mature_at')
                    ->label('成熟时间')
                    ->nullable()
                    ->visible(fn (Get $get) => in_array($get('status'), ['growing', 'mature', 'withered'])),
                Forms\Components\DateTimePicker::make('wither_at')
                    ->label('枯萎时间')
                    ->nullable()
                    ->visible(fn (Get $get) => in_array($get('status'), ['growing', 'mature', 'withered'])),
                Forms\Components\TextInput::make('watered_times')
                    ->label('被浇水次数')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('can_be_stolen')
                    ->label('可被偷取')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('farm.user.username')
                    ->label('农场主')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slot_index')
                    ->label('土地序号')
                    ->sortable(),
                Tables\Columns\TextColumn::make('crop.emoji')
                    ->label('作物')
                    ->formatStateUsing(fn ($state, $record) => $state ? $state . ' ' . $record->crop->name : '-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'empty' => 'gray',
                        'growing' => 'info',
                        'mature' => 'success',
                        'withered' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'empty' => '空地',
                        'growing' => '生长中',
                        'mature' => '已成熟',
                        'withered' => '已枯萎',
                    }),
                Tables\Columns\TextColumn::make('mature_at')
                    ->label('成熟时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('watered_times')
                    ->label('浇水次数')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\IconColumn::make('can_be_stolen')
                    ->label('可偷')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                $farmId = request()->query('farm_id');
                if ($farmId) {
                    $query->where('farm_id', $farmId);
                }
            })
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'empty' => '空地',
                        'growing' => '生长中',
                        'mature' => '已成熟',
                        'withered' => '已枯萎',
                    ]),
                Tables\Filters\SelectFilter::make('crop_id')
                    ->label('作物')
                    ->options(StardustCrop::all()->pluck('name', 'id')),
                Tables\Filters\Filter::make('can_be_stolen')
                    ->label('可被偷取')
                    ->query(fn ($query) => $query->where('can_be_stolen', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('harvest')
                    ->label('收获')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->visible(fn (StardustLand $record) => in_array($record->status, ['mature', 'withered']))
                    ->action(function (StardustLand $record) {
                        $result = $record->harvest();
                        if ($result) {
                            Notification::make()
                                ->title('收获成功')
                                ->body("收获了 {$result['fragments']} 个 {$result['crop_name']} 碎片")
                                ->success()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('forceMature')
                    ->label('一键成熟')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->visible(fn (StardustLand $record) => $record->status === 'growing')
                    ->requiresConfirmation()
                    ->action(function (StardustLand $record) {
                        $record->forceMature();
                        Notification::make()
                            ->title('成熟处理完成')
                            ->body("土地 #{$record->slot_index} 已设置为成熟状态")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('bulkForceMature')
                        ->label('批量一键成熟')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'growing') {
                                    $record->forceMature();
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('批量成熟完成')
                                ->body($count > 0 ? "已处理 {$count} 块土地。" : '所选土地均无需处理。')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListStardustLands::route('/'),
            'create' => Pages\CreateStardustLand::route('/create'),
            'edit' => Pages\EditStardustLand::route('/{record}/edit'),
        ];
    }
}


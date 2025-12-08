<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\SpecialPermissionResource\Pages;
use App\Models\SpecialPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class SpecialPermissionResource extends Resource
{
    protected static ?string $model = SpecialPermission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 200;

    public static function getNavigationLabel(): string
    {
        return '特殊权限管理';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('权限代码')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100)
                    ->helperText('唯一标识符，如：auto_claim_stardust'),
                Forms\Components\TextInput::make('name')
                    ->label('权限名称')
                    ->required()
                    ->maxLength(200),
                Forms\Components\Textarea::make('description')
                    ->label('权限描述')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('是否启用')
                    ->default(true)
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
                Tables\Columns\TextColumn::make('code')
                    ->label('权限代码')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('权限名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('启用状态')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('拥有用户数')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('启用状态')
                    ->options([
                        true => '启用',
                        false => '禁用',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListSpecialPermissions::route('/'),
            'create' => Pages\CreateSpecialPermission::route('/create'),
            'edit' => Pages\EditSpecialPermission::route('/{record}/edit'),
        ];
    }
}


<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\BonusProductResource\Pages;
use App\Models\BonusProduct;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class BonusProductResource extends Resource
{
    protected static ?string $model = BonusProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 300;

    public static function getNavigationLabel(): string
    {
        return '魔力值商品管理';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('art')
                    ->label('商品类型标识')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $record, Get $get) =>
                            // 与 menge 组合唯一，允许相同 art 不同数量
                            $rule->where('menge', $get('menge'))
                    )
                    ->maxLength(50)
                    ->helperText('与数量 (menge) 组合唯一，示例：traffic，invite，title'),
                Forms\Components\TextInput::make('name')
                    ->label('商品名称')
                    ->required()
                    ->maxLength(200),
                Forms\Components\Select::make('category')
                    ->label('商品分类')
                    ->options(BonusProduct::categoryOptions())
                    ->default(BonusProduct::CATEGORY_TOOL)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('商品描述')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('points')
                    ->label('需要的魔力值')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('menge')
                    ->label('数量')
                    ->numeric()
                    ->default(0)
                    ->helperText('某些商品需要，如流量大小（字节）'),
                Forms\Components\Select::make('product_type')
                    ->label('商品类型')
                    ->options([
                        BonusProduct::PRODUCT_TYPE_NORMAL => '普通商品',
                        BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION => '特殊权限商品',
                    ])
                    ->default(BonusProduct::PRODUCT_TYPE_NORMAL)
                    ->required()
                    ->reactive(),
                Forms\Components\Select::make('special_permission_id')
                    ->label('关联的特殊权限')
                    ->relationship('specialPermission', 'name', fn ($query) => $query->where('is_active', true))
                    ->visible(fn (Forms\Get $get) => $get('product_type') === BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION)
                    ->helperText('选择关联的特殊权限'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('排序顺序')
                    ->numeric()
                    ->default(0)
                    ->helperText('数字越小越靠前'),
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
                Tables\Columns\TextColumn::make('art')
                    ->label('类型标识')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('商品名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('分类')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => BonusProduct::categoryOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('points')
                    ->label('魔力值')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 1)),
                Tables\Columns\TextColumn::make('product_type')
                    ->label('商品类型')
                    ->badge()
                    ->colors([
                        'primary' => BonusProduct::PRODUCT_TYPE_NORMAL,
                        'success' => BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION,
                    ])
                    ->formatStateUsing(fn ($state) => $state === BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION ? '特殊权限' : '普通商品'),
                Tables\Columns\TextColumn::make('specialPermission.name')
                    ->label('关联权限')
                    ->default('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('启用状态')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->label('商品类型')
                    ->options([
                        BonusProduct::PRODUCT_TYPE_NORMAL => '普通商品',
                        BonusProduct::PRODUCT_TYPE_SPECIAL_PERMISSION => '特殊权限商品',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('分类')
                    ->options(BonusProduct::categoryOptions()),
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
            ])
            ->defaultSort('sort_order', 'asc');
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
            'index' => Pages\ListBonusProducts::route('/'),
            'create' => Pages\CreateBonusProduct::route('/create'),
            'edit' => Pages\EditBonusProduct::route('/{record}/edit'),
        ];
    }
}


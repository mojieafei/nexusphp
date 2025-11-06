<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Banner管理';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('是否启用')
                            ->default(true)
                            ->required(),
                        
                        Forms\Components\TextInput::make('sort_order')
                            ->label('排序权重')
                            ->helperText('数字越大越靠前显示')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('资源设置')
                    ->schema([
                        Forms\Components\Select::make('resource_type')
                            ->label('资源类型')
                            ->options(Banner::getResourceTypes())
                            ->required()
                            ->default('image')
                            ->reactive(),
                        
                        Forms\Components\TextInput::make('resource_url')
                            ->label('资源URL')
                            ->helperText('支持相对路径（如: banner/aa.mp4）或完整URL（如: https://cdn.example.com/banner.jpg）')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('jump_url')
                            ->label('跳转链接')
                            ->helperText('点击banner后跳转的链接（可选）')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('显示设置')
                    ->schema([
                        Forms\Components\Select::make('visible_to')
                            ->label('可见等级')
                            ->helperText('选择的等级及以上用户可见')
                            ->options(Banner::getUserClassList())
                            ->default(0)
                            ->required(),
                        
                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('开始时间')
                            ->helperText('留空表示不限制')
                            ->nullable(),
                        
                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('结束时间')
                            ->helperText('留空表示不限制')
                            ->nullable(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->limit(30),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),
                
                Tables\Columns\BadgeColumn::make('resource_type')
                    ->label('类型')
                    ->formatStateUsing(fn ($state) => $state === 'image' ? '图片' : '视频')
                    ->colors([
                        'success' => 'image',
                        'primary' => 'video',
                    ]),
                
                Tables\Columns\TextColumn::make('resource_url')
                    ->label('资源路径')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->resource_url),
                
                Tables\Columns\TextColumn::make('visible_to')
                    ->label('可见等级')
                    ->formatStateUsing(fn ($state) => Banner::getUserClassList()[$state] ?? $state),
                
                Tables\Columns\TextColumn::make('time_range')
                    ->label('展示时间')
                    ->formatStateUsing(function ($record) {
                        $start = $record->start_time ? $record->start_time->format('Y-m-d H:i') : '不限';
                        $end = $record->end_time ? $record->end_time->format('Y-m-d H:i') : '不限';
                        return new HtmlString("<span style='font-size:12px;'>{$start}<br/>~ {$end}</span>");
                    }),
                
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('状态')
                    ->options([
                        1 => '已启用',
                        0 => '已禁用',
                    ]),
                
                Tables\Filters\SelectFilter::make('resource_type')
                    ->label('资源类型')
                    ->options(Banner::getResourceTypes()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('sort_order', 'desc')->orderBy('id', 'desc'));
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
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}


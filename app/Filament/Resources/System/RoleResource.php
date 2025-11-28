<?php

namespace App\Filament\Resources\System;

use App\Enums\Permission\PermissionEnum;
use App\Filament\Resources\System\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Role & Permission';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return '角色管理';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        // 获取所有权限选项
        $permissionOptions = [];
        foreach (PermissionEnum::cases() as $permission) {
            $permissionOptions[$permission->value] = $permission->value;
        }
        
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('角色标识')
                    ->helperText('唯一标识，如：normal_user, torrent_reviewer')
                    ->maxLength(50),
                Forms\Components\TextInput::make('display_name')
                    ->required()
                    ->label('显示名称')
                    ->helperText('在前端显示的名称，如：普通用户, 种审员')
                    ->maxLength(100),
                Forms\Components\TextInput::make('icon')
                    ->label('图标')
                    ->helperText('角色图标，可以是emoji（如：✅、📤）或图片路径')
                    ->maxLength(50),
                Forms\Components\Textarea::make('description')
                    ->label('描述')
                    ->rows(3),
                Forms\Components\Toggle::make('is_default')
                    ->label('默认角色')
                    ->helperText('是否为默认角色（新用户自动获得）'),
                Forms\Components\CheckboxList::make('permissions')
                    ->label('权限')
                    ->options($permissionOptions)
                    ->columns(2)
                    ->descriptions([
                        'uploadspecial' => '上传到特殊分区',
                        'beanonymous' => '匿名发布',
                        'view_special_torrent' => '查看特殊种子',
                        'torrent_hr' => '设置H&R',
                        'torrent-set-price' => '设置种子价格',
                        'torrentsticky' => '设置置顶',
                        'torrentmanage' => '管理种子',
                        'torrent-approval-allow-automatic' => '允许自动审核',
                        'torrent-set-special-tag' => '设置特殊标签',
                        'upload' => '上传种子',
                        'prfmanage' => '管理用户基本信息',
                        'cruprfmanage' => '管理用户敏感信息',
                        'userprofile' => '查看用户敏感信息',
                    ])
                    ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state, $record) {
                        if ($record) {
                            $component->state($record->permission_list);
                        }
                    })
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label('ID'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('角色标识'),
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->label('显示名称'),
                Tables\Columns\TextColumn::make('icon')
                    ->label('图标')
                    ->formatStateUsing(fn ($state) => $state ? (preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $state) ? '<img src="' . htmlspecialchars($state) . '" style="max-width:20px;max-height:20px;" />' : $state) : '')
                    ->html()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('描述')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('默认角色')
                    ->boolean(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('权限数量')
                    ->counts('permissions'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('用户数量')
                    ->counts('users'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_default')
                    ->label('默认角色')
                    ->query(fn (Builder $query): Builder => $query->where('is_default', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}


<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RoleSalarySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.role-salary-settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1001;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return '角色工资配置';
    }

    public function getTitle(): string
    {
        return '角色工资配置';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->class >= \App\Models\User::CLASS_ADMINISTRATOR;
    }

    public function mount(): void
    {
        $this->form->fill($this->getInitialData());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('发布员（uploader）')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('uploader_min_torrents')
                                ->label('最少发种数量 N')
                                ->numeric()
                                ->minValue(0)
                                ->default(10)
                                ->helperText('每月发种数量达到此值，且满足体积要求，才发放发布员工资。'),
                            TextInput::make('uploader_min_volume_gb')
                                ->label('最少总体积 X（GB）')
                                ->numeric()
                                ->minValue(0)
                                ->default(30)
                                ->helperText('本月所有有效种子的总体积下限（单位 GB）。'),
                            TextInput::make('uploader_bonus')
                                ->label('每个种子魔力值')
                                ->numeric()
                                ->minValue(0)
                                ->default(1000)
                                ->helperText('达到最低要求后，按种子数量 × 此值计算工资（多劳多得）。'),
                            TextInput::make('uploader_invites')
                                ->label('工资邀请数量')
                                ->numeric()
                                ->minValue(0)
                                ->default(1),
                        ]),
                    ]),

                Section::make('转载员（re_uploader）')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('reuploader_min_valid_torrents')
                                ->label('最少有效种子数量')
                                ->numeric()
                                ->minValue(0)
                                ->default(1)
                                ->helperText('本月有效种子数量达到此值时才发放转载员工资。'),
                            TextInput::make('reuploader_bonus_per_torrent')
                                ->label('每个种子魔力值')
                                ->numeric()
                                ->minValue(0)
                                ->default(1000),
                            TextInput::make('reuploader_invites')
                                ->label('工资邀请数量')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),
                    ]),

                Section::make('保种员（seeder）')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('seeder_base_days')
                                ->label('基础天数（每级 20 天）')
                                ->numeric()
                                ->minValue(0)
                                ->default(20)
                                ->helperText('第 1 次工资需累计 seedtime ≥ base_days 天；第 2 次 ≥ 2×base_days，以此类推。'),
                            TextInput::make('seeder_bonus_per_torrent')
                                ->label('每个有效保种魔力值')
                                ->numeric()
                                ->minValue(0)
                                ->default(100),
                            TextInput::make('seeder_invites')
                                ->label('工资邀请数量')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),
                    ]),

                Section::make('种审员（torrent_reviewer）')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('reviewer_min_reviews')
                                ->label('最少审核数量 N')
                                ->numeric()
                                ->minValue(0)
                                ->default(60),
                            TextInput::make('reviewer_bonus_per_review')
                                ->label('每次审核魔力值')
                                ->numeric()
                                ->minValue(0)
                                ->default(200),
                            TextInput::make('reviewer_invites')
                                ->label('工资邀请数量')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getInitialData(): array
    {
        return [
            // 这里强制从数据库读取，避免 Setting::get 的 10 分钟缓存导致页面不更新
            'uploader_min_torrents' => Setting::getFromDb('role_salary.uploader.min_torrents') ?? 10,
            'uploader_min_volume_gb' => Setting::getFromDb('role_salary.uploader.min_volume_gb') ?? 30,
            'uploader_bonus' => Setting::getFromDb('role_salary.uploader.bonus') ?? 1000,
            'uploader_invites' => Setting::getFromDb('role_salary.uploader.invites') ?? 1,

            'reuploader_min_valid_torrents' => Setting::getFromDb('role_salary.reuploader.min_valid_torrents') ?? 1,
            'reuploader_bonus_per_torrent' => Setting::getFromDb('role_salary.reuploader.bonus_per_torrent') ?? 1000,
            'reuploader_invites' => Setting::getFromDb('role_salary.reuploader.invites') ?? 0,

            'seeder_base_days' => Setting::getFromDb('role_salary.seeder.base_days') ?? 20,
            'seeder_bonus_per_torrent' => Setting::getFromDb('role_salary.seeder.bonus_per_torrent') ?? 100,
            'seeder_invites' => Setting::getFromDb('role_salary.seeder.invites') ?? 0,

            'reviewer_min_reviews' => Setting::getFromDb('role_salary.reviewer.min_reviews') ?? 60,
            'reviewer_bonus_per_review' => Setting::getFromDb('role_salary.reviewer.bonus_per_review') ?? 200,
            'reviewer_invites' => Setting::getFromDb('role_salary.reviewer.invites') ?? 0,
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $map = [
            'uploader_min_torrents' => 'role_salary.uploader.min_torrents',
            'uploader_min_volume_gb' => 'role_salary.uploader.min_volume_gb',
            'uploader_bonus' => 'role_salary.uploader.bonus',
            'uploader_invites' => 'role_salary.uploader.invites',

            'reuploader_min_valid_torrents' => 'role_salary.reuploader.min_valid_torrents',
            'reuploader_bonus_per_torrent' => 'role_salary.reuploader.bonus_per_torrent',
            'reuploader_invites' => 'role_salary.reuploader.invites',

            'seeder_base_days' => 'role_salary.seeder.base_days',
            'seeder_bonus_per_torrent' => 'role_salary.seeder.bonus_per_torrent',
            'seeder_invites' => 'role_salary.seeder.invites',

            'reviewer_min_reviews' => 'role_salary.reviewer.min_reviews',
            'reviewer_bonus_per_review' => 'role_salary.reviewer.bonus_per_review',
            'reviewer_invites' => 'role_salary.reviewer.invites',
        ];

        foreach ($map as $formKey => $settingKey) {
            $value = $data[$formKey] ?? null;
            Setting::query()->updateOrCreate(
                ['name' => $settingKey],
                ['value' => (string)$value, 'autoload' => 'yes']
            );
        }

        Notification::make()
            ->title('角色工资配置已保存')
            ->success()
            ->send();
    }
}



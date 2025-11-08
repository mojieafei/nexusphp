<?php

namespace App\Filament\Pages;

use App\Models\BonusLogs;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class BatchAddBonus extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.batch-add-bonus';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 11;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return '批量增加魔力值';
    }

    public function getTitle(): string
    {
        return '批量增加魔力值';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->class >= \App\Models\User::CLASS_SYSOP;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('user_ids')
                    ->label('用户ID列表')
                    ->placeholder('每行一个用户ID，例如：&#10;1&#10;100&#10;256')
                    ->rows(10)
                    ->required()
                    ->helperText('每行输入一个用户ID（数字），支持批量添加'),

                TextInput::make('bonus_amount')
                    ->label('增加魔力值')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(1000)
                    ->suffix('魔力值')
                    ->helperText('每个用户将获得相同数量的魔力值'),

                Textarea::make('comment')
                    ->label('备注说明')
                    ->placeholder('例如：活动奖励、补偿等')
                    ->maxLength(500)
                    ->helperText('此备注将记录在魔力值日志中'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // 解析用户ID列表
        $userIdsText = trim($data['user_ids']);
        $userIdList = array_filter(
            array_map('trim', explode("\n", $userIdsText)),
            fn($id) => !empty($id) && is_numeric($id)
        );

        if (empty($userIdList)) {
            Notification::make()
                ->title('错误')
                ->body('用户ID列表不能为空')
                ->danger()
                ->send();
            return;
        }

        $bonusAmount = (float) $data['bonus_amount'];
        $comment = $data['comment'] ?? '站长批量发放魔力值';

        $successCount = 0;
        $failedUsers = [];
        $results = [];

        DB::beginTransaction();
        try {
            foreach ($userIdList as $userId) {
                // 查找用户
                $user = User::find((int)$userId);

                if (!$user) {
                    $failedUsers[] = "UID {$userId} (用户不存在)";
                    continue;
                }

                // 记录旧魔力值
                $oldBonus = $user->seedbonus;
                $newBonus = $oldBonus + $bonusAmount;

                // 更新用户魔力值
                $user->seedbonus = $newBonus;
                $user->save();

                // 记录魔力值日志
                BonusLogs::create([
                    'uid' => $user->id,
                    'business_type' => BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT,
                    'old_total_value' => $oldBonus,
                    'value' => $bonusAmount,
                    'new_total_value' => $newBonus,
                    'comment' => $comment,
                ]);

                $successCount++;
                $results[] = "UID {$userId} ({$user->username}): {$oldBonus} → {$newBonus} (+{$bonusAmount})";
            }

            DB::commit();

            // 显示成功通知
            $message = "成功为 {$successCount} 个用户增加魔力值";
            if (!empty($failedUsers)) {
                $message .= "\n\n失败列表：\n" . implode("\n", $failedUsers);
            }

            Notification::make()
                ->title('操作完成')
                ->body($message)
                ->success()
                ->send();

            // 清空表单
            $this->form->fill();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('操作失败')
                ->body('发生错误：' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}


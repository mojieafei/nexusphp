<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\OptionsTrait;
use App\Filament\Resources\User\UserResource;
use App\Models\Exam;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\SpecialPermission;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\ExamRepository;
use App\Repositories\MedalRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Nexus\Database\NexusDB;

class UserProfile extends ViewRecord
{
    use InteractsWithRecord;
    use HasRelationManagers;
    use OptionsTrait;

    private static $rep;

    protected static string $resource = UserResource::class;

//    protected static string $view = 'filament.resources.user.user-resource.pages.user-profile';

    private function getRep(): UserRepository
    {
        if (!self::$rep) {
            self::$rep = new UserRepository();
        }
        return self::$rep;
    }

    /**
     * 检查当前用户是否可以编辑目标用户
     * 规则：级别大于目标用户，或者双方都是STAFF_LEADER（站长可以编辑站长）
     */
    private function canEditUser(): bool
    {
        $currentUser = Auth::user();
        // 如果当前用户级别大于目标用户，可以编辑
        if ($currentUser->class > $this->record->class) {
            return true;
        }
        // 特殊规则：如果双方都是STAFF_LEADER（站长），可以编辑
        if ($currentUser->class == User::CLASS_STAFF_LEADER && $this->record->class == User::CLASS_STAFF_LEADER) {
            return true;
        }
        return false;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        if ($this->canEditUser()) {
            $actions[] = $this->buildManageRolesAction();
            $actions[] = $this->buildManageSpecialPermissionsAction();
            $actions[] = $this->buildGrantPropsAction();
            $actions[] = $this->buildGrantMedalAction();
            $actions[] = $this->buildAssignExamAction();
            $actions[] = $this->buildChangeBonusEtcAction();
//            if ($this->record->two_step_secret) {
//                $actions[] = $this->buildDisableTwoStepAuthenticationAction();
//            }
//            if ($this->record->status == User::STATUS_PENDING) {
//                $actions[] = $this->buildConfirmAction();
//            }
            $actions[] = $this->buildResetPasswordAction();
//            $actions[] = $this->buildEnableDisableAction();
//            $actions[] = $this->buildEnableDisableDownloadPrivilegesAction();
//            if (user_can('user-change-class')) {
//                $actions[] = $this->buildChangeClassAction();
//            }
            if (user_can('user-delete')) {
                $actions[] = $this->buildDeleteAction();
            }
            $actions = apply_filter('user_profile_actions', $actions);
        }
        return $actions;
    }

    private function buildEnableDisableAction(): Actions\Action
    {
        return Actions\Action::make('enable_disable')
            ->label($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_btn') : __('admin.resources.user.actions.enable_modal_btn'))
            ->modalHeading($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->form([
                Forms\Components\TextInput::make('reason')->label(__('admin.resources.user.actions.enable_disable_reason'))->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder')),
                Forms\Components\Hidden::make('action')->default($this->record->enabled == 'yes' ? 'disable' : 'enable'),
                Forms\Components\Hidden::make('uid')->default($this->record->id),
            ])
//            ->visible(false)
//            ->hidden(true)
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    if ($data['action'] == 'enable') {
                        $userRep->enableUser(Auth::user(), $data['uid'], $data['reason']);
                    } elseif ($data['action'] == 'disable') {
                        $userRep->disableUser(Auth::user(), $data['uid'], $data['reason']);
                    }
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildDisableTwoStepAuthenticationAction(): Actions\Action
    {
        return Actions\Action::make(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->modalHeading(__('admin.resources.user.actions.disable_two_step_authentication'))
            ->requiresConfirmation()
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->removeTwoStepAuthentication(Auth::user(), $this->record->id);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildChangeBonusEtcAction(): Actions\Action
    {
        return Actions\Action::make(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->modalHeading(__('admin.resources.user.actions.change_bonus_etc_btn'))
            ->form([
                Forms\Components\Radio::make('field')->options([
                    'uploaded' => __('label.user.uploaded'),
                    'downloaded' => __('label.user.downloaded'),
                    'invites' => __('label.user.invites'),
                    'seedbonus' => __('label.user.seedbonus'),
                    'attendance_card' => __('label.user.attendance_card'),
                    'tmp_invites' => __('label.user.tmp_invites'),
                ])
                    ->label(__('admin.resources.user.actions.change_bonus_etc_field_label'))
                    ->inline()
                    ->required()
                    ->reactive()
                ,
                Forms\Components\Radio::make('action')->options([
                    'Increment' => __("admin.resources.user.actions.change_bonus_etc_action_increment"),
                    'Decrement' => __("admin.resources.user.actions.change_bonus_etc_action_decrement"),
                ])
                    ->label(__('admin.resources.user.actions.change_bonus_etc_action_label'))
                    ->inline()
                    ->required()
                ,
                Forms\Components\TextInput::make('value')->integer()->required()
                    ->label(__('admin.resources.user.actions.change_bonus_etc_value_label'))
                    ->helperText(__('admin.resources.user.actions.change_bonus_etc_value_help'))
                ,

                Forms\Components\TextInput::make('duration')->integer()
                    ->label(__('admin.resources.user.actions.change_bonus_etc_duration_label'))
                    ->helperText(__('admin.resources.user.actions.change_bonus_etc_duration_help'))
                    ->hidden(fn (\Filament\Forms\Get $get) => $get('field') != 'tmp_invites')
                ,

                Forms\Components\TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.change_bonus_etc_reason_label'))
                ,
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    if ($data['field'] == 'tmp_invites') {
                        $userRep->addTemporaryInvite(Auth::user(), $this->record->id, $data['action'], $data['value'], $data['duration'], $data['reason']);
                    } else {
                        $userRep->incrementDecrement(Auth::user(), $this->record->id, $data['action'], $data['field'], $data['value'], $data['reason']);
                    }
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildResetPasswordAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.reset_password_btn'))
            ->modalHeading(__('admin.resources.user.actions.reset_password_btn'))
            ->form([
                Forms\Components\TextInput::make('password')->label(__('admin.resources.user.actions.reset_password_label'))->required(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label(__('admin.resources.user.actions.reset_password_confirmation_label'))
                    ->same('password')
                    ->required(),
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->resetPassword($this->record->id, $data['password'], $data['password_confirmation']);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildAssignExamAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.assign_exam_btn'))
            ->modalHeading(__('admin.resources.user.actions.assign_exam_btn'))
            ->form([
                Forms\Components\Select::make('exam_id')
                    ->options((new ExamRepository())->listMatchExam($this->record->id)->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.assign_exam_exam_label'))->required(),
                Forms\Components\DateTimePicker::make('begin')->label(__('admin.resources.user.actions.assign_exam_begin_label')),
                Forms\Components\DateTimePicker::make('end')->label(__('admin.resources.user.actions.assign_exam_end_label'))
                    ->helperText(__('admin.resources.user.actions.assign_exam_end_help')),

            ])
            ->action(function ($data) {
                $examRep = new ExamRepository();
                try {
                    $examRep->assignToUser($this->record->id, $data['exam_id'], $data['begin'], $data['end']);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildGrantMedalAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.grant_medal_btn'))
            ->modalHeading(__('admin.resources.user.actions.grant_medal_btn'))
            ->form([
                Forms\Components\Select::make('medal_id')
                    ->options(Medal::query()->pluck('name', 'id'))
                    ->label(__('admin.resources.user.actions.grant_medal_medal_label'))
                    ->required(),

                Forms\Components\TextInput::make('duration')
                    ->label(__('admin.resources.user.actions.grant_medal_duration_label'))
                    ->helperText(__('admin.resources.user.actions.grant_medal_duration_help'))
                    ->integer(),

            ])
            ->action(function ($data) {
                $medalRep = new MedalRepository();
                try {
                    $medalRep->grantToUser($this->record->id, $data['medal_id'], $data['duration']);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildConfirmAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.confirm_btn'))
            ->modalHeading(__('admin.resources.user.actions.confirm_btn'))
            ->requiresConfirmation()
            ->action(function () {
                if (Auth::user()->class <= $this->record->class) {
                    send_admin_fail_notification("No permission!");
                    return;
                }
                $this->record->status = User::STATUS_CONFIRMED;
                $this->record->info= null;
                $this->record->save();
                $this->sendSuccessNotification();
            });
    }


    private function buildEnableDisableDownloadPrivilegesAction(): Actions\Action
    {
        return Actions\Action::make($this->record->downloadpos == 'yes' ? __('admin.resources.user.actions.disable_download_privileges_btn') : __('admin.resources.user.actions.enable_download_privileges_btn'))
//            ->modalHeading($this->record->enabled == 'yes' ? __('admin.resources.user.actions.disable_modal_title') : __('admin.resources.user.actions.enable_modal_title'))
            ->requiresConfirmation()
            ->action(function () {
                $userRep = $this->getRep();
                try {
                    $userRep->updateDownloadPrivileges(Auth::user(), $this->record->id, $this->record->downloadpos == 'yes' ? 'no' : 'yes');
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildManageRolesAction()
    {
        return Actions\Action::make('管理角色')
            ->label('管理角色')
            ->modalHeading('管理用户角色')
            ->form([
                Forms\Components\CheckboxList::make('roles')
                    ->label('角色')
                    ->options(\App\Models\Role::query()->orderBy('id')->pluck('display_name', 'id')->toArray())
                    ->default(fn () => $this->record->roles()->pluck('roles.id')->toArray())
                    ->columns(2)
                    ->helperText('选择用户拥有的角色（可多选）'),
            ])
            ->action(function ($data) {
                try {
                    // 同步用户角色
                    $roleIds = $data['roles'] ?? [];
                    $this->record->roles()->sync($roleIds);
                    
                    // 清除用户相关缓存，确保前端立即看到更新
                    $uid = $this->record->id;
                    try {
                        \Nexus\Database\NexusDB::cache_del("user_{$uid}_content");
                        \Nexus\Database\NexusDB::cache_del("user_{$uid}_roles");
                        \Nexus\Database\NexusDB::cache_del(\App\Models\Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX . $uid);
                        \Nexus\Database\NexusDB::cache_del("user_role_ids:$uid");
                        \Nexus\Database\NexusDB::cache_del("direct_permissions:$uid");
                    } catch (\Exception $e) {
                        // 缓存清除失败不影响主流程，只记录日志
                        do_log("Clear cache failed for user $uid: " . $e->getMessage(), 'error');
                    }
                    
                    $this->sendSuccessNotification('角色更新成功！');
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildManageSpecialPermissionsAction()
    {
        return Actions\Action::make('管理特殊权限')
            ->label('管理特殊权限')
            ->modalHeading('管理用户特殊权限')
            ->form([
                Forms\Components\CheckboxList::make('specialPermissions')
                    ->label('特殊权限')
                    ->options(\App\Models\SpecialPermission::query()
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->default(fn () => $this->record->specialPermissions()->pluck('special_permissions.id')->toArray())
                    ->columns(2)
                    ->helperText('选择用户拥有的特殊权限（可多选）'),
            ])
            ->action(function ($data) {
                try {
                    // 同步用户特殊权限
                    $permissionIds = $data['specialPermissions'] ?? [];
                    $this->record->specialPermissions()->sync($permissionIds);
                    
                    // 清除用户相关缓存，确保前端立即看到更新
                    $uid = $this->record->id;
                    try {
                        clear_user_cache($uid, $this->record->passkey);
                        // 清除权限相关的所有缓存
                        \Nexus\Database\NexusDB::cache_del("user_{$uid}_content");
                        \Nexus\Database\NexusDB::cache_del("user_{$uid}_roles");
                        \Nexus\Database\NexusDB::cache_del(\App\Models\Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX . $uid);
                        \Nexus\Database\NexusDB::cache_del("user_role_ids:$uid");
                        \Nexus\Database\NexusDB::cache_del("direct_permissions:$uid");
                    } catch (\Exception $e) {
                        // 缓存清除失败不影响主流程，只记录日志
                        do_log("Clear cache failed for user $uid: " . $e->getMessage(), 'error');
                    }
                    
                    $this->sendSuccessNotification('特殊权限更新成功！请让用户重新登录或等待几分钟后重试。');
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildGrantPropsAction()
    {
        return Actions\Action::make(__('admin.resources.user.actions.grant_prop_btn'))
            ->modalHeading(__('admin.resources.user.actions.grant_prop_btn'))
            ->form([
                Forms\Components\Select::make('meta_key')
                    ->options(UserMeta::listProps())
                    ->label(__('admin.resources.user.actions.grant_prop_form_prop'))->required(),
                Forms\Components\TextInput::make('duration')->label(__('admin.resources.user.actions.grant_prop_form_duration'))
                    ->helperText(__('admin.resources.user.actions.grant_prop_form_duration_help')),

            ])
            ->action(function ($data) {
                $rep = $this->getRep();
                try {
                    $rep->addMeta($this->record, $data, $data);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function buildDeleteAction(): Actions\DeleteAction
    {
        return Actions\DeleteAction::make()->using(function () {
            $this->getRep()->destroy($this->record->id);
            return redirect(self::$resource::getUrl('index'));
        });
    }

    public function getViewData(): array
    {
        return [
            'props' => $this->listUserProps(),
            'temporary_invite_count' => $this->countTemporaryInvite()
        ];
    }

    private function listUserProps(): array
    {
        $metaKeys = [
            UserMeta::META_KEY_PERSONALIZED_USERNAME,
            UserMeta::META_KEY_CHANGE_USERNAME,
        ];
        $metaList = $this->getRep()->listMetas($this->record->id, $metaKeys);
        $props = [];
        foreach ($metaList as $metaKey => $metas) {
            $meta = $metas->first();
            $text = sprintf('[%s]', $meta->metaKeyText);
            if ($meta->meta_key == UserMeta::META_KEY_PERSONALIZED_USERNAME) {
                $text .= sprintf('(%s)', $meta->getDeadlineText());
            }
            $props[] = "<div>{$text}</div>";
        }
        return $props;
    }

    private function countTemporaryInvite()
    {
        return Invite::query()->where('inviter', $this->record->id)
            ->where('invitee', '')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', Carbon::now())
            ->count();
    }

    private function buildChangeClassAction(): Actions\Action
    {
        return Actions\Action::make('change_class')
            ->label(__('admin.resources.user.actions.change_class_btn'))
            ->form([
                Forms\Components\Select::make('class')
                    ->options(User::listClass(User::CLASS_PEASANT, Auth::user()->class - 1))
                    ->default($this->record->class)
                    ->label(__('user.labels.class'))
                    ->required()
                    ->reactive()
                ,
                Forms\Components\Radio::make('vip_added')
                    ->options(self::getYesNoOptions('yes', 'no'))
                    ->default($this->record->vip_added)
                    ->label(__('user.labels.vip_added'))
                    ->helperText(__('user.labels.vip_added_help'))
                    ->hidden(fn (\Filament\Forms\Get $get) => $get('class') != User::CLASS_VIP)
                ,
                Forms\Components\DateTimePicker::make('vip_until')
                    ->default($this->record->vip_until)
                    ->label(__('user.labels.vip_until'))
                    ->helperText(__('user.labels.vip_until_help'))
                    ->hidden(fn (\Filament\Forms\Get $get) => $get('class') != User::CLASS_VIP)
                ,
                Forms\Components\TextInput::make('reason')
                    ->label(__('admin.resources.user.actions.enable_disable_reason'))
                    ->placeholder(__('admin.resources.user.actions.enable_disable_reason_placeholder'))
                ,
            ])
            ->action(function ($data) {
                $userRep = $this->getRep();
                try {
                    $userRep->changeClass(Auth::user(), $this->record, $data['class'], $data['reason'], $data);
                    $this->sendSuccessNotification();
                } catch (\Exception $exception) {
                    $this->sendFailNotification($exception->getMessage());
                }
            });
    }

    private function sendSuccessNotification(string $msg = ""): void
    {
        Notification::make()
            ->success()
            ->title($msg ?: "Success!")
            ->send()
        ;
    }

    private function sendFailNotification(string $msg = ""): void
    {
        Notification::make()
            ->danger()
            ->title($msg ?: "Fail!")
            ->send()
        ;
    }
}

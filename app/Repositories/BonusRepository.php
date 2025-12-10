<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Models\UserMedal;
use App\Models\UserMeta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    // 兑换上传流量
    public function consumeToExchangeUpload(int $uid, int $bytes, float $cost): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = $cost;
        NexusDB::transaction(function () use ($user, $bytes, $requireBonus) {
            $comment = nexus_trans('bonus.comment_exchange_upload', [
                'bonus' => $requireBonus,
                'size' => mksize($bytes),
            ], $user->locale);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $comment);
            User::query()->where('id', $user->id)->increment('uploaded', $bytes);
        });
        return true;
    }

    // 兑换下载流量
    public function consumeToExchangeDownload(int $uid, int $bytes, float $cost): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = $cost;
        NexusDB::transaction(function () use ($user, $bytes, $requireBonus) {
            $comment = nexus_trans('bonus.comment_exchange_download', [
                'bonus' => $requireBonus,
                'size' => mksize($bytes),
            ], $user->locale);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $comment);
            User::query()->where('id', $user->id)->increment('downloaded', $bytes);
        });
        return true;
    }

    // 购买邀请
    public function consumeToBuyInvite(int $uid, float $cost): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = $cost;
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_invite', [
                'bonus' => $requireBonus,
            ], $user->locale);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_INVITE, $comment);
            User::query()->where('id', $user->id)->increment('invites', 1);
        });
        return true;
    }

    // 赠送魔力值
    public function consumeToGiftBonus(int $uid, string $toUsername, int $amount, string $message = ''): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('赠送数量需为正整数');
        }
        $user = User::query()->findOrFail($uid);
        $toUser = User::query()->where('username', $toUsername)->first();
        if (!$toUser) {
            throw new \InvalidArgumentException('接收者不存在');
        }
        if ($toUser->id === $user->id) {
            throw new \InvalidArgumentException('不能给自己赠送');
        }
        $requireBonus = $amount;
        $msg = $message ? mb_substr($message, 0, 100) : '';

        NexusDB::transaction(function () use ($user, $toUser, $requireBonus, $msg) {
            $comment = nexus_trans('bonus.comment_gift_to_someone', [
                'bonus' => $requireBonus,
                'to_username' => $toUser->username,
            ], $user->locale);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $comment);

            $toUser->increment('seedbonus', $requireBonus);
            BonusLogs::add(
                $toUser->id,
                $toUser->seedbonus - $requireBonus,
                $requireBonus,
                $toUser->seedbonus,
                nexus_trans('bonus.comment_receive_gift', [
                    'bonus' => $requireBonus,
                    'from_username' => $user->username,
                ], $toUser->locale),
                BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT
            );

            // 站内信提示
            Message::add([
                'sender' => 0,
                'receiver' => $toUser->id,
                'subject' => nexus_trans('message.receive_gift.subject', [], $toUser->locale),
                'msg' => nexus_trans('message.receive_gift.body', [
                    'username' => $user->username,
                    'bonus' => $requireBonus,
                    'message' => $msg ?: nexus_trans('label.none', [], $toUser->locale),
                ], $toUser->locale),
                'added' => now(),
            ]);
        });

        return true;
    }

    // 慈善捐赠
    public function consumeToCharityGiving(int $uid, int $bonusAmount, float $ratioThreshold = 0.3): bool
    {
        if ($bonusAmount < 1000 || $bonusAmount > 50000) {
            throw new \InvalidArgumentException('捐赠数量需在 1000 - 50000 之间');
        }
        if ($ratioThreshold < 0.1 || $ratioThreshold > 0.8) {
            throw new \InvalidArgumentException('分享率阈值需在 0.1 - 0.8 之间');
        }
        $user = User::query()->findOrFail($uid);
        $requireBonus = $bonusAmount;

        NexusDB::transaction(function () use ($user, $requireBonus, $ratioThreshold) {
            $comment = nexus_trans('bonus.comment_charity_giving', [
                'bonus' => $requireBonus,
                'ratio' => $ratioThreshold,
            ], $user->locale);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $comment);
            // 这里不直接分配对象用户，由原有业务规则去匹配低分享率用户（与旧逻辑保持一致）
        });

        return true;
    }

    // 自定义头衔
    public function consumeToBuyCustomTitle(int $uid, string $title, float $cost): bool
    {
        $title = trim(mb_substr($title, 0, 30));
        if ($title === '') {
            throw new \InvalidArgumentException('头衔不能为空');
        }
        $user = User::query()->findOrFail($uid);
        $oldTitle = trim((string)($user->title ?? ''));
        $requireBonus = $cost;

        NexusDB::transaction(function () use ($user, $requireBonus, $oldTitle, $title) {
            $comment = sprintf("%s Points for custom title. Old title is %s and new title is %s", $requireBonus, htmlspecialchars($oldTitle), $title);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $comment, ['title' => $title]);
            User::query()->where('id', $user->id)->update(['title' => $title]);
        });

        return true;
    }

    // 购买无广告
    public function consumeToBuyNoAd(int $uid, float $cost, int $durationSeconds): bool
    {
        if ($durationSeconds <= 0) {
            throw new \InvalidArgumentException('无广告时长必须大于0');
        }
        $user = User::query()->findOrFail($uid);
        $requireBonus = $cost;
        $noaduntil = date("Y-m-d H:i:s", time() + $durationSeconds);
        $days = round($durationSeconds / 86400, 1);

        NexusDB::transaction(function () use ($user, $requireBonus, $noaduntil, $days) {
            $comment = sprintf("%s Points for %s days without ads", $requireBonus, $days);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_NO_AD, $comment, [
                'noad' => 'yes',
                'noaduntil' => $noaduntil,
            ]);
        });

        return true;
    }

    // 购买VIP身份
    public function consumeToBuyVip(int $uid, float $cost): bool
    {
        $user = User::query()->findOrFail($uid);
        
        // 检查用户等级是否已经是VIP或更高
        if ($user->class >= 10) { // UC_VIP = 10
            throw new \InvalidArgumentException('您的等级已经是VIP或更高，无需重复购买');
        }
        
        $requireBonus = $cost;
        // VIP有效期为28天
        $vip_until = date("Y-m-d H:i:s", time() + 28 * 86400);

        NexusDB::transaction(function () use ($user, $requireBonus, $vip_until) {
            $comment = sprintf("%s Points for 1 month VIP Status", $requireBonus);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_VIP, $comment, [
                'class' => 10, // UC_VIP
                'vip_added' => 'yes',
                'vip_until' => $vip_until,
            ]);
        });

        return true;
    }

    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (!HitAndRun::getIsEnabled()) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        if ($hitAndRun->status == HitAndRun::STATUS_PARDONED) {
            throw new \LogicException("H&R: $hitAndRunId already pardoned.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = nexus_trans('hr.bonus_cancel_comment', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");

            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN, "$comment(H&R ID: {$hitAndRun->id})");

            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => NexusDB::raw("if(comment = '', '$comment', concat_ws('\n', '$comment', comment))"),
            ]);
        });

        return true;

    }


    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail($uid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $requireBonus = $medal->price;
        NexusDB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_MEDAL, "$comment(medal ID: {$medal->id})");
            $medalRep = new MedalRepository();
            $medalRep->userAttachMedal($user, $medal);
            if ($medal->inventory !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory')
                ;
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) inventory affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    public function consumeToGiftMedal($uid, $medalId, $toUid): bool
    {
        $user = User::query()->findOrFail($uid);
        $toUser = User::query()->findOrFail($toUid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $toUser->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $toUid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $giftFee = $medal->price * ($medal->gift_fee_factor ?? 0);
        $requireBonus = $medal->price + $giftFee;
        NexusDB::transaction(function () use ($user, $toUser, $medal, $requireBonus, $giftFee) {
            $comment = nexus_trans('bonus.comment_gift_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
                'to_username' => $toUser->username,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_GIFT_MEDAL, "$comment(medal ID: {$medal->id})");

            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays((int)$medal->duration)->toDateTimeString();
            }
            $msg = [
                'sender' => 0,
                'receiver' => $toUser->id,
                'subject' => nexus_trans('message.receive_medal.subject', [], $toUser->locale),
                'msg' => nexus_trans('message.receive_medal.body', [
                    'username' => $user->username,
                    'cost_bonus' => $requireBonus,
                    'medal_name' => $medal->name,
                    'price' => $medal->price,
                    'gift_fee_total' => $giftFee,
                    'gift_fee_factor' => $medal->gift_fee_factor ?? 0,
                    'expire_at' => $expireAt ?? nexus_trans('label.permanent'),
                    'bonus_addition_factor' => $medal->bonus_addition_factor ?? 0,
                ], $toUser->locale),
                'added' => now()
            ];
            Message::add($msg);
            $toUser->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);
            if ($medal->inventory !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory')
                ;
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) inventory affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    public function consumeToBuyAttendanceCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyAttendanceCard();
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_attendance_card', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD, $comment);
            User::query()->where('id', $user->id)->increment('attendance_card');
        });

        return true;

    }


    public function consumeToBuyTemporaryInvite($uid, $count = 1): bool
    {
        $requireBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($requireBonus <= 0) {
            throw new \RuntimeException("Temporary invite require bonus <= 0 !");
        }
        $user = User::query()->findOrFail($uid);
        $toolRep = new ToolRepository();
        $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
        NexusDB::transaction(function () use ($user, $requireBonus, $hashArr) {
            $comment = nexus_trans('bonus.comment_buy_temporary_invite', [
                'bonus' => $requireBonus,
                'count' => count($hashArr)
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE, $comment);
            $invites = [];
            foreach ($hashArr as $hash) {
                $invites[] = [
                    'inviter' => $user->id,
                    'invitee' => '',
                    'hash' => $hash,
                    'valid' => 0,
                    'expired_at' => Carbon::now()->addDays(Invite::TEMPORARY_INVITE_VALID_DAYS),
                    'created_at' => Carbon::now(),
                ];
            }
            Invite::query()->insert($invites);
        });

        return true;

    }

    public function consumeToBuyRainbowId($uid, $duration = 30): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyRainbowId();
        NexusDB::transaction(function () use ($user, $requireBonus, $duration) {
            $comment = nexus_trans('bonus.comment_buy_rainbow_id', [
                'bonus' => $requireBonus,
                'duration' => $duration,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
                'duration' => $duration,
            ];
            $userRep = new UserRepository();
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    public function consumeToBuyChangeUsernameCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyChangeUsernameCard();
        if (UserMeta::query()->where('uid', $uid)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
            throw new NexusException("user already has change username card");
        }
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_change_username_card', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_CHANGE_USERNAME,
            ];
            $userRep = new UserRepository();
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    public function consumeToBuyTorrent($uid, $torrentId, $channel = 'Web'): TorrentBuyLog
    {
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $requireBonus = $torrent->price;
        return NexusDB::transaction(function () use ($requireBonus, $torrent, $channel, $uid) {
            $userQuery = User::query();
            if ($requireBonus > 0) {
                $userQuery = $userQuery->lockForUpdate();
            }
            $user = $userQuery->findOrFail($uid);
            $buyerLocale = $user->locale;
            $comment = nexus_trans('bonus.comment_buy_torrent', [
                'bonus' => $requireBonus,
                'torrent_id' => $torrent->id,
            ], $buyerLocale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_TORRENT, $comment);
            $buyLog = TorrentBuyLog::query()->create([
                'uid' => $user->id,
                'torrent_id' => $torrent->id,
                'price' => $requireBonus,
                'channel' => $channel,
            ]);
            //increment owner bonus
            $taxFactor = Setting::get('torrent.tax_factor');
            if (!is_numeric($taxFactor) || $taxFactor < 0 || $taxFactor > 1) {
                throw new \RuntimeException("Invalid tax_factor: $taxFactor");
            }
            $increaseBonus = $requireBonus * (1 - $taxFactor);
            $owner = $torrent->user;
            if ($owner->id) {
                $nowStr = now()->toDateTimeString();
                $businessType = BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED;
                $owner->increment('seedbonus', $increaseBonus);
                $comment = nexus_trans('bonus.comment_torrent_be_downloaded', [
                    'username' => $user->username,
                    'uid' => $user->id,
                ], $owner->locale);
                $bonusLog = [
                    'business_type' => $businessType,
                    'uid' => $owner->id,
                    'old_total_value' => $owner->seedbonus,
                    'value' => $increaseBonus,
                    'new_total_value' => bcadd($owner->seedbonus, $increaseBonus),
                    'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$businessType]['text'], $comment),
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                BonusLogs::query()->insert($bonusLog);
            }
            $buyTorrentSuccessMessage = [
                'sender' => 0,
                'receiver' => $user->id,
                'added' => now(),
                'subject' => nexus_trans("message.buy_torrent_success.subject", [], $buyerLocale),
                'msg' => nexus_trans("message.buy_torrent_success.body", [
                    'torrent_name' => $torrent->name,
                    'bonus' => $requireBonus,
                    'url' => sprintf('details.php?id=%s&hit=1', $torrent->id)
                ], $buyerLocale),
            ];
            Message::add($buyTorrentSuccessMessage);
            return $buyLog;
        });
    }

    /**
     * 增加用户魔力值
     */
    public function addUserBonus(int $userId, float $amount, int $logBusinessType, string $logComment = ''): bool
    {
        $user = User::query()->findOrFail($userId);
        $oldBonus = floatval($user->seedbonus);
        $newBonus = $oldBonus + $amount;

        NexusDB::transaction(function () use ($user, $amount, $oldBonus, $newBonus, $logBusinessType, $logComment) {
            BonusLogs::add($user->id, $oldBonus, $amount, $newBonus, $logComment, $logBusinessType);
            User::query()->where('id', $user->id)->update(['seedbonus' => $newBonus]);
        });

        return true;
    }

    /**
     * 购买火星老板卡（库存+1，扣魔力值）
     */
    public function consumeToBuyMarsOwnerCard(int $uid, float $cost): bool
    {
        $user = User::query()->findOrFail($uid);
        $this->consumeUserBonus($user, $cost, BonusLogs::BUSINESS_TYPE_BUY_TORRENT, "购买火星老板卡");
        User::query()->where('id', $user->id)->increment('mars_owner_card');
        return true;
    }

    public function consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment = '', array $userUpdates = [])
    {
        if (!isset(BonusLogs::$businessTypes[$logBusinessType])) {
            throw new \InvalidArgumentException("Invalid logBusinessType: $logBusinessType");
        }
        if (isset($userUpdates['seedbonus']) || isset($userUpdates['bonuscomment']) || isset($userUpdates['modcomment'])) {
            throw new \InvalidArgumentException("Not support update seedbonus or bonuscomment or modcomment");
        }
        if ($requireBonus <= 0) {
            return;
        }
        $user = $this->getUser($user);
        if ($user->seedbonus < $requireBonus) {
            do_log("user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: $requireBonus", 'error');
            throw new \LogicException("User bonus not enough.");
        }
        NexusDB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logComment, $userUpdates) {
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub($oldUserBonus, $requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            do_log($log);
            $userUpdates['seedbonus'] = $newUserBonus;
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                do_log("update user seedbonus affected rows: ".$affectedRows." != 1, query: " . last_query(), 'error');
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $nowStr = now()->toDateTimeString();
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$logBusinessType]['text'], $logComment),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            BonusLogs::query()->insert($bonusLog);
            do_log("bonusLog: " . nexus_json_encode($bonusLog));
            clear_user_cache($user->id, $user->passkey);
        });
    }


}

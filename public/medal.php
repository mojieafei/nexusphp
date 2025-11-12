<?php

use App\Models\Medal;
use App\Models\MedalSeries;
use App\Models\User;
use App\Models\UserMedalSeriesClaim;
use Illuminate\Support\Collection;

require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$title = nexus_trans('medal.label');
/** @var User $user */
$user = User::query()->findOrFail($CURUSER['id']);
$userMedals = $user->valid_medals->keyBy('id');
$ownedMedalIds = $userMedals->keys();
$searchRaw = trim($_GET['q'] ?? '');
$searchKeyword = mb_strtolower($searchRaw);

$seriesCollection = MedalSeries::query()
    ->with(['medals' => function ($query) {
        $query->where('display_on_medal_page', 1)
            ->orderBy('series_position', 'desc')
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc');
    }])
    ->orderBy('priority', 'desc')
    ->orderBy('id', 'desc')
    ->get();

$otherMedals = Medal::query()
    ->where('display_on_medal_page', 1)
    ->whereNull('series_id')
    ->orderBy('priority', 'desc')
    ->orderBy('id', 'desc')
    ->get();

$seriesClaims = UserMedalSeriesClaim::query()
    ->where('user_id', $user->id)
    ->get()
    ->groupBy('series_id');

$seriesBlocks = [];

foreach ($seriesCollection as $series) {
    $medals = $series->orderedMedals();
    if ($searchKeyword !== '') {
        $medals = $medals->filter(function (Medal $medal) use ($searchKeyword) {
            return str_contains(mb_strtolower($medal->name), $searchKeyword);
        })->values();
    }
    if ($medals->isEmpty()) {
        continue;
    }

    $seriesClaimState = $series->evaluateClaimState(
        $ownedMedalIds,
        $seriesClaims->get($series->id, collect())
    );

    $seriesBlocks[] = [
        'id' => $series->id,
        'name' => $series->name,
        'description' => $series->description,
        'cover_image' => $series->cover_image,
        'banner_image' => $series->banner_image,
        'reward_title' => $series->reward_title,
        'reward_description' => $series->reward_description,
        'reward_amount' => $series->reward_amount,
        'reward_currency_label' => nexus_trans('medal-series.reward_currency.' . $series->reward_currency),
        'reward_interval_label' => $series->reward_interval_label,
        'bonus_factor' => $series->bonus_addition_factor,
        'bonus_description' => $series->bonus_addition_description,
        'claim_state' => $seriesClaimState,
        'reward_active' => $series->isRewardActive(),
        'medals' => $medals,
        'is_other' => false,
    ];
}

$otherMedalList = $otherMedals;
if ($searchKeyword !== '') {
    $otherMedalList = $otherMedals->filter(function (Medal $medal) use ($searchKeyword) {
        return str_contains(mb_strtolower($medal->name), $searchKeyword);
    })->values();
}

if ($otherMedalList->isNotEmpty()) {
    $seriesBlocks[] = [
        'id' => null,
        'name' => nexus_trans('medal-series.frontend.others_series'),
        'description' => '',
        'cover_image' => null,
        'banner_image' => null,
        'reward_title' => null,
        'reward_description' => null,
        'reward_amount' => 0,
        'reward_currency_label' => '',
        'reward_interval_label' => '',
        'bonus_factor' => 0,
        'bonus_description' => null,
        'claim_state' => [
            'status' => 'inactive',
            'claimable' => false,
            'owned_count' => $otherMedalList->pluck('id')->intersect($ownedMedalIds)->count(),
            'total_count' => $otherMedalList->count(),
            'remaining_claims' => 0,
            'period_key' => null,
            'latest_claim_at' => null,
            'cooldown_ready_at' => null,
        ],
        'reward_active' => false,
        'medals' => $otherMedalList,
        'is_other' => true,
    ];
}

$hasResults = !empty($seriesBlocks);

stdhead($title);
begin_main_frame();
?>
<style>
    .medal-series-container {
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-bottom: 30px;
    }
    .medal-series-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 24px;
        position: relative;
        overflow: hidden;
    }
    .medal-series-card.is-other {
        background: rgba(255, 255, 255, 0.03);
    }
    .medal-series-header {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
        margin-bottom: 20px;
    }
    .medal-series-cover {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        overflow: hidden;
        background: rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .medal-series-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .medal-series-info {
        flex: 1;
        min-width: 260px;
    }
    .medal-series-info h2 {
        font-size: 24px;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .medal-series-description {
        color: rgba(255,255,255,0.7);
        margin-bottom: 8px;
    }
    .medal-series-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        color: rgba(255,255,255,0.85);
        font-size: 14px;
    }
    .medal-series-stats span {
        background: rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 6px 12px;
    }
    .medal-reward-box {
        background: rgba(255, 215, 0, 0.08);
        border: 1px solid rgba(255, 215, 0, 0.35);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }
    .medal-reward-box.inactive {
        opacity: 0.6;
    }
    .medal-reward-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 8px;
        color: #ffd700;
    }
    .medal-reward-body {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
    }
    .medal-reward-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .series-claim-btn {
        background: linear-gradient(135deg, #ffd700, #ffb347);
        border: none;
        border-radius: 8px;
        padding: 10px 18px;
        font-weight: bold;
        cursor: pointer;
        color: #1a1a1a;
        transition: all 0.2s ease;
    }
    .series-claim-btn[disabled] {
        cursor: not-allowed;
        opacity: 0.6;
    }
    .medal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    .medal-card {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .medal-card.owned {
        border-color: rgba(78, 205, 196, 0.6);
        background: rgba(78, 205, 196, 0.1);
    }
    .medal-card.locked {
        opacity: 0.75;
    }
    .medal-card.locked img {
        filter: grayscale(100%);
    }
    .medal-card-header {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    .medal-card-header img {
        width: 64px;
        height: 64px;
        object-fit: contain;
    }
    .medal-card-header h3 {
        font-size: 18px;
        margin: 0 0 4px;
    }
    .medal-card-meta {
        display: grid;
        gap: 4px;
        font-size: 12px;
        color: rgba(255,255,255,0.6);
    }
    .medal-card-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .medal-card-actions input[type="button"] {
        padding: 8px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: bold;
    }
    .medal-card-actions input[type="button"].buy {
        background: #4ecdc4;
        color: #0f172a;
    }
    .medal-card-actions input[type="button"].gift {
        background: #3b82f6;
        color: #fff;
    }
    .medal-card-actions input[type="button"][disabled] {
        background: rgba(255,255,255,0.2);
        color: rgba(255,255,255,0.6);
        cursor: not-allowed;
    }
    .medal-search-form {
        margin-bottom: 24px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .medal-search-form input[type="text"] {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.05);
        color: #fff;
    }
    .medal-empty-result {
        text-align: center;
        padding: 60px 20px;
        color: rgba(255,255,255,0.6);
    }
    @media (max-width: 768px) {
        .medal-series-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .medal-series-cover {
            width: 100px;
            height: 100px;
        }
    }
</style>

<div class="medal-search-form">
    <form method="get" action="medal.php" style="display:flex;gap:8px;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($searchRaw); ?>" placeholder="<?php echo nexus_trans('label.search'); ?>">
        <input type="submit" value="<?php echo nexus_trans('label.submit'); ?>">
        <button type="button" onclick="window.location='medal.php'"><?php echo nexus_trans('label.reset'); ?></button>
    </form>
</div>

<div class="medal-series-container">
    <?php if (!$hasResults): ?>
        <div class="medal-empty-result"><?php echo nexus_trans('label.no_results'); ?></div>
    <?php else: ?>
        <?php foreach ($seriesBlocks as $block): ?>
            <?php
            /** @var Collection|Medal[] $medals */
            $medals = $block['medals'];
            $claimState = $block['claim_state'];
            $ownedCount = $claimState['owned_count'] ?? $medals->pluck('id')->intersect($ownedMedalIds)->count();
            $totalCount = $claimState['total_count'] ?? $medals->count();
            $seriesTitle = htmlspecialchars($block['name']);
            $seriesDescription = htmlspecialchars($block['description'] ?? '');
            $rewardTitle = htmlspecialchars($block['reward_title'] ?? nexus_trans('medal-series.frontend.reward_default_title'));
            $rewardDescription = htmlspecialchars($block['reward_description'] ?? '');
            $bonusDescription = htmlspecialchars($block['bonus_description'] ?? '');
            $bonusFactorText = $block['bonus_factor'] ? sprintf('+%s%%', number_format($block['bonus_factor'] * 100, 2)) : null;
            $rewardAmountText = $block['reward_amount'] > 0 ? sprintf('%s %s', number_format($block['reward_amount'], 1), $block['reward_currency_label']) : null;
            $claimStatusLabel = nexus_trans('medal-series.claim_status.' . ($claimState['status'] ?? 'inactive'));
            $remainingText = $claimState['remaining_claims'] > 0
                ? nexus_trans('medal-series.frontend.reward_limit_remaining', ['count' => $claimState['remaining_claims']])
                : '';
            $cooldownText = '';
            if (!empty($claimState['cooldown_ready_at'])) {
                $cooldownText = nexus_trans('medal-series.frontend.cooldown_until', [
                    'time' => format_datetime($claimState['cooldown_ready_at'])
                ]);
            }
            $canClaim = !empty($claimState['claimable']) && !$block['is_other'];
            ?>
            <section class="medal-series-card <?php echo $block['is_other'] ? 'is-other' : ''; ?>">
                <div class="medal-series-header">
                    <div class="medal-series-cover">
                        <?php if ($block['cover_image']): ?>
                            <img src="<?php echo htmlspecialchars($block['cover_image']); ?>" alt="<?php echo $seriesTitle; ?>" class="preview">
                        <?php else: ?>
                            <span>★</span>
                        <?php endif; ?>
                    </div>
                    <div class="medal-series-info">
                        <h2><?php echo $seriesTitle; ?></h2>
                        <?php if ($seriesDescription): ?>
                            <div class="medal-series-description"><?php echo nl2br($seriesDescription); ?></div>
                        <?php endif; ?>
                        <div class="medal-series-stats">
                            <span><?php echo nexus_trans('medal-series.frontend.collection_progress', ['owned' => $ownedCount, 'total' => $totalCount]); ?></span>
                            <?php if ($block['bonus_factor']): ?>
                                <span><?php echo nexus_trans('medal-series.fields.bonus_addition_factor'); ?>: <?php echo $bonusFactorText; ?></span>
                            <?php endif; ?>
                            <?php if ($bonusDescription): ?>
                                <span><?php echo $bonusDescription; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$block['is_other'] && ($block['reward_amount'] > 0 || $block['reward_active'])): ?>
                    <div class="medal-reward-box <?php echo $block['reward_active'] ? '' : 'inactive'; ?>">
                        <div class="medal-reward-title"><?php echo $rewardTitle; ?></div>
                        <div class="medal-reward-body">
                            <div>
                                <?php if ($rewardAmountText): ?>
                                    <div><?php echo $rewardAmountText; ?></div>
                                <?php endif; ?>
                                <div><?php echo $block['reward_interval_label']; ?></div>
                                <?php if ($rewardDescription): ?>
                                    <div><?php echo $rewardDescription; ?></div>
                                <?php endif; ?>
                                <div>
                                    <?php echo nexus_trans('medal-series.frontend.reward_status_prefix'); ?>
                                    <strong><?php echo $claimStatusLabel; ?></strong>
                                    <?php if ($remainingText): ?>
                                        <span> · <?php echo $remainingText; ?></span>
                                    <?php endif; ?>
                                    <?php if ($cooldownText): ?>
                                        <span> · <?php echo $cooldownText; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="medal-reward-actions">
                                <button
                                    class="series-claim-btn"
                                    data-series-id="<?php echo $block['id']; ?>"
                                    <?php echo $canClaim ? '' : 'disabled'; ?>
                                >
                                    <?php echo $canClaim
                                        ? nexus_trans('medal-series.frontend.claim_button')
                                        : nexus_trans('medal-series.frontend.claimed_button'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="medal-grid">
                    <?php foreach ($medals as $medal): ?>
                        <?php
                        $owned = $userMedals->has($medal->id);
                        $cardClass = $owned ? 'owned' : 'locked';
                        $bonusFactor = $medal->bonus_addition_factor ? sprintf('+%s%%', number_format($medal->bonus_addition_factor * 100, 2)) : null;
                        $buyDisabled = ' disabled';
                        $giftDisabled = ' disabled';
                        $buyClass = '';
                        $giftClass = '';
                        $buyBtnText = nexus_trans('medal.buy_btn');
                        $giftBtnText = nexus_trans('medal.gift_btn');
                        try {
                            $medal->checkCanBeBuy();
                            if ($owned) {
                                $buyBtnText = nexus_trans('medal.buy_already');
                            } elseif ($CURUSER['seedbonus'] >= $medal->price) {
                                $buyDisabled = '';
                                $buyClass = 'buy';
                            } else {
                                $buyBtnText = nexus_trans('medal.require_more_bonus');
                            }

                            $giftCost = $medal->price * (1 + ($medal->gift_fee_factor ?? 0));
                            if ($CURUSER['seedbonus'] >= $giftCost) {
                                $giftDisabled = '';
                                $giftClass = 'gift';
                            } else {
                                $giftBtnText = nexus_trans('medal.require_more_bonus');
                            }
                        } catch (\Throwable $exception) {
                            $message = $exception->getMessage();
                            $buyBtnText = $message;
                            $giftBtnText = $message;
                        }
                        ?>
                        <div class="medal-card <?php echo $cardClass; ?>">
                            <div class="medal-card-header">
                                <img src="<?php echo htmlspecialchars($medal->image_large); ?>" alt="<?php echo htmlspecialchars($medal->name); ?>" class="preview">
                                <div>
                                    <h3><?php echo htmlspecialchars($medal->name); ?></h3>
                                    <div class="medal-card-meta">
                                        <span><?php echo nexus_trans('medal.fields.price'); ?>: <?php echo number_format($medal->price); ?></span>
                                        <span><?php echo nexus_trans('medal.fields.duration'); ?>: <?php echo htmlspecialchars($medal->durationText); ?></span>
                                        <span><?php echo nexus_trans('medal.fields.inventory'); ?>: <?php echo htmlspecialchars($medal->inventoryText); ?></span>
                                        <span><?php echo nexus_trans('medal.fields.sale_begin_end_time'); ?>: <?php echo ($medal->sale_begin_time ?? nexus_trans('nexus.no_limit')) . ' ~ ' . ($medal->sale_end_time ?? nexus_trans('nexus.no_limit')); ?></span>
                                        <?php if ($bonusFactor): ?>
                                            <span><?php echo nexus_trans('medal.fields.bonus_addition_factor'); ?>: <?php echo $bonusFactor; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($medal->description)): ?>
                                <div style="font-size: 13px;color: rgba(255,255,255,0.75);">
                                    <?php echo nl2br(htmlspecialchars($medal->description)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="medal-card-actions">
                                <input type="button" class="<?php echo $buyClass; ?>" data-id="<?php echo $medal->id; ?>" value="<?php echo htmlspecialchars($buyBtnText); ?>"<?php echo $buyDisabled; ?>>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input type="number" class="uid" <?php echo $giftDisabled; ?> style="width: 70px" placeholder="UID">
                                    <input type="button" class="<?php echo $giftClass; ?>" data-id="<?php echo $medal->id; ?>" value="<?php echo htmlspecialchars($giftBtnText); ?>"<?php echo $giftDisabled; ?>>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$confirmBuyMsg = nexus_trans('medal.confirm_to_buy');
$confirmGiftMsg = nexus_trans('medal.confirm_to_gift');
$claimSuccess = nexus_trans('medal-series.frontend.claim_success');
$claimFailed = nexus_trans('medal-series.frontend.claim_failed');
$js = <<<JS
// 强制重置页面状态
try {
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.href);
    }
    if (window.jQuery && jQuery.fn && jQuery.fn.pjax) {
        jQuery(document).off('.pjax');
        jQuery('#pjax-container').removeData('pjax');
    }
} catch (e) {
    console.warn('页面状态重置异常:', e);
}

jQuery(document).on('click', '.buy', function (e) {
    e.preventDefault();
    let medalId = jQuery(this).attr('data-id');
    layer.confirm("{$confirmBuyMsg}", {
        btn: ['OK', 'Cancel']
    }, function (index) {
        layer.close(index);
        let params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        };
        jQuery.post('ajax.php', params, function(response) {
            if (response.ret != 0) {
                layer.alert(response.msg);
                return;
            }
            window.location.reload();
        }, 'json');
    }, function (index) {
        layer.close(index);
    });
});

jQuery(document).on('click', '.gift', function (e) {
    e.preventDefault();
    let medalId = jQuery(this).attr('data-id');
    let uid = jQuery(this).prev('.uid').val();
    if (!uid) {
        layer.alert('Require UID');
        return;
    }
    layer.confirm("{$confirmGiftMsg}" + uid + " ?", {
        btn: ['OK', 'Cancel']
    }, function (index) {
        layer.close(index);
        let params = {
            action: "giftMedal",
            params: {medal_id: medalId, uid: uid}
        };
        jQuery.post('ajax.php', params, function(response) {
            if (response.ret != 0) {
                layer.alert(response.msg);
                return;
            }
            window.location.reload();
        }, 'json');
    }, function (index) {
        layer.close(index);
    });
});

jQuery(document).on('click', '.series-claim-btn', function (e) {
    e.preventDefault();
    const btn = jQuery(this);
    if (btn.prop('disabled')) {
        return;
    }
    const seriesId = btn.data('series-id');
    if (!seriesId) {
        return;
    }
    btn.prop('disabled', true);
    jQuery.post('ajax.php', {action: 'medal_series_claim', series_id: seriesId}, function(response) {
        if (response.success) {
            layer.msg(response.message || "{$claimSuccess}", {time: 1500}, function () {
                window.location.reload();
            });
        } else {
            btn.prop('disabled', false);
            layer.alert(response.message || "{$claimFailed}");
        }
    }, 'json').fail(function(xhr) {
        btn.prop('disabled', false);
        layer.alert(xhr.responseJSON?.message || "{$claimFailed}");
    });
});
JS;
\Nexus\Nexus::js($js, 'footer', false);

end_main_frame();
stdfoot();


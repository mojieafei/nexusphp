<?php

use App\Models\Medal;
use App\Models\MedalSeries;
use App\Models\User;
use App\Models\UserMedalSeriesClaim;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$title = nexus_trans('medal.label');
/** @var User $user */
$user = User::query()->findOrFail($CURUSER['id']);
$userMedals = $user->valid_medals->keyBy('id');
$ownedMedalIds = $userMedals->keys();
$selectedSeriesId = isset($_GET['series_id']) ? trim($_GET['series_id']) : '';
$selectedSeriesId = $selectedSeriesId === '' ? 'all' : $selectedSeriesId;

$newArrivalLimit = 12;
$newArrivalMedals = Medal::query()
    ->where('display_on_medal_page', 1)
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->take($newArrivalLimit)
    ->get();

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

$medalOwnerCounts = [];
$seriesCompletionCounts = [];
if ($hasResults) {
    $allMedalIds = [];
    foreach ($seriesBlocks as $block) {
        if ($block['medals'] instanceof Collection) {
            $allMedalIds = array_merge($allMedalIds, $block['medals']->pluck('id')->all());
        }
    }
    $allMedalIds = array_values(array_unique(array_filter($allMedalIds)));
    $expireAtValue = date('Y-m-d H:i:s');
    if (function_exists('sqlesc')) {
        $expireAt = sqlesc($expireAtValue);
    } else {
        $expireAt = "'" . addslashes($expireAtValue) . "'";
    }
    if (!empty($allMedalIds)) {
        $idList = implode(',', array_map('intval', $allMedalIds));
        if ($idList !== '') {
            $sql = sprintf(
                "SELECT medal_id, COUNT(DISTINCT uid) AS total
                 FROM user_medals
                 WHERE medal_id IN (%s)
                   AND (expire_at IS NULL OR expire_at >= %s)
                 GROUP BY medal_id",
                $idList,
                $expireAt
            );
            $rows = NexusDB::select($sql);
            foreach ($rows as $row) {
                $medalOwnerCounts[(int)($row['medal_id'] ?? 0)] = (int)($row['total'] ?? 0);
            }
        }
    }
    foreach ($seriesBlocks as $block) {
        if (!empty($block['is_other']) || empty($block['id']) || !($block['medals'] instanceof Collection)) {
            continue;
        }
        $seriesMedalIds = array_map('intval', $block['medals']->pluck('id')->all());
        $seriesMedalIds = array_values(array_unique(array_filter($seriesMedalIds)));
        if (empty($seriesMedalIds)) {
            continue;
        }
        $seriesMedalIdList = implode(',', $seriesMedalIds);
        $requiredCount = count($seriesMedalIds);
        $sql = sprintf(
            "SELECT COUNT(*) AS total FROM (
                SELECT uid, COUNT(DISTINCT medal_id) AS cnt
                FROM user_medals
                WHERE medal_id IN (%s)
                  AND (expire_at IS NULL OR expire_at >= %s)
                GROUP BY uid
                HAVING cnt = %d
            ) AS tmp",
            $seriesMedalIdList,
            $expireAt,
            $requiredCount
        );
        $rows = NexusDB::select($sql);
        $seriesCompletionCounts[(int)$block['id']] = isset($rows[0]['total']) ? (int)$rows[0]['total'] : 0;
    }
}

stdhead($title);
begin_main_frame();
?>
<style>
    .medal-series-container {
        display: flex;
        flex-direction: column;
        gap: 32px;
        margin-bottom: 40px;
    }
    .medal-series-card {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        padding: 26px;
        background: linear-gradient(145deg, rgba(28, 36, 74, 0.9), rgba(15, 20, 42, 0.94));
        border: 1px solid rgba(148, 197, 255, 0.16);
        box-shadow: 0 18px 36px rgba(10, 24, 64, 0.42);
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .medal-series-banner {
        position: relative;
        border-radius: 18px;
        overflow: hidden;
        height: 160px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        box-shadow: inset 0 -60px 120px rgba(15, 23, 42, 0.7);
    }
    .medal-series-banner::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.1) 0%, rgba(15, 23, 42, 0.65) 75%, rgba(15, 23, 42, 0.85) 100%);
    }
    .medal-series-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(81, 140, 255, 0.25), transparent 55%);
        pointer-events: none;
        mix-blend-mode: screen;
    }
    .medal-series-card.is-other {
        background: linear-gradient(145deg, rgba(24, 32, 60, 0.92), rgba(14, 18, 40, 0.95));
        border-color: rgba(148, 163, 184, 0.2);
    }
    .medal-series-hero {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 140px) minmax(0, 1fr);
        gap: 24px;
        align-items: center;
    }
    .medal-series-cover {
        position: relative;
        width: 140px;
        height: 140px;
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(24, 31, 64, 0.65));
        box-shadow: 0 14px 32px rgba(8, 20, 60, 0.45);
        overflow: hidden;
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
        min-width: 240px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .medal-series-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 14px;
    }
    .medal-series-info h2 {
        margin: 0;
        font-size: 26px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .medal-series-chip {
        padding: 4px 12px;
        border-radius: 9999px;
        background: rgba(96, 165, 250, 0.18);
        color: rgba(191, 219, 254, 0.9);
        font-size: 13px;
    }
    .medal-series-description {
        color: rgba(226, 232, 240, 0.75);
        line-height: 1.55;
        font-size: 13px;
    }
    .medal-series-progress {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .medal-series-progress-label {
        font-size: 14px;
        color: rgba(221, 229, 239, 0.88);
    }
    .medal-series-progress-bar {
        position: relative;
        height: 10px;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.25);
        overflow: hidden;
    }
    .medal-series-progress-bar span {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, #60a5fa, #38bdf8);
    }
    .medal-series-metrics {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 12px;
        color: rgba(221, 229, 239, 0.88);
    }
    .medal-series-metrics span {
        padding: 5px 11px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .medal-reward-box {
        position: relative;
        z-index: 1;
        border-radius: 18px;
        padding: 18px 22px;
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 215, 0, 0.04));
        border: 1px solid rgba(255, 215, 0, 0.38);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .medal-reward-box.inactive {
        opacity: 0.6;
    }
    .medal-reward-title {
        font-size: 16px;
        font-weight: 700;
        color: #ffd700;
    }
    .medal-reward-body {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        justify-content: space-between;
        align-items: center;
        color: rgba(30, 41, 59, 0.85);
    }
    .medal-reward-body > div:first-child {
        display: flex;
        flex-direction: column;
        gap: 6px;
        color: inherit;
    }
    .medal-reward-body > div:first-child div {
        color: rgba(30, 41, 59, 0.76);
        font-size: 14px;
    }
    .medal-reward-body strong {
        color: rgba(17, 24, 39, 0.92);
    }
    .medal-reward-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .series-claim-btn {
        background: linear-gradient(120deg, #facc15, #f97316);
        border: none;
        border-radius: 999px;
        padding: 12px 26px;
        font-weight: 700;
        cursor: pointer;
        color: #1f2937;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 10px 30px rgba(251, 191, 36, 0.35);
    }
    .series-claim-btn:hover:not([disabled]) {
        transform: translateY(-1px);
        box-shadow: 0 12px 32px rgba(251, 146, 60, 0.45);
    }
    .series-claim-btn[disabled] {
        cursor: not-allowed;
        opacity: 0.6;
        box-shadow: none;
    }
    .medal-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 18px;
    }
    .medal-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 16px;
        border-radius: 18px;
        padding: 18px;
        background: linear-gradient(155deg, rgba(17, 24, 39, 0.9), rgba(30, 41, 59, 0.93));
        border: 1px solid rgba(148, 163, 184, 0.16);
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.42);
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        height: 460px;
    }
    .medal-card:hover {
        transform: translateY(-3px);
        border-color: rgba(96, 165, 250, 0.4);
        box-shadow: 0 22px 40px rgba(30, 64, 175, 0.4);
    }
    .medal-card.owned {
        border-color: rgba(78, 205, 196, 0.6);
        box-shadow: 0 24px 45px rgba(78, 205, 196, 0.35);
    }
    .medal-card.locked {
        opacity: 0.9;
    }
    .medal-card-image {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 16px;
        background: radial-gradient(circle at 50% 30%, rgba(148, 197, 255, 0.28), rgba(15, 23, 42, 0.78));
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .medal-card-image img {
        width: 72%;
        height: 72%;
        object-fit: contain;
        transition: transform 0.25s ease;
    }
    .medal-card:hover .medal-card-image img {
        transform: scale(1.04);
    }
    .medal-card-chip {
        position: absolute;
        top: 14px;
        left: 14px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(59, 130, 246, 0.75);
        color: #f8fafc;
        font-size: 12px;
        font-weight: 600;
    }
    .medal-card-badge {
        position: absolute;
        top: 14px;
        right: 14px;
        padding: 4px 10px;
        border-radius: 999px;
    background: rgba(78, 205, 196, 0.8);
        color: #022c22;
        font-size: 12px;
        font-weight: 600;
    }
.medal-card-badge.unowned {
    background: rgba(96, 165, 250, 0.8);
    color: #0b1a2b;
}
    .medal-card-body {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex: 1;
        overflow: hidden;
    }
    .medal-card-title {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: rgba(226, 232, 240, 0.95);
        letter-spacing: 0.01em;
    }
    .medal-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 12px;
        color: rgba(226, 232, 240, 0.78);
    }
    .medal-card-meta .medal-meta-item {
        flex: 1 1 calc(50% - 10px);
        min-width: 120px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .medal-card-meta .medal-meta-item span {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(148, 163, 184, 0.7);
    }
    .medal-card-meta .medal-meta-item strong {
        color: rgba(248, 250, 252, 0.92);
        font-weight: 600;
    }
    .medal-card-description {
        font-size: 12px;
        line-height: 1.55;
        color: rgba(226, 232, 240, 0.68);
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
    }
    .medal-card-actions {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .medal-card-actions input[type="button"] {
        padding: 9px 14px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 700;
        letter-spacing: 0.02em;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .medal-card-actions input[type="button"].buy {
        background: linear-gradient(135deg, #34d399, #22c55e);
        color: #022c22;
        box-shadow: 0 10px 24px rgba(34, 197, 94, 0.28);
    }
    .medal-card-actions input[type="button"].buy:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px rgba(16, 185, 129, 0.4);
    }
    .medal-card-actions input[type="button"].gift {
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        color: #f8fafc;
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.3);
    }
    .medal-card-actions input[type="button"].gift:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px rgba(37, 99, 235, 0.4);
    }
    .medal-card-actions input[type="button"][disabled] {
        background: rgba(148, 163, 184, 0.18);
        color: rgba(148, 163, 184, 0.7);
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }
    .medal-card-gift-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .medal-card-gift-row input[type="number"] {
        flex: 1;
        min-width: 0;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(15, 23, 42, 0.78);
        color: #f1f5f9;
    }
    .medal-card-owner-count {
        position: absolute;
        bottom: 14px;
        right: 14px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        color: rgba(226, 232, 240, 0.9);
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .medal-card-owner-count strong {
        font-size: 16px;
        font-weight: 700;
        color: #f8fafc;
    }
    .medal-new-section {
        margin-bottom: 40px;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .medal-new-header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
    }
    .medal-new-header h2 {
        margin: 0;
        font-size: 26px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .medal-new-header p {
        margin: 0;
        font-size: 13px;
        color: rgba(226, 232, 240, 0.72);
    }
    .medal-new-list {
        display: flex;
        gap: 18px;
        overflow-x: auto;
        padding-bottom: 6px;
    }
    .medal-new-card {
        position: relative;
        flex: 0 0 180px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        border-radius: 16px;
        padding: 16px;
        background: linear-gradient(160deg, rgba(17, 24, 39, 0.88), rgba(30, 41, 59, 0.9));
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.36);
        min-height: 220px;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .medal-new-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 36px rgba(37, 99, 235, 0.28);
    }
    .medal-new-card img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: contain;
        border-radius: 12px;
        background: radial-gradient(circle at 50% 35%, rgba(148, 197, 255, 0.3), rgba(15, 23, 42, 0.78));
    }
    .medal-new-card h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: rgba(226, 232, 240, 0.95);
        line-height: 1.3;
    }
    .medal-new-card .medal-new-meta {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 12px;
        color: rgba(226, 232, 240, 0.7);
    }
    .medal-new-card .medal-new-tag {
        position: absolute;
        top: 12px;
        left: 12px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(59, 130, 246, 0.8);
        color: rgba(248, 250, 252, 0.95);
    }
    .medal-new-card .medal-new-tag.owned {
        background: rgba(45, 212, 191, 0.8);
        color: #022c22;
    }
    .medal-new-card .medal-new-footer {
        margin-top: auto;
        font-size: 11px;
        color: rgba(148, 163, 184, 0.7);
    }
    .medal-series-filter {
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .medal-series-filter label {
        font-size: 14px;
        font-weight: 600;
        color: rgba(226, 232, 240, 0.9);
    }
    .medal-series-filter select {
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(15, 23, 42, 0.75);
        color: #f1f5f9;
        min-width: 280px;
        font-size: 14px;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .medal-series-filter select:hover {
        border-color: rgba(148, 163, 184, 0.4);
    }
    .medal-series-filter select:focus {
        outline: none;
        border-color: rgba(96, 165, 250, 0.6);
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
    }
    .medal-filter-empty {
        display: none;
        margin-bottom: 24px;
        padding: 14px 18px;
        border-radius: 12px;
        background: rgba(148, 163, 184, 0.12);
        border: 1px solid rgba(148, 163, 184, 0.2);
        color: rgba(226, 232, 240, 0.75);
        font-size: 13px;
    }
    .medal-empty-result {
        text-align: center;
        padding: 70px 20px;
        color: rgba(226, 232, 240, 0.7);
    }
    @media (max-width: 992px) {
        .medal-series-hero {
            grid-template-columns: 1fr;
        }
        .medal-series-cover {
            width: 130px;
            height: 130px;
        }
        .medal-series-info {
            align-items: flex-start;
        }
    }
    @media (max-width: 768px) {
        .medal-series-card {
            padding: 24px;
        }
        .medal-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
        .medal-card-meta {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($newArrivalMedals->isNotEmpty()): ?>
<div class="medal-new-section">
    <div class="medal-new-header">
        <h2>最近上新</h2>
        <p>最新上架的勋章，抢先收藏。</p>
    </div>
    <div class="medal-new-list">
        <?php foreach ($newArrivalMedals as $arrivalMedal): ?>
            <?php
            $arrivalOwned = $ownedMedalIds->contains($arrivalMedal->id);
            $arrivalTagClass = $arrivalOwned ? 'owned' : '';
            $arrivalTagText = $arrivalOwned ? '已拥有' : '未拥有';
            $arrivalPrice = number_format($arrivalMedal->price ?? 0);
            $arrivalDuration = $arrivalMedal->duration > 0 ? $arrivalMedal->duration : nexus_trans('label.permanent');
            $arrivalDate = $arrivalMedal->created_at ? $arrivalMedal->created_at->format('Y-m-d') : '';
            ?>
            <div class="medal-new-card">
                <span class="medal-new-tag <?php echo $arrivalTagClass; ?>"><?php echo $arrivalTagText; ?></span>
                <img src="<?php echo htmlspecialchars($arrivalMedal->image_large); ?>" alt="<?php echo htmlspecialchars($arrivalMedal->name); ?>" class="preview">
                <h4><?php echo htmlspecialchars($arrivalMedal->name); ?></h4>
                <div class="medal-new-meta">
                    <div>价格：<?php echo $arrivalPrice; ?></div>
                    <div>有效期：<?php echo htmlspecialchars($arrivalDuration); ?></div>
                </div>
                <?php if ($arrivalDate): ?>
                    <div class="medal-new-footer">上新：<?php echo $arrivalDate; ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="medal-series-filter">
    <label for="series_id">选择系列：</label>
    <select name="series_id" id="series_id">
        <option value="all" <?php echo $selectedSeriesId === 'all' ? 'selected' : ''; ?>>所有系列</option>
        <?php foreach ($seriesCollection as $series): ?>
            <?php if ($series->medals->where('display_on_medal_page', 1)->isNotEmpty()): ?>
                <option value="<?php echo $series->id; ?>" <?php echo $selectedSeriesId === (string)$series->id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($series->name); ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($otherMedals->isNotEmpty()): ?>
            <option value="other" <?php echo $selectedSeriesId === 'other' ? 'selected' : ''; ?>>
                <?php echo nexus_trans('medal-series.frontend.others_series'); ?>
            </option>
        <?php endif; ?>
    </select>
</div>
<div class="medal-filter-empty">暂无符合条件的勋章系列。</div>

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
                ? sprintf('剩余领取次数：%d 次', (int)$claimState['remaining_claims'])
                : '';
            $cooldownText = '';
            if (!empty($claimState['cooldown_ready_at'])) {
                $cooldownText = nexus_trans('medal-series.frontend.cooldown_until', [
                    'time' => format_datetime($claimState['cooldown_ready_at'])
                ]);
            }
            $canClaim = !empty($claimState['claimable']) && !$block['is_other'];
            $progressPercent = $totalCount > 0 ? min(100, round(($ownedCount / $totalCount) * 100)) : 0;
            $claimStatus = $claimState['status'] ?? 'inactive';
            // 根据状态决定按钮文字
            $buttonText = nexus_trans('medal-series.frontend.claim_button');
            if ($canClaim) {
                $buttonText = nexus_trans('medal-series.frontend.claim_button');
            } else {
                // 根据具体状态显示不同文字
                switch ($claimStatus) {
                    case 'ready':
                        $buttonText = nexus_trans('medal-series.frontend.claimed_button');
                        break;
                    case 'not_complete':
                        $buttonText = nexus_trans('medal-series.claim_status.not_complete');
                        break;
                    case 'limit_reached':
                        $buttonText = nexus_trans('medal-series.claim_status.limit_reached');
                        break;
                    case 'cooldown':
                        $buttonText = nexus_trans('medal-series.claim_status.cooldown');
                        break;
                    case 'inactive':
                        $buttonText = nexus_trans('medal-series.claim_status.inactive');
                        break;
                    default:
                        $buttonText = nexus_trans('medal-series.frontend.claimed_button');
                }
            }
            ?>
            <section class="medal-series-card <?php echo $block['is_other'] ? 'is-other' : ''; ?>" data-series-id="<?php echo $block['is_other'] ? 'other' : $block['id']; ?>">
                <?php if (!empty($block['banner_image'])): ?>
                    <div class="medal-series-banner" style="background-image: url('<?php echo htmlspecialchars($block['banner_image']); ?>');"></div>
                <?php endif; ?>
                <div class="medal-series-hero">
                    <div class="medal-series-cover">
                        <?php if ($block['cover_image']): ?>
                            <img src="<?php echo htmlspecialchars($block['cover_image']); ?>" alt="<?php echo $seriesTitle; ?>" class="preview">
                        <?php else: ?>
                            <span>★</span>
                        <?php endif; ?>
                    </div>
                    <div class="medal-series-info">
                        <div class="medal-series-title-row">
                            <h2><?php echo $seriesTitle; ?></h2>
                            <?php if ($bonusFactorText): ?>
                                <span class="medal-series-chip"><?php echo $bonusFactorText; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($seriesDescription): ?>
                            <div class="medal-series-description"><?php echo nl2br($seriesDescription); ?></div>
                        <?php endif; ?>
                        <div class="medal-series-progress">
                            <div class="medal-series-progress-label">
                                <?php echo nexus_trans('medal-series.frontend.collection_progress', ['owned' => $ownedCount, 'total' => $totalCount]); ?>
                            </div>
                            <div class="medal-series-progress-bar">
                                <span style="width: <?php echo $progressPercent; ?>%;"></span>
                            </div>
                        </div>
                        <div class="medal-series-metrics">
                            <?php if ($bonusDescription): ?>
                                <span><?php echo $bonusDescription; ?></span>
                            <?php endif; ?>
                            <?php if ($rewardAmountText): ?>
                                <span><?php echo htmlspecialchars($rewardAmountText); ?></span>
                            <?php endif; ?>
                            <?php if (!$block['is_other']): ?>
                                <span>已集齐人数：<?php echo number_format($seriesCompletionCounts[$block['id']] ?? 0); ?> 人</span>
                            <?php endif; ?>
                            <span><?php echo nexus_trans('medal-series.frontend.reward_status_prefix'); ?> <?php echo $claimStatusLabel; ?></span>
                            <?php if ($remainingText): ?>
                                <span><?php echo htmlspecialchars($remainingText); ?></span>
                            <?php endif; ?>
                            <?php if ($cooldownText): ?>
                                <span><?php echo htmlspecialchars($cooldownText); ?></span>
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
                                    <?php echo $buttonText; ?>
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
                        $ownerCount = $medalOwnerCounts[$medal->id] ?? 0;
                        $saleWindow = ($medal->sale_begin_time ? format_datetime($medal->sale_begin_time) : nexus_trans('nexus.no_limit')) .
                            ' ~ ' .
                            ($medal->sale_end_time ? format_datetime($medal->sale_end_time) : nexus_trans('nexus.no_limit'));
                        ?>
                        <div class="medal-card <?php echo $cardClass; ?>">
                            <div class="medal-card-image">
                                <img src="<?php echo htmlspecialchars($medal->image_large); ?>" alt="<?php echo htmlspecialchars($medal->name); ?>" class="preview">
                                <?php if ($bonusFactor): ?>
                                    <span class="medal-card-chip"><?php echo $bonusFactor; ?></span>
                                <?php endif; ?>
                                <?php if ($owned): ?>
                                    <span class="medal-card-badge">已拥有</span>
                                <?php else: ?>
                                    <span class="medal-card-badge unowned">未拥有</span>
                                <?php endif; ?>
                                <span class="medal-card-owner-count"><strong><?php echo number_format($ownerCount); ?></strong> 人拥有</span>
                            </div>
                            <div class="medal-card-body">
                                <h3 class="medal-card-title"><?php echo htmlspecialchars($medal->name); ?></h3>
                                <div class="medal-card-meta">
                                    <div class="medal-meta-item">
                                        <span><?php echo nexus_trans('medal.fields.price'); ?></span>
                                        <strong><?php echo number_format($medal->price); ?></strong>
                                    </div>
                                    <div class="medal-meta-item">
                                        <span><?php echo nexus_trans('medal.fields.duration'); ?></span>
                                        <strong><?php echo htmlspecialchars($medal->durationText); ?></strong>
                                    </div>
                                    <div class="medal-meta-item">
                                        <span><?php echo nexus_trans('medal.fields.inventory'); ?></span>
                                        <strong><?php echo htmlspecialchars($medal->inventoryText); ?></strong>
                                    </div>
                                    <div class="medal-meta-item">
                                        <span><?php echo nexus_trans('medal.fields.users_count'); ?></span>
                                        <strong><?php echo number_format($ownerCount); ?></strong>
                                    </div>
                                    <div class="medal-meta-item">
                                        <span><?php echo nexus_trans('medal.fields.sale_begin_end_time'); ?></span>
                                        <strong><?php echo htmlspecialchars($saleWindow); ?></strong>
                                    </div>
                                    <?php if ($bonusFactor): ?>
                                        <div class="medal-meta-item">
                                            <span><?php echo nexus_trans('medal.fields.bonus_addition_factor'); ?></span>
                                            <strong><?php echo $bonusFactor; ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($medal->description)): ?>
                                    <div class="medal-card-description">
                                        <?php echo nl2br(htmlspecialchars($medal->description)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="medal-card-actions">
                                <input type="button" class="<?php echo $buyClass; ?>" data-id="<?php echo $medal->id; ?>" value="<?php echo htmlspecialchars($buyBtnText); ?>"<?php echo $buyDisabled; ?>>
                                <div class="medal-card-gift-row">
                                    <input type="number" class="uid" <?php echo $giftDisabled; ?> placeholder="UID">
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

jQuery(function ($) {
    var $seriesFilter = $('#series_id');
    var cardSelector = '.medal-series-card';
    var $emptyNotice = $('.medal-filter-empty');

    function applySeriesFilter(value) {
        var $cards = $(cardSelector);
        if (!$cards.length) {
            $emptyNotice.toggle(value !== 'all');
            return;
        }
        if (!value || value === 'all') {
            $cards.show();
        } else {
            $cards.each(function () {
                var attr = $(this).attr('data-series-id');
                var matchValue = attr === undefined ? '' : String(attr);
                $(this).toggle(matchValue === value);
            });
        }
        var anyVisible = $cards.filter(':visible').length > 0;
        $emptyNotice.toggle(!anyVisible);
    }

    applySeriesFilter($seriesFilter.val());
    $seriesFilter.on('change', function () {
        applySeriesFilter(this.value);
    });
});

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


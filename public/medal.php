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

$newArrivalLimit = 7;
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
    html {
        scroll-behavior: smooth;
    }
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
    .medal-series-hero.has-banner {
        border-radius: 18px;
        overflow: hidden;
        padding: 24px;
    }
    .medal-series-hero.has-banner::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: var(--series-banner);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        filter: blur(18px);
        transform: scale(1.08);
        z-index: 0;
    }
    .medal-series-hero.has-banner::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(140deg, rgba(8, 13, 28, 0.9), rgba(15, 23, 42, 0.8));
        z-index: 1;
        backdrop-filter: saturate(140%) brightness(0.9);
    }
    .medal-series-hero.has-banner > * {
        position: relative;
        z-index: 2;
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
        color: rgba(255, 255, 255, 0.95);
    }
    .medal-reward-body > div:first-child {
        display: flex;
        flex-direction: column;
        gap: 6px;
        color: inherit;
    }
    .medal-reward-body > div:first-child div {
        color: rgba(255, 255, 255, 0.85);
        font-size: 14px;
        line-height: 1.5;
    }
    .medal-reward-body strong {
        color: #ffffff;
        font-weight: 700;
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
        gap: 10px;
        border-radius: 16px;
        padding: 14px;
        background: linear-gradient(155deg, rgba(17, 24, 39, 0.9), rgba(30, 41, 59, 0.93));
        border: 1px solid rgba(148, 163, 184, 0.16);
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.42);
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        min-height: 380px;
        scroll-margin-top: 120px;
    }
    .medal-card:hover {
        transform: translateY(-3px);
        border-color: rgba(96, 165, 250, 0.4);
        box-shadow: 0 22px 40px rgba(30, 64, 175, 0.4);
    }
    .medal-card:target {
        border-color: rgba(148, 197, 255, 0.9);
        box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45);
        animation: targetHighlight 2s ease;
    }
    .medal-card.medal-highlight {
        border-color: rgba(148, 197, 255, 0.9) !important;
        box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45) !important;
        animation: medalPulse 2s ease;
        transform: scale(1.02);
    }
    @keyframes targetHighlight {
        0% {
            transform: scale(1.0);
            box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45);
        }
        20% {
            transform: scale(1.03);
            box-shadow: 0 0 0 5px rgba(148, 197, 255, 0.6), 0 32px 60px rgba(30, 64, 175, 0.55);
        }
        40% {
            transform: scale(1.02);
            box-shadow: 0 0 0 4px rgba(148, 197, 255, 0.5), 0 30px 56px rgba(30, 64, 175, 0.5);
        }
        60% {
            transform: scale(1.01);
        }
        100% {
            transform: scale(1.0);
            box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45);
        }
    }
    @keyframes medalPulse {
        0% {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45);
        }
        25% {
            transform: scale(1.04);
            box-shadow: 0 0 0 6px rgba(148, 197, 255, 0.6), 0 35px 65px rgba(30, 64, 175, 0.6);
        }
        50% {
            transform: scale(1.02);
            box-shadow: 0 0 0 4px rgba(148, 197, 255, 0.5), 0 30px 56px rgba(30, 64, 175, 0.5);
        }
        75% {
            transform: scale(1.03);
            box-shadow: 0 0 0 5px rgba(148, 197, 255, 0.55), 0 32px 60px rgba(30, 64, 175, 0.55);
        }
        100% {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(148, 197, 255, 0.4), 0 28px 52px rgba(30, 64, 175, 0.45);
        }
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
        max-height: 160px;
        border-radius: 14px;
        background: radial-gradient(circle at 50% 30%, rgba(148, 197, 255, 0.28), rgba(15, 23, 42, 0.78));
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
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
        top: 10px;
        left: 10px;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(59, 130, 246, 0.75);
        color: #f8fafc;
        font-size: 11px;
        font-weight: 600;
        z-index: 5;
    }
    .medal-card-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(78, 205, 196, 0.8);
        color: #022c22;
        font-size: 11px;
        font-weight: 600;
        z-index: 5;
    }
.medal-card-badge.unowned {
    background: rgba(96, 165, 250, 0.8);
    color: #0b1a2b;
}
    .medal-card-body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        overflow: hidden;
        min-height: 0;
    }
    .medal-card-title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: rgba(226, 232, 240, 0.95);
        letter-spacing: 0.01em;
        line-height: 1.3;
    }
    .medal-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 11px;
        color: rgba(226, 232, 240, 0.78);
    }
    .medal-card-meta .medal-meta-item {
        flex: 1 1 calc(50% - 8px);
        min-width: 100px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .medal-card-meta .medal-meta-item span {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: rgba(148, 163, 184, 0.7);
        line-height: 1.2;
    }
    .medal-card-meta .medal-meta-item strong {
        color: rgba(248, 250, 252, 0.92);
        font-weight: 600;
        font-size: 13px;
        line-height: 1.3;
    }
    .medal-card-description {
        font-size: 11px;
        line-height: 1.4;
        color: rgba(226, 232, 240, 0.68);
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        margin-top: 2px;
    }
    .medal-card-actions {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding-top: 4px;
    }
    .medal-card-actions input[type="button"] {
        padding: 7px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.01em;
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
        padding: 6px 10px;
        border-radius: 7px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: rgba(15, 23, 42, 0.78);
        color: #f1f5f9;
        font-size: 12px;
    }
    .medal-card-owner-count {
        position: absolute;
        bottom: 10px;
        right: 10px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(6px);
        color: rgba(226, 232, 240, 0.9);
        font-size: 11px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        z-index: 10;
    }
    .medal-card-owner-count strong {
        font-size: 14px;
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
        cursor: pointer;
        text-decoration: none;
        color: inherit;
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
            $targetMedalId = 'medal-' . $arrivalMedal->id;
            $targetHref = '#' . $targetMedalId;
            ?>
            <a class="medal-new-card" href="<?php echo htmlspecialchars($targetHref); ?>">
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
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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
            <?php
            $hasBanner = !empty($block['banner_image']);
            $sectionId = $block['is_other'] ? 'series-other' : ('series-' . $block['id']);
            $bannerStyle = $hasBanner ? ' style="--series-banner: url(\'' . htmlspecialchars($block['banner_image'], ENT_QUOTES) . '\');"' : '';
            ?>
            <section id="<?php echo htmlspecialchars($sectionId); ?>" class="medal-series-card <?php echo $block['is_other'] ? 'is-other' : ''; ?>" data-series-id="<?php echo $block['is_other'] ? 'other' : $block['id']; ?>">
                <div class="medal-series-hero<?php echo $hasBanner ? ' has-banner' : ''; ?>"<?php echo $bannerStyle; ?>>
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
                        $medalCardId = 'medal-' . $medal->id;
                        ?>
                        <div id="<?php echo htmlspecialchars($medalCardId); ?>" class="medal-card <?php echo $cardClass; ?>">
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
$confirmBuyMsg = json_encode(nexus_trans('medal.confirm_to_buy'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$confirmGiftMsg = json_encode(nexus_trans('medal.confirm_to_gift'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$claimSuccess = json_encode(nexus_trans('medal-series.frontend.claim_success'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$claimFailed = json_encode(nexus_trans('medal-series.frontend.claim_failed'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$js = <<<JS
// 测试：确认代码已加载
(function() {
    console.log('=== 勋章页面JavaScript开始加载 ===');
    console.log('jQuery是否可用:', typeof jQuery !== 'undefined');
    console.log('layer是否可用:', typeof layer !== 'undefined');
    
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
})();

// 等待DOM加载完成后再绑定
(function() {
    function initMedalButtons() {
        console.log('DOM已加载，开始绑定事件');
        
        // 测试：检查按钮是否存在
        var buyButtons = jQuery('.medal-card-actions input[type="button"][data-id]');
        console.log('找到购买按钮数量:', buyButtons.length);
        buyButtons.each(function(i) {
            var btn = jQuery(this);
            console.log('按钮' + i + ':', btn.attr('data-id'), 'disabled:', btn.prop('disabled'), 'class:', btn.attr('class'));
        });
        
        var giftButtons = jQuery('.medal-card-gift-row input[type="button"][data-id]');
        console.log('找到赠送按钮数量:', giftButtons.length);
    }
    
    if (typeof jQuery !== 'undefined') {
        if (jQuery(document).ready) {
            jQuery(document).ready(initMedalButtons);
        } else {
            initMedalButtons();
        }
    } else {
        console.error('jQuery未加载！');
    }
})();

// 购买按钮事件 - 使用更具体的选择器
jQuery(document).on('click', '.medal-card-actions input.buy', function (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    console.log('购买按钮被点击');
    
    var btn = jQuery(this);
    if (btn.prop('disabled')) {
        console.log('按钮已禁用，忽略点击');
        return false;
    }
    
    var medalId = btn.attr('data-id');
    if (!medalId) {
        console.error('购买按钮：勋章ID缺失');
        if (typeof window.nexusAlert === 'function') {
            window.nexusAlert('勋章ID缺失');
        } else if (typeof layer !== 'undefined') {
            layer.alert('勋章ID缺失');
        } else {
            alert('勋章ID缺失');
        }
        return false;
    }
    console.log('购买按钮点击，勋章ID:', medalId);
    
    var confirmCallback = function() {
        btn.prop('disabled', true);
        var params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        };
        console.log('发送购买请求:', params);
        jQuery.post('ajax.php', params, function(response) {
            console.log('购买响应:', response);
            btn.prop('disabled', false);
            if (response.ret != 0) {
                var msg = response.msg || '购买失败';
                if (typeof window.nexusAlert === 'function') {
                    window.nexusAlert(msg);
                } else if (typeof layer !== 'undefined') {
                    layer.alert(msg);
                } else {
                    alert(msg);
                }
                return;
            }
            if (typeof window.nexusMsg === 'function') {
                window.nexusMsg('购买成功', 1500, function() {
                    window.location.reload();
                });
            } else if (typeof layer !== 'undefined') {
                layer.msg('购买成功', {time: 1500}, function() {
                    window.location.reload();
                });
            } else {
                alert('购买成功');
                window.location.reload();
            }
        }, 'json').fail(function(xhr) {
            console.error('购买请求失败:', xhr);
            btn.prop('disabled', false);
            var errorMsg = '请求失败，请重试';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                errorMsg = xhr.responseJSON.msg;
            }
            if (typeof layer !== 'undefined') {
                layer.alert(errorMsg);
            } else {
                alert(errorMsg);
            }
            restoreUrl();
        });
    };
    
    // 使用自定义确认弹窗，不操作历史记录
    if (typeof window.nexusConfirm === 'function') {
        window.nexusConfirm({$confirmBuyMsg}, function() {
            confirmCallback();
        });
    } else if (typeof layer !== 'undefined') {
        layer.confirm({$confirmBuyMsg}, {
            btn: ['确认', '取消']
        }, function (index) {
            layer.close(index);
            confirmCallback();
        }, function (index) {
            layer.close(index);
        });
    } else {
        if (confirm('确认购买勋章 ID: ' + medalId + '?')) {
            confirmCallback();
        }
    }
    
    return false;
});

// 赠送按钮事件 - 使用更具体的选择器
jQuery(document).on('click', '.medal-card-gift-row input.gift', function (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    console.log('赠送按钮被点击');
    
    var btn = jQuery(this);
    if (btn.prop('disabled')) {
        console.log('按钮已禁用，忽略点击');
        return false;
    }
    
    var medalId = btn.attr('data-id');
    if (!medalId) {
        console.error('赠送按钮：勋章ID缺失');
        if (typeof window.nexusAlert === 'function') {
            window.nexusAlert('勋章ID缺失');
        } else if (typeof layer !== 'undefined') {
            layer.alert('勋章ID缺失');
        } else {
            alert('勋章ID缺失');
        }
        return false;
    }
    
    var uidInput = btn.closest('.medal-card-gift-row').find('.uid');
    var uid = uidInput.val();
    if (!uid || uid.trim() === '') {
        var msg = '请输入用户ID';
        if (typeof window.nexusAlert === 'function') {
            window.nexusAlert(msg);
        } else if (typeof layer !== 'undefined') {
            layer.alert(msg);
        } else {
            alert(msg);
        }
        uidInput.focus();
        return false;
    }
    console.log('赠送按钮点击，勋章ID:', medalId, '用户ID:', uid);
    
    var confirmCallback = function() {
        btn.prop('disabled', true);
        var params = {
            action: "giftMedal",
            params: {medal_id: medalId, uid: uid}
        };
        console.log('发送赠送请求:', params);
        jQuery.post('ajax.php', params, function(response) {
            console.log('赠送响应:', response);
            btn.prop('disabled', false);
            if (response.ret != 0) {
                var msg = response.msg || '赠送失败';
                if (typeof window.nexusAlert === 'function') {
                    window.nexusAlert(msg);
                } else if (typeof layer !== 'undefined') {
                    layer.alert(msg);
                } else {
                    alert(msg);
                }
                return;
            }
            if (typeof window.nexusMsg === 'function') {
                window.nexusMsg('赠送成功', 1500, function() {
                    window.location.reload();
                });
            } else if (typeof layer !== 'undefined') {
                layer.msg('赠送成功', {time: 1500}, function() {
                    window.location.reload();
                });
            } else {
                alert('赠送成功');
                window.location.reload();
            }
        }, 'json').fail(function(xhr) {
            console.error('赠送请求失败:', xhr);
            btn.prop('disabled', false);
            var errorMsg = '请求失败，请重试';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                errorMsg = xhr.responseJSON.msg;
            }
            if (typeof layer !== 'undefined') {
                layer.alert(errorMsg);
            } else {
                alert(errorMsg);
            }
            restoreUrl();
        });
    };
    
    // 使用自定义确认弹窗，不操作历史记录
    var giftConfirmMsg = {$confirmGiftMsg} + " 给用户 " + uid + " ?";
    if (typeof window.nexusConfirm === 'function') {
        window.nexusConfirm(giftConfirmMsg, function() {
            confirmCallback();
        });
    } else if (typeof layer !== 'undefined') {
        layer.confirm(giftConfirmMsg, {
            btn: ['确认', '取消']
        }, function (index) {
            layer.close(index);
            confirmCallback();
        }, function (index) {
            layer.close(index);
        });
    } else {
        if (confirm('确认赠送勋章 ID: ' + medalId + ' 给用户 ' + uid + '?')) {
            confirmCallback();
        }
    }
    
    return false;
});

jQuery(document).on('click', '.series-claim-btn', function (e) {
    e.preventDefault();
    var btn = jQuery(this);
    if (btn.prop('disabled')) {
        return;
    }
    var seriesId = btn.data('series-id');
    if (!seriesId) {
        return;
    }
    btn.prop('disabled', true);
    jQuery.post('ajax.php', {action: 'medal_series_claim', series_id: seriesId}, function(response) {
        if (response.success) {
            if (typeof window.nexusMsg === 'function') {
                window.nexusMsg(response.message || {$claimSuccess}, 1500, function () {
                    window.location.reload();
                });
            } else if (typeof layer !== 'undefined') {
                layer.msg(response.message || {$claimSuccess}, {time: 1500}, function () {
                    window.location.reload();
                });
            } else {
                alert(response.message || {$claimSuccess});
                window.location.reload();
            }
        } else {
            btn.prop('disabled', false);
            if (typeof window.nexusAlert === 'function') {
                window.nexusAlert(response.message || {$claimFailed});
            } else if (typeof layer !== 'undefined') {
                layer.alert(response.message || {$claimFailed});
            } else {
                alert(response.message || {$claimFailed});
            }
        }
    }, 'json').fail(function(xhr) {
        btn.prop('disabled', false);
        var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : {$claimFailed};
        if (typeof window.nexusAlert === 'function') {
            window.nexusAlert(errorMsg);
        } else if (typeof layer !== 'undefined') {
            layer.alert(errorMsg);
        } else {
            alert(errorMsg);
        }
    });
});

// 处理勋章高亮动效
(function() {
    function highlightMedal(medalId) {
        // 移除之前的高亮
        jQuery('.medal-card.medal-highlight').removeClass('medal-highlight');
        
        // 找到目标勋章
        var medalElement = jQuery('#' + medalId);
        if (medalElement.length === 0) {
            // 如果元素还没渲染，等待一小段时间后重试
            setTimeout(function() {
                var retryElement = jQuery('#' + medalId);
                if (retryElement.length > 0) {
                    doScrollAndHighlight(retryElement);
                }
            }, 50);
            return;
        }
        
        doScrollAndHighlight(medalElement);
    }
    
    function doScrollAndHighlight(medalElement) {
        // 获取目标位置
        var offset = medalElement.offset().top - 100; // 留出顶部空间
        
        // 立即开始滚动，使用较快的动画
        jQuery('html, body').stop(true, false).animate({
            scrollTop: offset
        }, 400, 'swing', function() {
            // 滚动完成后添加高亮动效
            medalElement.addClass('medal-highlight');
            
            // 2秒后移除高亮类，但保留:target样式
            setTimeout(function() {
                medalElement.removeClass('medal-highlight');
            }, 2000);
        });
    }
    
    // 页面加载时检查URL hash
    if (window.location.hash) {
        var medalId = window.location.hash.substring(1);
        if (medalId && medalId.startsWith('medal-')) {
            setTimeout(function() {
                highlightMedal(medalId);
            }, 100);
        }
    }
    
    // 拦截"最近上新"卡片的点击，立即滚动到目标位置
    jQuery(document).on('click', '.medal-new-card', function(e) {
        var href = jQuery(this).attr('href');
        if (href && href.startsWith('#')) {
            var medalId = href.substring(1);
            if (medalId && medalId.startsWith('medal-')) {
                e.preventDefault();
                e.stopPropagation();
                
                // 立即滚动，不等待hashchange事件
                highlightMedal(medalId);
                
                // 更新URL hash（不影响滚动，只是更新地址栏）
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, null, href);
                } else {
                    window.location.hash = href;
                }
            }
        }
    });
    
    // 监听hash变化（用于浏览器前进/后退按钮）
    jQuery(window).on('hashchange', function() {
        if (window.location.hash) {
            var medalId = window.location.hash.substring(1);
            if (medalId && medalId.startsWith('medal-')) {
                // 使用requestAnimationFrame确保DOM已准备好
                requestAnimationFrame(function() {
                    highlightMedal(medalId);
                });
            }
        }
    });
})();
JS;
\Nexus\Nexus::js($js, 'footer', false);

end_main_frame();
stdfoot();


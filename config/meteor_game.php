<?php

return [
    'score' => [
        'min' => -10000,
        'max' => 10000,
    ],
    'duration' => [
        'expected_seconds' => 60,
        'min_seconds' => 55,
        'max_seconds' => 70,
        'allowed_delta_seconds' => 2.5,
    ],
    'combo' => [
        'max' => 80,
        'lenient_check_threshold' => 1,
    ],
    'frequency' => [
        'min_submit_interval_seconds' => 30,
        'daily_submit_limit' => 5,
    ],
    'flag_rules' => [
        'high_score_threshold' => 3000,
        'min_combo_for_high_score' => 15,
        'min_inputs_total' => 10,
        'min_inputs_score_threshold' => 800,
        'max_bad_ratio_for_reward' => 0.5,
        'min_events_for_submission' => 15,
        'event_hard_limit' => 600,
        'input_hard_limit' => 2000,
    ],
];


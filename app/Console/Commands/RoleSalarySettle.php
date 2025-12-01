<?php

namespace App\Console\Commands;

use App\Repositories\RoleSalaryRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RoleSalarySettle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:salary_settle
                            {--month= : 结算月份，格式：YYYY-MM，默认为当前自然月}
                            {--uid= : 只结算指定用户 ID}
                            {--test= : 测试模式，只计算不落库，1 表示开启}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '按用户角色结算每月工资（魔力与邀请），支持测试模式和指定月份';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(RoleSalaryRepository $repository)
    {
        $month = $this->option('month');
        $uid = $this->option('uid');
        $test = $this->option('test');

        $userId = $uid ? (int)$uid : null;
        $isTest = (bool)$test;

        $this->info(sprintf(
            'Start role:salary_settle, month=%s, uid=%s, test=%s',
            $month ?: Carbon::now()->format('Y-m'),
            $userId ?: 'ALL',
            $isTest ? 'yes' : 'no'
        ));

        $result = $repository->settle($month, $userId, $isTest);

        $this->info(sprintf(
            'role:salary_settle done. month=%s, uid=%s, is_test=%s, roles=%s',
            $result['month'] ?? 'n/a',
            $result['user_id'] ?? 'ALL',
            $result['is_test'] ? 'yes' : 'no',
            implode(',', array_keys($result['roles'] ?? []))
        ));

        $this->printRoleDetails($result['roles'] ?? []);

        $log = sprintf(
            '[%s], %s, params: %s, result: %s',
            nexus()->getRequestId(),
            __METHOD__,
            json_encode([
                'month' => $month,
                'uid' => $userId,
                'is_test' => $isTest,
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                'month' => $result['month'] ?? null,
                'roles' => array_map(fn($item) => $item['users'] ?? [], $result['roles'] ?? []),
            ])
        );

        do_log($log);
        $this->info($log);

        return 0;
    }

    /**
     * 按角色输出详细的结算数据，便于排查
     *
     * @param array $roles
     * @return void
     */
    protected function printRoleDetails(array $roles): void
    {
        if (empty($roles)) {
            $this->info('No role results.');
            return;
        }
        foreach ($roles as $roleName => $roleData) {
            $users = $roleData['users'] ?? [];
            $settled = $roleData['settled'] ?? [];
            $this->info(sprintf(
                '[%s] users=%d, settled=%d',
                $roleName,
                is_countable($users) ? count($users) : 0,
                is_countable($settled) ? count($settled) : 0
            ));
            if (empty($settled)) {
                continue;
            }
            foreach ($settled as $item) {
                $this->line('  - ' . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }
}



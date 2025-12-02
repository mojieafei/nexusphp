<?php

namespace App\Jobs;

use App\Models\AdminOperationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAdminOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $path,
        public string $method,
        public ?array $requestQuery,
        public ?array $requestBody,
        public ?string $ip,
        public ?string $userAgent
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $now = now();
            AdminOperationLog::query()->create([
                'user_id' => $this->userId,
                'path' => $this->path,
                'method' => $this->method,
                'action' => null,
                'request_query' => $this->requestQuery,
                'request_body' => $this->requestBody,
                'ip' => $this->ip,
                'user_agent' => $this->userAgent,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            do_log('[LogAdminOperation] error: ' . $e->getMessage(), 'error');
            throw $e; // 重新抛出异常，让队列重试
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        do_log('[LogAdminOperation] failed after retries: ' . $exception->getMessage(), 'error');
    }
}


<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Database\NexusDB;

class ClearPasskeyInvalidCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear_passkey_invalid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all passkey_invalid cache keys from Redis (useful after database migration)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to clear passkey_invalid cache keys...');
        
        $redis = NexusDB::redis();
        if (!$redis) {
            $this->error('Redis connection failed!');
            return 1;
        }

        $pattern = 'passkey_invalid:*';
        $deletedCount = 0;
        $it = NULL;
        
        // 使用 SCAN 命令遍历所有匹配的键
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        
        do {
            $keys = $redis->scan($it, $pattern, 1000);
            
            if ($keys !== false && !empty($keys)) {
                foreach ($keys as $key) {
                    if ($redis->del($key)) {
                        $deletedCount++;
                        $this->line("Deleted: $key");
                    }
                }
            }
        } while ($it > 0);

        $log = sprintf('[%s] Cleared %d passkey_invalid cache keys', nexus()->getRequestId(), $deletedCount);
        $this->info($log);
        do_log($log);
        
        $this->info("Successfully cleared $deletedCount passkey_invalid cache keys.");
        
        return 0;
    }
}


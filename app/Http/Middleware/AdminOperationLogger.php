<?php

namespace App\Http\Middleware;

use App\Models\AdminOperationLog;
use Closure;
use Illuminate\Http\Request;

class AdminOperationLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // 先返回响应，然后同步记录日志
        // 即使日志记录失败也不影响正常操作
        
        try {
            $user = auth()->user();
            if (!$user) {
                return $response;
            }

            // 仅记录后台（Filament）相关请求
            $path = $request->path();
            $uri = $request->getRequestUri();
            $referer = $request->header('Referer', '');
            
            // 排除操作日志页面本身的访问，避免循环记录
            if (str_contains($path, 'admin-operation-logs') || str_contains($uri, 'admin-operation-logs')) {
                return $response;
            }
            
            // 检查路径是否包含 nexusphp（可能是 nexusphp/... 或 /nexusphp/...）
            $isAdminPath = str_contains($path, 'nexusphp') || str_contains($uri, '/nexusphp/');
            
            // 对于 Livewire 请求，检查 Referer 是否来自 Filament 后台
            if (str_contains($path, 'livewire') || str_contains($uri, '/livewire/')) {
                $isAdminPath = str_contains($referer, '/nexusphp/');
                // 如果 Livewire 请求来自操作日志页面，也排除
                if (str_contains($referer, 'admin-operation-logs')) {
                    return $response;
                }
            }
            
            // 排除静态资源（但不排除 livewire，因为它是用户操作）
            $excludedPaths = ['css', 'js', 'images', 'fonts', 'favicon'];
            $shouldExclude = false;
            foreach ($excludedPaths as $excluded) {
                if (str_contains($path, $excluded) || str_contains($uri, $excluded)) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if (!$isAdminPath || $shouldExclude) {
                return $response;
            }

            // 同步记录操作日志
            $queryParams = $request->query();
            $bodyParams = $request->except(['password', 'password_confirmation', '_token']);
            $now = now();
            
            AdminOperationLog::query()->create([
                'user_id' => $user->id,
                'path' => $path,
                'method' => $request->method(),
                'action' => null,
                'request_query' => !empty($queryParams) ? $queryParams : null,
                'request_body' => !empty($bodyParams) ? $bodyParams : null,
                'ip' => $request->ip(),
                'user_agent' => (string) substr($request->userAgent() ?? '', 0, 1000),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            
        } catch (\Throwable $e) {
            // 静默失败，不影响正常操作
            do_log('[AdminOperationLogger] error: ' . $e->getMessage(), 'error');
        }

        return $response;
    }
}



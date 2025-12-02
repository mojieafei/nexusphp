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

        try {
            $user = auth()->user();
            if (!$user) {
                return $response;
            }

            // 仅记录后台（Filament）相关请求，路径一般以 nexusphp/ 开头
            $path = $request->path();
            if (!str_starts_with($path, 'nexusphp')) {
                return $response;
            }

            AdminOperationLog::query()->create([
                'user_id' => $user->id,
                'path' => $path,
                'method' => $request->method(),
                'action' => null,
                'request_query' => $request->query(),
                'request_body' => $request->except(['password', 'password_confirmation', '_token']),
                'ip' => $request->ip(),
                'user_agent' => (string) substr($request->userAgent() ?? '', 0, 1000),
            ]);
        } catch (\Throwable $e) {
            do_log('[AdminOperationLogger] error: ' . $e->getMessage(), 'error');
        }

        return $response;
    }
}



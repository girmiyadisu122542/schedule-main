<?php

namespace App\Http\Middleware;

use Carbon\CarbonInterval;
use Closure;
use Helper\Response\Response;
use Illuminate\Support\Facades\Cache;
use Translation\Message;

class RateLimitMiddleware {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     * @param  string|null $prefix
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $maxAttempts = 5, $decaySeconds = 60, $prefix = null) {
        $key = $this->resolveRequestSignature($request, $prefix);
        $attempts = Cache::get($key, 0);
        if ($attempts >= $maxAttempts) {
            $retryAfter = Cache::get($key . ':timer') - time();
            $bindings = ['minutes' => CarbonInterval::seconds($retryAfter)->cascade()->forHumans()];
            return Response::_409(Message::get('too_many_attempts', $bindings));
        }

        if (!Cache::has($key . ':timer')) {
            Cache::put($key . ':timer', time() + $decaySeconds, $decaySeconds);
        }

        Cache::put($key, $attempts + 1, $decaySeconds);
        return $next($request);
    }

    protected function resolveRequestSignature($request, $prefix = null) {
        $ip = $request->ip();
        $route = $request->route()
            ? $request->route()->getName()
            : $request->path();

        return ($prefix ?: 'rate_limit') . ':' . sha1($ip . '|' . $route);
    }
}

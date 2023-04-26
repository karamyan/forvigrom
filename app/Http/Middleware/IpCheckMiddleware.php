<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PaymentService\Exceptions\ForbiddenResponseException;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Class IpCheckMiddleware.
 *
 * @package App\Http\Middleware
 */
class IpCheckMiddleware
{
    /**
     * Checking if request ip whitelist for platform.
     *
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws ForbiddenResponseException
     */
    public function handle($request, Closure $next): mixed
    {
        $allowedIPS = explode(',', config('app.platform_ip_list'));

        if (config('app.env') === 'production') {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $request->ip();

            $this->checkIp($ip, $allowedIPS);
        } else if (config('app.env') === 'development') {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $request->ip();

            $currentPath = trim($request->path(), '/');
            $withdrawPath = trim(route(name: 'do_withdraw', absolute: false), '/');

            if ($currentPath === $withdrawPath) {
                $this->checkIp($ip, $allowedIPS);
            }
        }

        return $next($request);
    }

    private function checkIp($ip, $allowedIPS)
    {
        if (!in_array($ip, $allowedIPS)) {

            Log::channel('errors')->error('IP address is not allowed.', [
                "ip" => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? request()->ip(),
                "request_uri" => request()->route()->uri(),
                "request_body" => request()->all()
            ]);

            throw new ForbiddenResponseException('IP address is not allowed.', 403);
        }
    }
}

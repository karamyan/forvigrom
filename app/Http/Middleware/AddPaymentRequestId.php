<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddPaymentRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->server('X-Request-ID') ?? md5(uniqid());

        $request->request->add(['payment_request_id' => $requestId]);

        return $next($request);
    }
}

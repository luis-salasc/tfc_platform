<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('member-portal.enabled') === true, 404);

        return $next($request);
    }
}

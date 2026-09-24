<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;

final class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Access::admin($request), 403, 'Forbidden');

        return $next($request);
    }
}

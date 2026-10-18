<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The demo account (for recruiters) is read-only and never sees the forum or slogans
class BlockDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_demo) {
            abort(403, 'Not available in the demo');
        }

        return $next($request);
    }
}

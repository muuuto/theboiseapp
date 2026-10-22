<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

// "Last login" also counts the visits of who stays logged in (Remember me, the app on the phone):
// updated at most once a day. It's what says someone is still around (User::isActive)
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->last_login || substr((string) $user->last_login, 0, 10) !== now()->toDateString())) {
            // Without touching updated_at, and without events
            DB::table('users')->where('id', $user->id)->update(['last_login' => now()]);
            $user->last_login = now();
        }

        return $next($request);
    }
}

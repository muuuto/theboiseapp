<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The language of the page: the account's choice, or the one picked on the login page, or English
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');
        Locale::set(isset(Locale::AVAILABLE[$locale]) ? $locale : Locale::DEFAULT);

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Wrapped;
use Illuminate\Http\Request;
use Inertia\Inertia;

// TheBoise Wrapped: your year in albums (the current year opens in December)
class WrappedController extends Controller
{
    public function index(Request $request)
    {
        $years = Wrapped::years($request->user());

        return $years
            ? redirect("/wrapped/{$years[0]}")
            : Inertia::render('Wrapped', ['wrapped' => null, 'years' => [], 'opensOn' => $this->opensOn()]);
    }

    public function show(Request $request, int $year)
    {
        $user = $request->user();
        if (! Wrapped::isOpen($user, $year)) {
            return redirect('/wrapped')->with('message', __('Your :year Wrapped opens on :date', ['year' => $year, 'date' => $this->opensOn()]));
        }

        return Inertia::render('Wrapped', [
            'wrapped' => Wrapped::for($user, $year),
            'year' => $year,
            'years' => Wrapped::years($user),
            'opensOn' => $this->opensOn(),
        ]);
    }

    private function opensOn(): string
    {
        return now()->setDate(now()->year, Wrapped::OPENS_MONTH, 1)->translatedFormat('j F');
    }
}

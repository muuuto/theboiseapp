<?php

namespace App\Http\Controllers;

use App\Models\Slogan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SloganController extends Controller
{
    // Show Create Slogan Form
    public function create() {
        return Inertia::render('Slogans/Create');
    }

    // Store Listing Data
    public function store(Request $request) {
        $user = auth()->user()['name'];
        $formFields = $request->validate([
            'sloganPhrase' => 'required|string|max:255'
        ]);

        $formFields['author'] = $user;

        Slogan::create($formFields);

        return redirect('/')->with('message', 'Slogan created successfully!');
    }
}

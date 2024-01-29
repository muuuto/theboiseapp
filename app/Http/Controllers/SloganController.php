<?php

namespace App\Http\Controllers;

use App\Models\Slogan;
use Illuminate\Http\Request;

class SloganController extends Controller
{
    // Show Create Slogan Form
    public function create() {
        return view('slogan.create');
    }

    // Store Listing Data
    public function store(Request $request) {
        $user = auth()->user()['name'];
        $formFields = $request->validate([
            'sloganPhrase' => 'required'
        ]);

        $formFields['author'] = $user;

        Slogan::create($formFields);

        return redirect('/')->with('message', 'Slogan created successfully!');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Listing;
use App\Models\Slogan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Models\Comment;

class ListingController extends Controller
{
    // Show all listings
    public function index() {
        $user = auth()->user();
        if ($user) {
            $listings = $user->listing();
        }

        $slogan = Slogan::all();

        if(count($slogan) > 0) {
            $randomSlogan = $slogan->random();
        } else {
            $randomSlogan["sloganPhrase"] = "Find your albums";
        }

        return view('listings.index', [
            'listings' => $user ? $listings->filter(request(['tag', 'search']))->orderBy('dateFrom', 'desc')->paginate(24)->withQueryString() : [],
            'slogan' => $randomSlogan
        ]);
    }

    // Show single listing
    public function show(Listing $listing) {
        $user = auth()->user()['id'];
        $username = auth()->user()['name'];
        $comments = $listing->comments;
        $videoLinks = explode(',', $listing->videoLinks);

        foreach($listing->users as $currentUser) {
            if ($currentUser['id'] == $user) {
                return view('listings.show', [
                    'listing' => $listing,
                    'comments' => $comments,
                    'user' => $username,
                    'videoLinks' => $videoLinks
                ]);
            }
        }
        abort(403, 'Unauthorized Action');
    }

    // Show Create Form
    public function create() {
        $users = User::all();
        $user = auth()->user()['isAdmin'];
        if ($user == 1) {
            return view('listings.create', compact(['users']));
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Store Listing Data
    public function store(Request $request, Listing $listing) {
        $formFields = $request->validate([
            'title' => 'required',
            'dateFrom' => 'required',
            'dateTo' => 'required',
            'location' => 'required',
            'albumLink' => 'required',
            'tags' => 'required',
            'description' => 'required'
        ]);

        $formPeopleField = $request->validate([
            'people' => 'required'
        ]);

        if($request->hasFile('logo')) {
            $formFields['logo'] = $request->file('logo')->store('logos', 'public');
        }

        if($request->has('videoLinks')) {
            $formFields['videoLinks'] = $request->videoLinks;
        }

        $formFields['user_id'] = auth()->id();

        // send email to user when new album is created
        $users = User::findOrFail($request->people);

        $albumTitle = $formFields['title'];

        $listing = Listing::create($formFields);

        foreach($users as $user) {
            // email data
            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'albumTitle' => $formFields['title'],
                'albumLink' => 'https://theboise.it/listings/' . $listing->id
            );
            Mail::send('/mails/new_album', $email_data, function ($message) use ($email_data, $albumTitle) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New album ' . $albumTitle . ' added to theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }
        foreach($request->people as $person) {
            $listing->users()->attach($person);
        }

        return redirect('/')->with('message', 'Listing created successfully!');
    }

    // Show Edit Form
    public function edit(Listing $listing) {
        $users = User::all();
        $user = auth()->user()['isAdmin'];
        if ($user == 1) {
            return view('listings.edit', ['listing' => $listing, 'users' => $users]);
        } else {
            abort(403, 'Unauthorized Action');
        }
    }

    // Update Listing Data
    public function update(Request $request, Listing $listing) {
        // Make sure logged in user is owner
        if($listing->user_id != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }

        $formFields = $request->validate([
            'title' => 'required',
            'dateFrom' => 'required',
            'dateTo' => 'required',
            'location' => 'required',
            'albumLink' => 'required',
            'tags' => 'required',
            'description' => 'required'
        ]);

        $formPeopleField = $request->validate([
            'people' => 'required'
        ]);

        if($request->has('videoLinks')) {
            $formFields['videoLinks'] = $request->videoLinks;
        }

        if($request->hasFile('logo')) {
            Storage::disk('public')->delete($listing->logo);
            $formFields['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $listing->users()->sync($request->people);

        $listing->update($formFields);

        return redirect('/')->with('message', 'Listing updated successfully!');
    }

    // Delete Listing
    public function destroy(Listing $listing) {
        // Make sure logged in user is owner
        if($listing->user_id != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }
        if($listing->logo && Storage::disk('public')->exists($listing->logo)) {
            Storage::disk('public')->delete($listing->logo);
        }
        $listing->delete();
        return redirect('/')->with('message', 'Listing deleted successfully');
    }

    // Manage Listings
    public function manage() {
        $user = auth()->user();
        if ($user) {
            $listings = $user->listing()->get();
        }

        return view('listings.manage', ['listings' => $user ? $listings : []]);
    }

    // Store Comments Data
    public function comments(Request $request, Listing $listing) {
        $formFields = $request->validate([
            'listing_id' => 'required',
            'comment' => 'required'
        ]);

        $user = auth()->user();
        $userId = auth()->id();

        $formFields['user_id'] = $user->id;

        $users = Listing::findOrFail($request->listing_id)->users;
        $albumTitle = Listing::findOrFail($request->listing_id)->title;
        $commentOfUser = User::findOrFail($userId)->name;

        foreach($users as $user) {
            if($userId == $user['id']) {
                continue;
            }

            $email_data = array(
                'name' => $user['name'],
                'email' => $user['email'],
                'commentOfUser' => $commentOfUser,
                'comment' => $formFields['comment'],
                'albumTitle' => $albumTitle,
                'albumLink' => 'https://theboise.it/listings/' . $formFields['listing_id']
            );
            Mail::send('/mails/new_comment', $email_data, function ($message) use ($email_data, $albumTitle) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject('New comment added to album ' . $albumTitle . ' inside theboise.it')
                    ->from('info@theboise.it', 'TheBoise');
            });
        }

        Comment::create($formFields);

        return back()->with('message', 'Comment created successfully!');
    }
}

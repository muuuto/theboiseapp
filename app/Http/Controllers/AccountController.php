<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Support\Notify;
use App\Support\VapidKeys;
use App\Models\Slogan;
use App\Models\User;
use App\Support\AlbumCover;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $wallet = $user->wallet ? json_decode($user->wallet, true) : null;

        return Inertia::render('Account/Index', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => Media::url($user->profilePicture),
                'birthday' => $user->birthday,
            ],
            'wallet' => $wallet ? ['name' => $wallet['name'], 'url' => Media::url($wallet['path'])] : null,
            // Phone notifications (null until their migration runs; never for the demo account).
            // Not "notifications": that's the bell, given to every page
            'push' => ! $user->is_demo && Schema::hasTable('push_subscriptions') ? [
                'publicKey' => VapidKeys::publicKey(),
                'settings' => Notify::settings($user),
                'devices' => PushController::devices(request()),
            ] : null,
        ]);
    }

    // Name, profile picture and birthday
    public function update(Request $request)
    {
        $user = auth()->user();

        $formFields = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'profilePicture' => 'nullable|image|max:20480',
            // The birthday emails use it
            'birthday' => 'nullable|date|before:today',
        ], [
            'name.unique' => 'This name is already taken.',
        ]);

        if ($request->hasFile('profilePicture')) {
            if ($user->profilePicture) {
                Storage::disk('public')->delete($user->profilePicture);
            }
            $formFields['profilePicture'] = AlbumCover::store($request->file('profilePicture'), 'profilePic', 384);
        } else {
            unset($formFields['profilePicture']);
        }

        // Slogans store the author's name: they follow the rename
        if ($formFields['name'] !== $user->name) {
            Slogan::where('author', $user->name)->update(['author' => $formFields['name']]);
        }

        $user->update($formFields);

        return redirect('/account')->with('message', 'Account updated!');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'current_password.current_password' => 'The current password is not correct.',
        ]);

        $request->user()->update(['password' => Hash::make($request->password)]);

        return redirect('/account')->with('message', 'Password changed!');
    }

    // One personal photo with a name, shown on the account page
    public function addWallet(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'mediaName' => 'required|string|max:255',
            'addWallet' => 'required|image|max:5120',
        ], [
            'addWallet.max' => 'The photo must be smaller than 5 MB.',
        ]);

        if ($user->wallet) {
            Storage::disk('public')->delete(json_decode($user->wallet, true)['path']);
        }

        // Stored as JSON text, like the wallets saved before
        $user->update(['wallet' => json_encode([
            'name' => $request->mediaName,
            'path' => $request->file('addWallet')->store('wallet', 'public'),
        ])]);

        return redirect('/account')->with('message', 'Wallet updated!');
    }

    // "Only albums I posted" on the album list and the map (before its migration it isn't offered)
    public function albumFilter(Request $request)
    {
        $data = $request->validate(['on' => 'required|boolean']);
        if (Schema::hasColumn('users', 'only_own_albums')) {
            $request->user()->update(['only_own_albums' => $data['on']]);
        }

        return back();
    }
}

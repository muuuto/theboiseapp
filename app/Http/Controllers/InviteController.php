<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use Illuminate\Http\Request;

// Admins create invite links on the maintenance page: registration is invite-only
class InviteController extends Controller
{
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'note' => 'nullable|string|max:255',
            'days' => 'required|integer|in:1,7,30',
        ]);

        [, $token] = Invite::generate(auth()->user(), $formFields['note'] ?? null, (int) $formFields['days']);

        // Shown only once: the database keeps just the hash of the code
        return redirect('/admin/maintenance')->with('inviteLink', url('/register/' . $token));
    }

    public function destroy(Invite $invite)
    {
        abort_if($invite->used_at, 403, 'This invite was already used');

        $invite->delete();

        return redirect('/admin/maintenance')->with('message', 'Invite revoked');
    }
}

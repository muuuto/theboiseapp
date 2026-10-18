<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\PostComment;
use Illuminate\Http\Request;

// Like / unlike a comment (album comments now, forum comments when the forum moves to Vue)
class LikeController extends Controller
{
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:album_comment,post_comment',
            'id' => 'required|integer',
        ]);

        $user = $request->user();
        $comment = match ($data['type']) {
            'album_comment' => Comment::with('listing.users')->findOrFail($data['id']),
            'post_comment' => PostComment::with('post')->findOrFail($data['id']),
        };

        // Only for comments the user can see
        $visible = $comment instanceof Comment
            ? $comment->listing?->users->contains('id', $user->id)
            : $comment->post?->isVisibleTo($user);
        abort_unless($visible, 403, 'Unauthorized Action');

        $existing = $comment->likes()->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            $comment->likes()->create(['user_id' => $user->id]);
        }

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Notify;
use App\Models\Comment;
use App\Models\PostComment;
use App\Notifications\CommentLiked;
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

        $author = $comment->user;
        $existing = $comment->likes()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            // Unliked before the author saw it: the notification goes away too
            $author?->unreadNotifications()
                ->where('type', CommentLiked::class)
                ->get()
                ->filter(fn ($notification) => $notification->data['liker_id'] == $user->id
                    && $notification->data['comment_type'] === $data['type']
                    && $notification->data['comment_id'] == $comment->id)
                ->each->delete();
        } else {
            $comment->likes()->create(['user_id' => $user->id]);
            // Tell the author (in the bell), not for your own comments or the demo account
            if ($author && $author->id !== $user->id && ! $author->is_demo) {
                $liked = new CommentLiked($user, $comment);
                $author->notify($liked);
                $data = $liked->toArray($author);
                Notify::push($author, 'like', [
                    'title' => $user->name . ' liked your comment',
                    'body' => $data['title'] ? 'On ' . $data['title'] : 'Tap to see it.',
                    'url' => $data['url'],
                    'tag' => 'like-' . $data['comment_type'] . '-' . $comment->id,
                ]);
            }
        }

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Reacted;
use App\Support\Notify;
use Illuminate\Http\Request;

// React to an album or a forum post with an emoji: one each. The same emoji again removes it,
// another one changes it
class ReactionController extends Controller
{
    // Emojis only (the picker's, with skin tones, flags and joined ones), never text
    private const EMOJI = '/^(?=.*[^\x00-\x7F])[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{25A0}-\x{25FF}\x{2934}\x{2935}\x{3030}\x{303D}\x{3297}\x{3299}\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}#*0-9]+$/u';

    public function react(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:album,post',
            'id' => 'required|integer',
            'emoji' => ['required', 'string', 'max:32', 'regex:' . self::EMOJI, fn ($attribute, $value, $fail) => mb_strlen($value) > 10 ? $fail(__('One emoji at a time.')) : null],
        ]);

        $user = $request->user();
        [$target, $owner] = match ($data['type']) {
            'album' => $this->album($data['id'], $user),
            'post' => $this->post($data['id'], $user),
        };

        $existing = $target->reactions()->where('user_id', $user->id)->first();
        $unread = fn () => $owner?->unreadNotifications()->where('type', Reacted::class)->get()
            ->filter(fn ($notification) => $notification->data['reactor_id'] == $user->id
                && $notification->data['target_type'] === $data['type']
                && $notification->data['target_id'] == $target->id) ?? collect();

        if ($existing && $existing->emoji === $data['emoji']) {
            // Taken back before the poster saw it: the notification goes away too
            $existing->delete();
            $unread()->each->delete();
        } elseif ($existing) {
            $existing->update(['emoji' => $data['emoji']]);
            $unread()->each(fn ($notification) => $notification->update(['data' => ['emoji' => $data['emoji']] + $notification->data]));
        } else {
            $target->reactions()->create(['user_id' => $user->id, 'emoji' => $data['emoji']]);
            // Tell who posted it (bell and phone), not for your own or the demo account
            if ($owner && $owner->id !== $user->id && ! $owner->is_demo) {
                $owner->notify(new Reacted($user, $target, $data['emoji']));
                $url = $data['type'] === 'album' ? "/listings/{$target->id}" : "/forum/{$target->category_id}/{$target->id}";
                Notify::push($owner, 'like', fn () => [
                    'title' => $data['type'] === 'album'
                        ? __(':name reacted :emoji to your album', ['name' => $user->name, 'emoji' => $data['emoji']])
                        : __(':name reacted :emoji to your post', ['name' => $user->name, 'emoji' => $data['emoji']]),
                    'body' => $target->title,
                    'url' => $url,
                    'tag' => "reaction-{$data['type']}-{$target->id}",
                ]);
            }
        }

        return back();
    }

    // People in the album (admins who only look at it can't react), and who posted it
    private function album(int $id, User $user): array
    {
        $listing = Listing::with('users', 'user')->findOrFail($id);
        abort_unless($listing->users->contains('id', $user->id), 403, 'Unauthorized Action');

        return [$listing, $listing->user];
    }

    // Anyone who can see the post, and its author
    private function post(int $id, User $user): array
    {
        $post = Post::findOrFail($id);
        abort_unless($post->isVisibleTo($user), 403, 'Unauthorized Action');

        return [$post, $post->author()->first()];
    }
}

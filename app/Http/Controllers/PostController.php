<?php

namespace App\Http\Controllers;

use App\Support\Notify;
use App\Support\Locale;
use App\Support\ForumOrder;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Support\AlbumCover;
use App\Support\Media;
use App\Support\Mentions;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PostController extends Controller
{
    // The posts of a category the user can see
    public function index(Category $category)
    {
        $user = auth()->user();
        abort_if($category->hidedUser->contains('id', $user->id), 403, 'Unauthorized Action');

        $category->load(['posts.hidedUser', 'posts.author', 'posts.comments']);
        $category->posts->each->setRelation('category', $category);

        $posts = $category->posts
            ->filter(fn (Post $post) => $post->isVisibleTo($user))
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'cover' => Media::url($post->cover),
                'tags' => $this->tags($post->tags),
                'author' => $post->getRelation('author')?->name ?? 'Deleted user',
                'createdAt' => $post->created_at?->toIso8601String(),
                'views' => $post->counter,
                'comments' => $post->comments->count(),
                'isPrivate' => (bool) $post->is_private,
                'canEdit' => $this->canEdit($post, $user),
                'canDelete' => $post->author == $user->id,
            ])
            ->values();

        return Inertia::render('Forum/Category', [
            'category' => [
                'id' => $category->id,
                'title' => $category->title,
                'description' => $category->description,
                'logo' => Media::url($category->logo) ?? asset('images/no-image.png'),
                'canEdit' => $user->id == $category->created_by || $user->isAdmin == 1,
            ],
            'posts' => $posts,
            'canReorder' => $user->isAdmin == 1 && ForumOrder::enabled(),
        ]);
    }

    // Admins: the order of the posts of a category, from the "Reorder" mode of the category page
    public function reorder(Request $request, Category $category)
    {
        abort_unless($request->user()->isAdmin == 1, 403, 'Unauthorized Action');
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|distinct']);

        ForumOrder::save('posts', $category->posts()->pluck('id')->all(), $data['ids']);

        return back()->with('message', 'Order saved!');
    }

    // Show Create Form
    public function create(Category $category)
    {
        abort_if($category->hidedUser->contains('id', auth()->id()), 403, 'Unauthorized Action');

        return Inertia::render('Forum/PostForm', $this->formProps($category, null));
    }

    // Store Post Data
    public function store(Request $request, Category $category)
    {
        abort_if($category->hidedUser->contains('id', auth()->id()), 403, 'Unauthorized Action');

        $formFields = $this->validatePost($request);
        $formFields['is_private'] = $request->boolean('is_private') ? 1 : 0;
        $formFields['canEdit'] = $request->boolean('canEdit') ? 1 : 0;

        if ($request->hasFile('cover')) {
            $formFields['cover'] = AlbumCover::store($request->file('cover'), 'cover');
        }
        $attachments = $this->storeAttachments($request->file('attachments', []));
        $formFields['attachments'] = $attachments ? json_encode($attachments) : null;

        $formFields['author'] = auth()->id();
        $formFields['counter'] = 0;
        $formFields['category_id'] = $category->id;

        $post = Post::create($formFields);
        $post->hidedUser()->sync($request->input('hideFrom', []));

        $email_data = array(
            'name' => $formFields['title'],
            'postLink' => url('/forum/' . $category->id . '/' . $post->id),
        );
        Locale::for(User::firstWhere('email', 'matteo.gavoni@gmail.com'), fn () => Mail::send('/mails/new_post', $email_data, function ($message) {
            $message->to('matteo.gavoni@gmail.com', 'Admin')
                ->subject(__('New post - theboise.it'))
                ->from('info@theboise.it', 'TheBoise');
        }));

        return redirect("/forum/{$category->id}/{$post->id}")->with('message', 'Post published!');
    }

    // Show single Post
    public function show(Category $category, Post $post)
    {
        $user = auth()->user();
        if (! $post->isVisibleTo($user)) {
            abort(403, 'Unauthorized Action');
        }

        $post->increment('counter');
        $post->load(['comments.user', 'comments.likes.user']);
        // author() is the relationship ($post->author is the id column)
        $author = $post->author()->first();

        $attachments = collect(json_decode($post->attachments ?? '', true) ?? []);
        [$pdfs, $images] = $attachments->partition(fn ($path) => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf');

        $mentionable = $this->mentionable($post, $user);
        $named = $mentionable->concat([$user]);

        return Inertia::render('Forum/Post', [
            'category' => ['id' => $category->id, 'title' => $category->title],
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'content' => SafeHtml::clean($post->content),
                'cover' => Media::url($post->cover),
                'tags' => $this->tags($post->tags),
                'author' => $author?->name ?? 'Deleted user',
                'authorAvatar' => Media::url($author?->profilePicture),
                'createdAt' => $post->created_at?->toIso8601String(),
                'views' => $post->counter,
                'isPrivate' => (bool) $post->is_private,
                'lastChanges' => array_reverse(json_decode($post->last_changes ?? '', true) ?? []),
                'images' => $images->map(fn ($path) => Media::url($path))->values(),
                'pdfs' => $pdfs->values()->map(fn ($path, $index) => ['url' => Media::url($path), 'name' => 'Attachment ' . ($index + 1)]),
            ],
            'reactions' => $post->reactionSummary($user, ! $user->is_demo),
            // Who can be @mentioned in a comment: whoever can see the post
            'mentionable' => $mentionable->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name, 'avatar' => Media::url($person->profilePicture)])->values(),
            // Newest first, each with its replies (oldest first) underneath
            'comments' => ListingController::threads($post->comments->sortByDesc('created_at'), fn (PostComment $comment) => [
                'id' => $comment->id,
                'author' => $comment->user?->name ?? 'Deleted user',
                'avatar' => Media::url($comment->user?->profilePicture),
                'text' => $comment->comment,
                // The @names in it, shown in bold
                'mentions' => Mentions::find($comment->comment, $named)->pluck('name')->values(),
                'createdAt' => $comment->created_at?->toIso8601String(),
                'likes' => $comment->likeSummary($user),
            ]),
            'can' => [
                'edit' => $this->canEdit($post, $user),
                'delete' => $post->author == $user->id,
                'notify' => $post->isManagedBy($user),
                'pin' => $user->isAdmin == 1,
            ],
            'pinned' => \App\Support\Settings::get(\App\Support\Settings::PINNED_POST) == $post->id,
        ]);
    }

    // Store Comments Data
    public function comment(Request $request, Category $category, Post $post) {
        $user = auth()->id();
        $formFields = $request->validate([
            'comment' => 'required|string|max:5000',
            'parent_id' => 'nullable|integer', // a reply
        ]);
        $formFields['user_id'] = $user;
        $formFields['post_id'] = $post->id;
        // A reply: under the comment's thread, and who's answered is told
        [, $thread, $answered] = \Illuminate\Support\Facades\Schema::hasColumn('post_comments', 'parent_id')
            ? Mentions::replyTo($formFields['parent_id'] ?? null, PostComment::where('post_id', $post->id))
            : [null, null, null];
        unset($formFields['parent_id']);
        if ($thread) {
            $formFields['parent_id'] = $thread->id;
        }

        if (! $post->isVisibleTo(auth()->user())) {
            abort(403, 'Unauthorized Action');
        }

        // Notify the author and everyone who commented before, once each,
        // except the person commenting and whoever can't see the post
        $recipients = User::whereIn('id', $post->comments()->pluck('user_id')->push($post->author))
            ->where('id', '!=', $user)
            ->get()
            ->filter(fn ($recipient) => $post->isVisibleTo($recipient));

        $comment = PostComment::create($formFields);

        // Who's answered, and @Anna B (someone who can see the post): their own notification
        // instead of "replied in"
        $url = "/forum/{$category->id}/{$post->id}#comment-{$comment->id}";
        $repliedTo = $answered && $answered->id !== $user && $post->isVisibleTo($answered) ? collect([$answered]) : collect();
        $mentioned = Mentions::find($formFields['comment'], $this->mentionable($post, auth()->user()))->whereNotIn('id', $repliedTo->pluck('id'));
        Mentions::notify($repliedTo, auth()->user(), e($formFields['comment']), $post->title, $url, 'reply');
        Mentions::notify($mentioned, auth()->user(), e($formFields['comment']), $post->title, $url);

        Notify::push($recipients->whereNotIn('id', $mentioned->concat($repliedTo)->pluck('id')), 'forum', fn () => [
            'title' => __(':name replied in :title', ['name' => auth()->user()->name, 'title' => $post->title]),
            'body' => __('Tap to read it and answer.'),
            'url' => "/forum/{$category->id}/{$post->id}#comment-{$comment->id}",
            'tag' => 'post-comments-' . $post->id,
        ]);

        foreach ($recipients as $recipient) {
            if (! Notify::wants($recipient, 'forum', 'email')) {
                continue;
            }
            $email_data = array(
                'name' => $recipient->name,
                'email' => $recipient->email,
                'commentOfUser' => auth()->user()->name,
                'comment' => $formFields['comment'],
                'postTitle' => $post->title,
                'postLink' => url('/forum/' . $category->id . '/' . $post->id),
            );

            Locale::for($recipient, fn () => Mail::send('/mails/new_comment_post', $email_data, function ($message) use ($email_data, $post) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject(__('New comment on the post :title - theboise.it', ['title' => $post->title]))
                    ->from('info@theboise.it', 'TheBoise');
            }));
        }

        return back()->with('message', 'Comment added!');
    }

    // The old "manage" page: edit and delete are on the category page now
    public function manage(Category $category)
    {
        return redirect("/forum/{$category->id}");
    }

    // Show Edit Form
    public function edit(Category $category, Post $post)
    {
        abort_unless($this->canEdit($post, auth()->user()), 403, 'Unauthorized Action');

        return Inertia::render('Forum/PostForm', $this->formProps($category, $post));
    }

    // Send post
    // Admins: this post linked at the top of the home page (one at a time), or not any more
    public function pin(Request $request, Category $category, Post $post)
    {
        abort_unless($request->user()->isAdmin == 1, 403, 'Unauthorized Action');
        $pin = $request->isMethod('post');
        \App\Support\Settings::set(\App\Support\Settings::PINNED_POST, $pin ? $post->id : null);

        return back()->with('message', $pin ? 'Pinned on the home page' : 'Removed from the home page');
    }

    public function notify(Category $category, Post $post) {
        if (! $post->isManagedBy(auth()->user())) {
            abort(403, 'Unauthorized Action');
        }

        // Everyone who can see the post and is still around (in an album or on the site in the last 18 months)
        $allowedUsers = User::active()->where('id', '!=', auth()->id())->get()
            ->filter(fn ($user) => $post->isVisibleTo($user));

        Notify::push($allowedUsers, 'forum', fn () => [
            'title' => __(':name shared a post', ['name' => auth()->user()->name]),
            'body' => $post->title,
            'url' => "/forum/{$category->id}/{$post->id}",
            'tag' => 'post-' . $post->id,
        ]);

        foreach ($allowedUsers as $user) {
            if (! Notify::wants($user, 'forum', 'email')) {
                continue;
            }
            $email_data = array(
                'name' => $user->name,
                'email' => $user->email,
                'postTitle' => $post->title,
                'postLink' => url('/forum/' . $category->id . '/' . $post->id),
            );

            Locale::for($user, fn () => Mail::send('/mails/notify_post', $email_data, function ($message) use ($email_data, $post) {
                $message->to($email_data['email'], $email_data['name'])
                    ->subject(__('Check out this post in the forum: :title - theboise.it', ['title' => $post->title]))
                    ->from('info@theboise.it', 'TheBoise');
            }));
        }

        return redirect('/forum/' . $category->id . '/' . $post->id)->with('message', 'Everyone was notified!');
    }

    // Update Post Data
    public function update(Request $request, Category $category, Post $post)
    {
        $currentUser = auth()->user();
        abort_unless($this->canEdit($post, $currentUser), 403, 'Unauthorized Action');

        $formFields = $this->validatePost($request);

        // Only the author and admins can change who sees and edits the post
        if ($post->isManagedBy($currentUser)) {
            $formFields['is_private'] = $request->boolean('is_private') ? 1 : 0;
            $formFields['canEdit'] = $request->boolean('canEdit') ? 1 : 0;
            $post->hidedUser()->sync($request->input('hideFrom', []));
        }

        if ($request->hasFile('cover')) {
            if ($post->cover && Storage::disk('public')->exists($post->cover)) {
                Storage::disk('public')->delete($post->cover);
            }
            $formFields['cover'] = AlbumCover::store($request->file('cover'), 'cover');
        }

        // Attachments: keep the ones still in the form, delete the removed ones, add the new uploads
        $current = json_decode($post->attachments ?? '', true) ?? [];
        $keep = array_values(array_intersect($current, $request->input('keepAttachments', [])));
        foreach (array_diff($current, $keep) as $removed) {
            Storage::disk('public')->delete($removed);
        }
        $attachments = array_merge($keep, $this->storeAttachments($request->file('attachments', [])));
        $formFields['attachments'] = $attachments ? json_encode($attachments) : null;

        date_default_timezone_set('Europe/Rome');
        $lastChanges = json_decode($post->last_changes ?? '', true) ?? [];
        $lastChanges[] = date('Y-m-d H:i:s') . ': ' . $currentUser->name;
        $formFields['last_changes'] = json_encode($lastChanges);

        $post->update($formFields);

        return redirect("/forum/{$category->id}/{$post->id}")->with('message', 'Post updated!');
    }

    // Delete Post
    public function destroy(Category $category, Post $post) {
        // Make sure logged in user is owner
        $author = $post->author()->getResults();

        if($author->id != auth()->id()) {
            abort(403, 'Unauthorized Action');
        }

        if($post->cover && Storage::disk('public')->exists($post->cover)) {
            Storage::disk('public')->delete($post->cover);
        }

        // Posts without attachments have null here
        $attachments = json_decode($post->attachments ?? '', true) ?? [];

        foreach($attachments as $attachment) {
            if($attachment && Storage::disk('public')->exists($attachment)) {
                Storage::disk('public')->delete($attachment);
            }
        }

        $post->delete();

        return redirect('/forum/' . $category->id)->with('message', 'Post deleted');
    }

    // Author and admins, or anyone who can see the post if "others can edit" is on
    // Who can be @mentioned under a post: whoever can see it, except the writer and the demo account
    private function mentionable(Post $post, User $writer)
    {
        return User::where('is_demo', false)->where('id', '!=', $writer->id)->orderBy('name')->get()
            ->filter(fn (User $person) => $post->isVisibleTo($person))->values();
    }

    private function canEdit(Post $post, User $user): bool
    {
        return ($post->canEdit && $post->isVisibleTo($user)) || $post->isManagedBy($user);
    }

    private function validatePost(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string|max:50',
            'content' => 'required|string',
            'cover' => 'nullable|image|max:20480',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,gif,pdf|max:20480',
            'keepAttachments' => 'nullable|array',
            'hideFrom' => 'nullable|array',
            'hideFrom.*' => 'integer|exists:users,id',
        ]);

        return [
            'title' => $data['title'],
            'description' => $data['description'],
            'tags' => collect($data['tags'])->map(fn ($tag) => trim(str_replace(',', ' ', $tag)))->filter()->unique()->implode(', '),
            'content' => SafeHtml::clean($data['content']),
        ];
    }

    // Images are resized like album covers, PDFs are stored as they are
    private function storeAttachments(array $files): array
    {
        return collect($files)->map(fn ($file) => $file->getMimeType() === 'application/pdf'
            ? $file->store('attachments', 'public')
            : AlbumCover::store($file, 'attachments'))->all();
    }

    private function formProps(Category $category, ?Post $post): array
    {
        $attachments = json_decode($post?->attachments ?? '', true) ?? [];

        return [
            'category' => ['id' => $category->id, 'title' => $category->title],
            'post' => $post ? [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'tags' => $this->tags($post->tags),
                'content' => SafeHtml::clean($post->content),
                'cover' => Media::url($post->cover),
                'isPrivate' => (bool) $post->is_private,
                'canEdit' => (bool) $post->canEdit,
                'hideFrom' => $post->hidedUser()->pluck('users.id'),
                'attachments' => collect($attachments)->map(fn ($path) => [
                    'path' => $path,
                    'url' => Media::url($path),
                    'isPdf' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf',
                ]),
            ] : null,
            // Only the author and admins change privacy (anyone can when creating: they're the author)
            'canManage' => $post ? $post->isManagedBy(auth()->user()) : true,
            'users' => User::where('is_demo', false)->where('id', '!=', auth()->id())->orderBy('name')->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture)]),
        ];
    }

    private function tags(?string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
    }
}

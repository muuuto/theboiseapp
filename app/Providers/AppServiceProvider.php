<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\Comment;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short names stored in likes.likeable_type instead of class names
        Relation::morphMap([
            'album_comment' => Comment::class,
            'post_comment' => PostComment::class,
        ]);

        // The demo account never receives emails: cancel any email addressed only to demo users
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $recipients = collect($event->message->getTo())->map(fn ($address) => strtolower($address->getAddress()));
            $demoEmails = User::where('is_demo', true)->pluck('email')->map(fn ($email) => strtolower($email));

            if ($recipients->isNotEmpty() && $recipients->diff($demoEmails)->isEmpty()) {
                return false;
            }
        });

        Model::unguard();
        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }
    }
}

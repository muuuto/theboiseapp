<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\ListingUser;
use App\Models\User;
use App\Models\Comment;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Console\Commands\GenerateAnniversaryNotifications;
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

        View::composer('components.layout', function ($view) {
            if (auth()->check()) {
                $anniversaryNotification = GenerateAnniversaryNotifications::getMemoriesOfTheDay(true);

                if (isset($anniversaryNotification[auth()->id()])) {
                    $userAnniversaryNotification = $anniversaryNotification[auth()->id()];
                } else {
                    $userAnniversaryNotification = [];
                }
                
                $unseenListings = ListingUser::where('user_id', '=', auth()->user()->id)
                    ->where('seen', false)
                    ->get();
                
                $unseenListings = $unseenListings->map(fn($unseenListing) => $unseenListing->listing);

                $view->with([
                    'unseenListings' => $unseenListings,
                    'anniversaryNotification' => $userAnniversaryNotification,
                    'likeNotifications' => \App\Http\Middleware\HandleInertiaRequests::likeNotifications(auth()->user()),
                ]);
            }
        });

        Model::unguard();
        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }
    }
}

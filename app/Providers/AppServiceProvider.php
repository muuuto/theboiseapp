<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\ListingUser;

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
        View::composer('components.layout', function ($view) {
            if (auth()->check()) {
                $unseenListings = ListingUser::where('user_id', '=', auth()->user()->id)
                    ->where('seen', false)
                    ->get();
                
                $unseenListings = $unseenListings->map(fn($unseenListing) => $unseenListing->listing);

                $view->with('unseenListings', $unseenListings);
            }
        });

        Model::unguard();
        \URL::forceScheme('https');
    }
}

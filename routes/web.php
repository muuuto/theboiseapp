<?php

use App\Http\Controllers\ListingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SloganController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\AlbumMapController;
use App\Http\Controllers\ExpenseGroupController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpensePaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Common Resource Routes:
// index - Show all listings
// show - Show single listing
// create - Show form to create new listing
// store - Store new listing
// edit - Show form to edit listing
// update - Update listing
// destroy - Delete listing

// All Listings
Route::get('/', [ListingController::class, 'index'])->middleware('auth');

// Show Create Form
Route::get('/listings/create', [ListingController::class, 'create'])->middleware('auth');

// Store Listing Data
Route::post('/listings', [ListingController::class, 'store'])->middleware('auth');

// Show Edit Form
Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->middleware('auth');

// Update Listing
Route::put('/listings/{listing}', [ListingController::class, 'update'])->middleware('auth');

// Delete Listing
Route::delete('/listings/{listing}', [ListingController::class, 'destroy'])->middleware('auth');

// Manage Listings
Route::get('/listings/manage', [ListingController::class, 'manage'])->middleware('auth');

// Single Listing
Route::get('/listings/{listing}', [ListingController::class, 'show'])->middleware('auth');

// Show Register/Create Form
// Registration is invite-only: /register/{token} comes from an invite link
Route::get('/register/{token?}', [UserController::class, 'create'])->middleware('guest');

// Create New User
Route::post('/users', [UserController::class, 'store'])->middleware('guest');

// Log User Out
Route::post('/logout', [UserController::class, 'logout']);

// Show Login Form
Route::get('/login', [UserController::class, 'login'])->name('login')->middleware('guest');

// Demo account for recruiters: secret link in the CV, read-only (see DEMO_LOGIN_SECRET)
Route::get('/demo/{secret}', [UserController::class, 'demo'])->middleware(['guest', 'throttle:10,1']);

// "Only albums I posted" on the album list and the map
Route::put('/account/album-filter', [AccountController::class, 'albumFilter'])->middleware(['auth', 'not-demo']);
Route::put('/account/home', [AccountController::class, 'home'])->middleware(['auth', 'not-demo']);

// Passkeys: log in with Face ID / fingerprint on the phone (the password stays as a backup)
Route::middleware(['auth', 'not-demo'])->group(function () {
    Route::post('/account/passkeys/options', [App\Http\Controllers\PasskeyController::class, 'registerOptions']);
    Route::post('/account/passkeys', [App\Http\Controllers\PasskeyController::class, 'register']);
    Route::delete('/account/passkeys/{passkey}', [App\Http\Controllers\PasskeyController::class, 'destroy']);
});
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::post('/passkeys/login/options', [App\Http\Controllers\PasskeyController::class, 'loginOptions']);
    Route::post('/passkeys/login', [App\Http\Controllers\PasskeyController::class, 'login']);
});

// Phone notifications: this device on/off, test, remove a device, what to receive
Route::middleware(['auth', 'not-demo'])->prefix('push')->group(function () {
    Route::post('/subscriptions', [App\Http\Controllers\PushController::class, 'store']);
    Route::delete('/subscriptions', [App\Http\Controllers\PushController::class, 'destroy']);
    Route::delete('/subscriptions/{subscription}', [App\Http\Controllers\PushController::class, 'forget']);
    Route::post('/test', [App\Http\Controllers\PushController::class, 'test']);
    Route::put('/settings', [App\Http\Controllers\PushController::class, 'settings']);
});

// Language: English or Italian (also before logging in)
Route::put('/locale', [UserController::class, 'locale']);

// Theme: light, dark or follow the device
Route::put('/account/theme', [UserController::class, 'theme'])->middleware(['auth', 'not-demo']);

// Log In User
Route::post('/users/authenticate', [UserController::class, 'authenticate']);

// Show Create SloganForm
Route::get('/slogan/create', [SloganController::class, 'create'])->middleware(['auth', 'not-demo']);

// Store Slogan Data
Route::post('/slogan', [SloganController::class, 'store'])->middleware(['auth', 'not-demo']);

// Zecca Gay page
// The answer is always the same (2% of the time the site "has a technical problem"). Public page
Route::get('/zeccaGay', function() {
    $answers = ["yes", "of course", "mega gay", "giga gay", "i hate him and he's gay", "definitely", "100% gay", "yes and also bald", "site working correctly, he's gay", "shibsha gay", "we both already know the answer", "yep", "aye", "yeah", "indeed", "undoubtedly", "unquestionably", "absolutely", "hell yeah"];
    $broken = rand(1, 100) > 98;

    return Inertia\Inertia::render('Zecca', [
        'answer' => __($broken ? "no, but the site might have some technical problem, wanna try again?" : $answers[array_rand($answers)]),
        'broken' => $broken,
    ]);
});

// monitoring
Route::get('/monitor', [UserController::class, 'monitor'])->middleware('auth');

// Show Create Form
Route::post('/listings/comments', [ListingController::class, 'comments'])->middleware(['auth', 'not-demo']);


// Album preview in emails (JPEG): no login, the signed link is the key
Route::get('/mail/cover/{listing}', function (App\Models\Listing $listing) {
    $file = App\Support\MailCover::jpeg($listing);
    abort_unless($file, 404);

    return response()->file($file, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=31536000']);
})->middleware('signed')->name('mail.cover');

// Like / unlike a comment
Route::post('/likes', [LikeController::class, 'toggle'])->middleware(['auth', 'not-demo']);

// React to an album or a forum post with an emoji
Route::post('/reactions', [App\Http\Controllers\ReactionController::class, 'react'])->middleware(['auth', 'not-demo']);

// The notification bell was opened: its notifications are read
Route::post('/notifications/read', function (Illuminate\Http\Request $request) {
    $request->user()->unreadNotifications->markAsRead();

    return $request->header('X-Inertia') ? back() : response()->noContent();
})->middleware('auth');

// A like or reaction clicked in the bell: it's been seen, so it leaves the bell, then on to what
// it's about. The bell links here with the #comment-12 of the comment, preserveFragment keeps it
Route::get('/notifications/{id}', function (Illuminate\Http\Request $request, string $id) {
    $notification = $request->user()->notifications()->find($id);
    if (! $notification) {
        // Already opened (a second click, an old tab)
        return redirect('/');
    }
    $notification->delete();

    return redirect(strtok($notification->data['url'], '#'))->preserveFragment();
})->middleware('auth')->whereUuid('id');

// TheBoise Wrapped: your year in albums
Route::get('/wrapped', [App\Http\Controllers\WrappedController::class, 'index'])->middleware('auth');
Route::get('/wrapped/{year}', [App\Http\Controllers\WrappedController::class, 'show'])->middleware('auth')->whereNumber('year');

// All the user's albums on a map
Route::get('/map', [AlbumMapController::class, 'index'])->middleware('auth');

// Admin: the album tags library (rename, merge, other spellings, linked account)
Route::middleware(['auth', 'can:admin'])->prefix('admin/tags')->group(function () {
    Route::get('/', [App\Http\Controllers\TagController::class, 'index']);
    Route::post('/', [App\Http\Controllers\TagController::class, 'store']);
    Route::put('/{tag}', [App\Http\Controllers\TagController::class, 'update']);
    Route::post('/distinct', [App\Http\Controllers\TagController::class, 'distinct']);
    Route::post('/{tag}/merge', [App\Http\Controllers\TagController::class, 'merge']);
    Route::delete('/{tag}', [App\Http\Controllers\TagController::class, 'destroy']);
});

// Admin: confirm or fix the album coordinates suggested from the location text
Route::middleware(['auth', 'can:admin'])->prefix('admin/locations')->group(function () {
    Route::get('/', [AlbumMapController::class, 'review']);
    Route::post('/confirm-all', [AlbumMapController::class, 'confirmAll']);
    Route::post('/{place}/confirm', [AlbumMapController::class, 'confirm']);
    Route::delete('/{place}', [AlbumMapController::class, 'clear']);
});

// Reset password form
Route::get('/forgot-password', [UserController::class, 'forgotPasswordShow'])->middleware('guest')->name('password.request');

// manage forgotten password form
Route::post('/forgot-password', [UserController::class, 'forgotPasswordManage'])->middleware('guest')->name('password.email');

// form for the actual password reset
Route::get('/reset-password/{token}', [UserController::class, 'forgotPasswordReset'])->middleware('guest')->name('password.reset');

Route::post('/reset-password', [UserController::class, 'forgotPasswordUpdate'])->middleware('guest')->name('password.update');

// Account
Route::get('/account', [AccountController::class, 'index'])->middleware('auth');

// Update account
Route::put('/account/update', [AccountController::class, 'update'])->middleware(['auth', 'not-demo']);

// Change password
Route::put('/account/password', [AccountController::class, 'password'])->middleware(['auth', 'not-demo']);

// Add media to wallet
Route::put('/account/addWallet', [AccountController::class, 'addWallet'])->middleware(['auth', 'not-demo']);



// All Categories
Route::get('/forum', [CategoryController::class, 'index'])->middleware(['auth', 'not-demo'])->name('forum.categories.index');

// Show Create Categories
Route::get('/forum/category/create', [CategoryController::class, 'create'])->middleware(['auth', 'not-demo'])->name('forum.categories.create');

// Store Categories Data
Route::post('/forum/category/store', [CategoryController::class, 'store'])->middleware(['auth', 'not-demo'])->name('forum.categories.store');

// Show Edit Categories
Route::get('/forum/{category}/edit', [CategoryController::class, 'edit'])->middleware(['auth', 'not-demo'])->name('forum.categories.edit');

// Order of the categories and of the posts of a category (admins, drag and drop)
Route::put('/forum/order', [CategoryController::class, 'reorder'])->middleware(['auth', 'not-demo'])->name('forum.categories.order');
Route::put('/forum/{category}/order', [PostController::class, 'reorder'])->middleware(['auth', 'not-demo'])->name('forum.posts.order');

// Update Categories
Route::put('/forum/{category}', [CategoryController::class, 'update'])->middleware(['auth', 'not-demo'])->name('forum.categories.update');

// Delete Categories
Route::delete('/forum/{category}', [CategoryController::class, 'destroy'])->middleware(['auth', 'not-demo'])->name('forum.categories.delete');

// Manage Categories
Route::get('/forum/category/manage', [CategoryController::class, 'manage'])->middleware(['auth', 'not-demo'])->name('forum.categories.manage');


// All Posts in Category
Route::get('/forum/{category}', [PostController::class, 'index'])->middleware(['auth', 'not-demo'])->name('forum.posts.index');

// Show Create Post
Route::get('/forum/{category}/create', [PostController::class, 'create'])->middleware(['auth', 'not-demo'])->name('forum.posts.create');

// Store Post Data
Route::post('/forum/{category}/store', [PostController::class, 'store'])->middleware(['auth', 'not-demo'])->name('forum.posts.store');

// Show Edit Post
Route::get('/forum/{category}/{post}/edit', [PostController::class, 'edit'])->middleware(['auth', 'not-demo'])->name('forum.posts.edit');

// Update Post
Route::put('/forum/{category}/{post}/update', [PostController::class, 'update'])->middleware(['auth', 'not-demo'])->name('forum.posts.update');

// Delete Post
Route::delete('/forum/{category}/{post}/delete', [PostController::class, 'destroy'])->middleware(['auth', 'not-demo'])->name('forum.posts.delete');

// Manage Post
Route::get('/forum/{category}/manage', [PostController::class, 'manage'])->middleware(['auth', 'not-demo'])->name('forum.posts.manage');

// Show Post in Category
Route::get('/forum/{category}/{post}', [PostController::class, 'show'])->middleware(['auth', 'not-demo'])->name('forum.posts.show');

// Show Create Form
Route::post('/forum/{category}/{post}/comment', [PostController::class, 'comment'])->middleware(['auth', 'not-demo'])->name('forum.posts.comment');

// Pin a post at the top of the home page (admins)
Route::post('/forum/{category}/{post}/pin', [PostController::class, 'pin'])->middleware(['auth', 'not-demo']);
Route::delete('/forum/{category}/{post}/pin', [PostController::class, 'pin'])->middleware(['auth', 'not-demo']);

// Send Post
Route::post('/forum/{category}/{post}/notify', [PostController::class, 'notify'])->middleware(['auth', 'not-demo'])->name('forum.posts.notify');

// Gym tracker: for the people the admins allow; everyone sees only their own workouts, the
// exercises are the same for everyone
Route::middleware(['auth', 'not-demo'])->prefix('gym')->group(function () {
    Route::get('/', [App\Http\Controllers\GymController::class, 'index']);
    Route::post('/workouts', [App\Http\Controllers\GymController::class, 'store']);
    Route::get('/workouts/{workout}', [App\Http\Controllers\GymController::class, 'show']);
    Route::put('/workouts/{workout}', [App\Http\Controllers\GymController::class, 'update']);
    Route::delete('/workouts/{workout}', [App\Http\Controllers\GymController::class, 'destroy']);
    Route::post('/exercises', [App\Http\Controllers\GymController::class, 'storeExercise']);
    Route::get('/exercises/{exercise}', [App\Http\Controllers\GymController::class, 'exercise'])->whereNumber('exercise');
    Route::get('/records', [App\Http\Controllers\GymController::class, 'records']);
    Route::put('/share', [App\Http\Controllers\GymController::class, 'share']);

    // Managing the exercises: names (English, Italian), group, photo, default sets and reps
    Route::middleware('can:admin')->group(function () {
        Route::get('/exercises', [App\Http\Controllers\GymController::class, 'exercises']);
        Route::put('/exercises/{exercise}', [App\Http\Controllers\GymController::class, 'updateExercise']);
        Route::post('/exercises/{exercise}/photo', [App\Http\Controllers\GymController::class, 'exercisePhoto']);
        Route::delete('/exercises/{exercise}/photo', [App\Http\Controllers\GymController::class, 'removeExercisePhoto']);
        Route::delete('/exercises/{exercise}', [App\Http\Controllers\GymController::class, 'destroyExercise']);
    });
});

// Upcoming trips: only the people going see one, never the demo account
Route::middleware(['auth', 'not-demo'])->prefix('trips')->scopeBindings()->group(function () {
    Route::get('/', [App\Http\Controllers\TripController::class, 'index']);
    Route::get('/create', [App\Http\Controllers\TripController::class, 'create']);
    Route::post('/', [App\Http\Controllers\TripController::class, 'store']);
    Route::get('/{trip}', [App\Http\Controllers\TripController::class, 'show']);
    Route::get('/{trip}/edit', [App\Http\Controllers\TripController::class, 'edit']);
    Route::put('/{trip}', [App\Http\Controllers\TripController::class, 'update']);
    Route::delete('/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);
    Route::post('/{trip}/options', [App\Http\Controllers\TripController::class, 'addOption']);
    Route::delete('/{trip}/options/{option}', [App\Http\Controllers\TripController::class, 'deleteOption']);
    Route::post('/{trip}/options/{option}/vote', [App\Http\Controllers\TripController::class, 'vote']);
    Route::post('/{trip}/options/{option}/choose', [App\Http\Controllers\TripController::class, 'choose']);
    Route::post('/{trip}/items', [App\Http\Controllers\TripController::class, 'addItem']);
    Route::put('/{trip}/items/{item}', [App\Http\Controllers\TripController::class, 'updateItem']);
    Route::delete('/{trip}/items/{item}', [App\Http\Controllers\TripController::class, 'deleteItem']);
    Route::post('/{trip}/expenses', [App\Http\Controllers\TripController::class, 'expenses']);
    Route::post('/{trip}/documents', [App\Http\Controllers\TripDocumentController::class, 'store']);
    Route::get('/{trip}/documents/{document}', [App\Http\Controllers\TripDocumentController::class, 'show']);
    Route::delete('/{trip}/documents/{document}', [App\Http\Controllers\TripDocumentController::class, 'destroy']);
    Route::get('/{trip}/album', [App\Http\Controllers\TripController::class, 'makeAlbum']);
});

// Shared expenses (like Splitwise): only the members of a group see it, never the demo account
Route::middleware(['auth', 'not-demo'])->prefix('expenses')->scopeBindings()->group(function () {
    Route::get('/', [ExpenseGroupController::class, 'index']);
    // The day's exchange rate, while filling in an expense in another currency
    Route::get('/rate', [ExpenseController::class, 'rate']);
    Route::get('/create', [ExpenseGroupController::class, 'create']);
    Route::post('/', [ExpenseGroupController::class, 'store']);
    Route::get('/{group}', [ExpenseGroupController::class, 'show']);
    Route::get('/{group}/edit', [ExpenseGroupController::class, 'edit']);
    Route::put('/{group}', [ExpenseGroupController::class, 'update']);
    Route::delete('/{group}', [ExpenseGroupController::class, 'destroy']);

    Route::get('/{group}/expenses/create', [ExpenseController::class, 'create']);
    Route::post('/{group}/expenses', [ExpenseController::class, 'store']);
    Route::get('/{group}/expenses/{expense}/edit', [ExpenseController::class, 'edit']);
    Route::put('/{group}/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/{group}/expenses/{expense}', [ExpenseController::class, 'destroy']);

    Route::post('/{group}/payments', [ExpensePaymentController::class, 'store']);
    Route::delete('/{group}/payments/{payment}', [ExpensePaymentController::class, 'destroy']);
});

// Maintenance (admin only): clear caches, backups and migrations without SSH
Route::middleware(['auth', 'can:admin'])->prefix('admin/maintenance')->group(function () {
    Route::get('/', [MaintenanceController::class, 'index']);
    Route::post('/test-email', [MaintenanceController::class, 'testEmail']);
    Route::post('/album-posting', [MaintenanceController::class, 'albumPostingMode']);
    Route::put('/decorations', [MaintenanceController::class, 'saveDecorations']);
    Route::post('/decorations/custom', [MaintenanceController::class, 'storeDecoration']);
    Route::put('/decorations/custom/{id}', [MaintenanceController::class, 'updateDecoration']);
    Route::delete('/decorations/custom/{id}', [MaintenanceController::class, 'destroyDecoration']);
    Route::put('/album-posting/everyone', [MaintenanceController::class, 'albumPostingEveryone']);
    Route::put('/gym/everyone', [MaintenanceController::class, 'gymEveryone']);
    Route::put('/gym/{user}', [MaintenanceController::class, 'gymAccess']);
    Route::put('/album-posting/{user}', [MaintenanceController::class, 'albumPostingPermission']);
    Route::post('/{action}', [MaintenanceController::class, 'run']);
    Route::get('/backups/{file}', [MaintenanceController::class, 'download']);
});

// Invite links (admin only), listed on the maintenance page
Route::middleware(['auth', 'can:admin'])->prefix('admin/invites')->group(function () {
    Route::post('/', [InviteController::class, 'store']);
    Route::delete('/{invite}', [InviteController::class, 'destroy']);
});

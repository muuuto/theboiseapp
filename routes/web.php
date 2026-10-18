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
        'answer' => $broken ? "no, but the site might have some technical problem, wanna try again?" : $answers[array_rand($answers)],
        'broken' => $broken,
    ]);
});

// monitoring
Route::get('/monitor', [UserController::class, 'monitor'])->middleware('auth');

// Show Create Form
Route::post('/listings/comments', [ListingController::class, 'comments'])->middleware(['auth', 'not-demo']);

// Like / unlike a comment
Route::post('/likes', [LikeController::class, 'toggle'])->middleware(['auth', 'not-demo']);

// The notification bell was opened: its notifications are read
Route::post('/notifications/read', function (Illuminate\Http\Request $request) {
    $request->user()->unreadNotifications->markAsRead();

    return $request->header('X-Inertia') ? back() : response()->noContent();
})->middleware('auth');

// All the user's albums on a map
Route::get('/map', [AlbumMapController::class, 'index'])->middleware('auth');

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

// Send Post
Route::post('/forum/{category}/{post}/notify', [PostController::class, 'notify'])->middleware(['auth', 'not-demo'])->name('forum.posts.notify');

// Maintenance (admin only): clear caches, backups and migrations without SSH
Route::middleware(['auth', 'can:admin'])->prefix('admin/maintenance')->group(function () {
    Route::get('/', [MaintenanceController::class, 'index']);
    Route::post('/{action}', [MaintenanceController::class, 'run']);
    Route::get('/backups/{file}', [MaintenanceController::class, 'download']);
});

// Invite links (admin only), listed on the maintenance page
Route::middleware(['auth', 'can:admin'])->prefix('admin/invites')->group(function () {
    Route::post('/', [InviteController::class, 'store']);
    Route::delete('/{invite}', [InviteController::class, 'destroy']);
});

<?php

use App\Http\Controllers\ListingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SloganController;
use App\Http\Controllers\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Listing;

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
Route::get('/register', [UserController::class, 'create'])->middleware('guest');

// Create New User
Route::post('/users', [UserController::class, 'store']);

// Log User Out
Route::post('/logout', [UserController::class, 'logout']);

// Show Login Form
Route::get('/login', [UserController::class, 'login'])->name('login')->middleware('guest');

// Show Login Form
Route::get('/guest', [UserController::class, 'guest'])->name('guest');

// Log In User
Route::post('/users/authenticate', [UserController::class, 'authenticate']);

// Show Create SloganForm
Route::get('/slogan/create', [SloganController::class, 'create'])->middleware('auth');

// Store Slogan Data
Route::post('/slogan', [SloganController::class, 'store'])->middleware('auth');

// Zecca Gay page
Route::get('/zeccaGay', function() {
    return view('listings.zecca-gay');
});

// monitoring
Route::get('/monitor', [UserController::class, 'monitor'])->middleware('auth');

// Show Create Form
Route::post('/listings/comments', [ListingController::class, 'comments'])->middleware('auth');

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
Route::put('/account/update', [AccountController::class, 'update'])->middleware('auth');

// Add media to wallet
Route::put('/account/addWallet', [AccountController::class, 'addWallet'])->middleware('auth');

// Update all the users with blank avatar
// Route::put('/account/linkNoImage', [AccountController::class, 'linkNoImage'])->middleware('auth');


// All Categories
Route::get('/forum', [CategoryController::class, 'index'])->middleware('auth')->name('forum.categories.index');

// Show Create Categories
Route::get('/forum/category/create', [CategoryController::class, 'create'])->middleware('auth')->name('forum.categories.create');

// Store Categories Data
Route::post('/forum/category/store', [CategoryController::class, 'store'])->middleware('auth')->name('forum.categories.store');

// Show Edit Categories
Route::get('/forum/{category}/edit', [CategoryController::class, 'edit'])->middleware('auth')->name('forum.categories.edit');

// Update Categories
Route::put('/forum/{category}', [CategoryController::class, 'update'])->middleware('auth')->name('forum.categories.update');

// Delete Categories
Route::delete('/forum/{category}', [CategoryController::class, 'destroy'])->middleware('auth')->name('forum.categories.delete');

// Manage Categories
Route::get('/forum/category/manage', [CategoryController::class, 'manage'])->middleware('auth')->name('forum.categories.manage');


// All Posts in Category
Route::get('/forum/{category}', [PostController::class, 'index'])->middleware('auth')->name('forum.posts.index');

// Show Create Post
Route::get('/forum/{category}/create', [PostController::class, 'create'])->middleware('auth')->name('forum.posts.create');

// Store Post Data
Route::post('/forum/{category}/store', [PostController::class, 'store'])->middleware('auth')->name('forum.posts.store');

// Show Edit Post
Route::get('/forum/{category}/{post}/edit', [PostController::class, 'edit'])->middleware('auth')->name('forum.posts.edit');

// Update Post
Route::put('/forum/{category}/{post}/update', [PostController::class, 'update'])->middleware('auth')->name('forum.posts.update');

// Delete Post
Route::delete('/forum/{category}/{post}/delete', [PostController::class, 'destroy'])->middleware('auth')->name('forum.posts.delete');

// Manage Post
Route::get('/forum/{category}/manage', [PostController::class, 'manage'])->middleware('auth')->name('forum.posts.manage');

// Show Post in Category
Route::get('/forum/{category}/{post}', [PostController::class, 'show'])->middleware('auth')->name('forum.posts.show');

// Show Create Form
Route::post('/forum/{category}/{post}/comment', [PostController::class, 'comment'])->middleware('auth')->name('forum.posts.comment');

// Send Post
Route::get('/forum/{category}/{post}/notify', [PostController::class, 'notify'])->middleware('auth')->name('forum.posts.notify');

Route::get('/test', function() {
    // Artisan::call('listings:send-reminders');
});

Route::get('/clear-cache', function() {
    $exitCode = Artisan::call('cache:clear');
    $exitCode = Artisan::call('optimize:clear');
    // return what you want
});
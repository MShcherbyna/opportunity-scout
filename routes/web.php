<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Renders the same login page Fortify serves at /login (not a redirect to
// it) so "/" is directly the login screen. The `guest` middleware still
// sends an already-authenticated visitor onward, same as /login does.
Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('home');

Route::inertia('privacy', 'legal/Privacy')->name('privacy');
Route::inertia('data-deletion', 'legal/DataDeletion')->name('data-deletion');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::inertia('posts', 'Posts')->name('posts.index');

    // The post itself is frontend mock data; this route only forwards the
    // id from the URL so the page can look it up client-side.
    Route::get('posts/{post}', fn (string $post) => Inertia::render('PostDetail', [
        'postId' => (int) $post,
    ]))->name('posts.show');

    Route::inertia('settings/scraping', 'scout-settings/Scraping')->name('settings.scraping');
    Route::inertia('settings/ai-filtering', 'scout-settings/AiFiltering')->name('settings.aiFiltering');
    Route::inertia('settings/threads', 'scout-settings/Threads')->name('settings.threads');
    Route::inertia('settings/integrations', 'scout-settings/Integrations')->name('settings.integrations');
});

require __DIR__.'/settings.php';

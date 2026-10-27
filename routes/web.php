<?php

use App\Http\Controllers\Legal\DataDeletionController;
use App\Http\Controllers\Threads\DashboardController;
use App\Http\Controllers\Threads\PostController;
use App\Http\Controllers\Threads\PostRelevanceController;
use App\Http\Controllers\Threads\ReplyTemplateController;
use App\Http\Controllers\Threads\ScrapingSettingsController;
use App\Http\Controllers\Threads\SearchRunController;
use App\Http\Controllers\Threads\ThreadsConnectionController;
use App\Http\Controllers\Threads\ThreadsKeywordController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Renders the same login page Fortify serves at /login (not a redirect to
// it) so "/" is directly the login screen. The `guest` middleware still
// sends an already-authenticated visitor onward, same as /login does.
Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('home');

Route::inertia('privacy', 'legal/Privacy')->name('privacy');
Route::get('data-deletion', [DataDeletionController::class, 'show'])->name('data-deletion');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('posts/{post}', [PostController::class, 'show'])->whereNumber('post')->name('posts.show');
    Route::patch('posts/{post}/relevant', [PostRelevanceController::class, 'update'])->whereNumber('post')->name('posts.relevant.update');

    Route::post('search-runs', [SearchRunController::class, 'store'])
        ->middleware('throttle:search-runs')
        ->name('searchRuns.store');

    Route::prefix('auth/threads')->name('threads.')->group(function () {
        Route::post('connect', [ThreadsConnectionController::class, 'connect'])
            ->middleware('throttle:threads-oauth')
            ->name('connect');
        Route::get('callback', [ThreadsConnectionController::class, 'callback'])
            ->middleware('throttle:threads-oauth')
            ->name('callback');
        Route::delete('connection', [ThreadsConnectionController::class, 'destroy'])->name('disconnect');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/settings/scraping');

        Route::get('scraping', [ScrapingSettingsController::class, 'show'])->name('scraping');
        Route::patch('search-settings', [ScrapingSettingsController::class, 'update'])->name('searchSettings.update');
        Route::get('reply-template', [ReplyTemplateController::class, 'show'])->name('replyTemplate.show');
        Route::patch('reply-template', [ReplyTemplateController::class, 'update'])->name('replyTemplate.update');
        Route::inertia('ai-filtering', 'scout-settings/AiFiltering')->name('aiFiltering');
        Route::get('threads', [ThreadsConnectionController::class, 'show'])->name('threads');
        Route::inertia('integrations', 'scout-settings/Integrations')->name('integrations');

        Route::middleware('throttle:threads-keywords')->group(function () {
            Route::post('keywords', [ThreadsKeywordController::class, 'store'])->name('keywords.store');
            Route::patch('keywords/{keyword}', [ThreadsKeywordController::class, 'update'])
                ->whereNumber('keyword')
                ->name('keywords.update');
            Route::delete('keywords/{keyword}', [ThreadsKeywordController::class, 'destroy'])
                ->whereNumber('keyword')
                ->name('keywords.destroy');
        });
    });
});

require __DIR__.'/account.php';

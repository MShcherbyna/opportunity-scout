<?php

use App\Http\Controllers\Threads\MetaCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meta callbacks
|--------------------------------------------------------------------------
|
| Registered without the `web` group: no session, no cookies, no CSRF.
| Authenticity comes from the signed request.
|
*/

Route::post('auth/threads/uninstall', [MetaCallbackController::class, 'uninstall'])->name('threads.uninstall');
Route::post('auth/threads/delete', [MetaCallbackController::class, 'dataDeletion'])->name('threads.dataDeletion');

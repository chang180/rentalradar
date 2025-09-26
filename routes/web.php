<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Handle favicon requests to prevent 302 redirects
Route::get('/favicon.ico', function () {
    return response()->file(public_path('favicon.ico'));
});


Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

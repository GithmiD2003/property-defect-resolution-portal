<?php

use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::resource('properties', PropertyController::class)
        ->only([
            'index',
            'create',
            'store',
            'show',
            'edit',
            'update',
        ]);

    Route::post(
        'properties/{property}/rooms',
        [RoomController::class, 'store'],
    )->name('properties.rooms.store');
});

require __DIR__.'/settings.php';

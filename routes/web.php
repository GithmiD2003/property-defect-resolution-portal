<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyMemberController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post(
    'properties/{property}/members',
    [PropertyMemberController::class, 'store'],
)->name('properties.members.store');

Route::delete(
    'properties/{property}/members/{user}',
    [PropertyMemberController::class, 'destroy'],
)->name('properties.members.destroy');

Route::get('invitations', [InvitationController::class, 'index'])
    ->name('invitations.index');

Route::post('invitations', [InvitationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('invitations.store');

Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
    ->name('invitations.destroy');

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

Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get(
        'invitations/{invitation}/accept',
        [AcceptInvitationController::class, 'show'],
    )->name('invitations.accept.show');

    Route::post(
        'invitations/{invitation}/accept',
        [AcceptInvitationController::class, 'store'],
    )->name('invitations.accept.store');
});

require __DIR__.'/settings.php';

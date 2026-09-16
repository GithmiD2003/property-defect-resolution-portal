<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\DefectAssignmentController;
use App\Http\Controllers\DefectCommentController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\DefectPhotoController;
use App\Http\Controllers\DefectReviewController;
use App\Http\Controllers\DefectWorkflowController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyMemberController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::post(
        'defects/{defect}/comments',
        [DefectCommentController::class, 'store'],
    )->middleware('throttle:10,1')->name('defects.comments.store');

    Route::patch(
        'defects/{defect}/verify',
        [DefectReviewController::class, 'verify'],
    )->name('defects.verify');

    Route::patch(
        'defects/{defect}/reopen',
        [DefectReviewController::class, 'reopen'],
    )->name('defects.reopen');

    Route::patch(
        'defects/{defect}/start',
        [DefectWorkflowController::class, 'start'],
    )->name('defects.start');

    Route::post(
        'defects/{defect}/repair',
        [DefectWorkflowController::class, 'repair'],
    )->middleware('throttle:10,1')->name('defects.repair');

    Route::patch(
        'defects/{defect}/assignment',
        [DefectAssignmentController::class, 'update'],
    )->name('defects.assignment.update');

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

    Route::post(
        'properties/{property}/members',
        [PropertyMemberController::class, 'store'],
    )->name('properties.members.store');

    Route::delete(
        'properties/{property}/members/{user}',
        [PropertyMemberController::class, 'destroy'],
    )->name('properties.members.destroy');

    Route::get('defects', [DefectController::class, 'index'])
        ->name('defects.index');

    Route::get(
        'properties/{property}/defects/create',
        [DefectController::class, 'create'],
    )->name('properties.defects.create');

    Route::post(
        'properties/{property}/defects',
        [DefectController::class, 'store'],
    )->middleware('throttle:10,1')->name('properties.defects.store');

    Route::get('defects/{defect}', [DefectController::class, 'show'])
        ->name('defects.show');

    Route::get(
        'defect-photos/{photo}',
        [DefectPhotoController::class, 'show'],
    )->name('defect-photos.show');

    Route::get('invitations', [InvitationController::class, 'index'])
        ->name('invitations.index');

    Route::post('invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.store');

    Route::delete(
        'invitations/{invitation}',
        [InvitationController::class, 'destroy'],
    )->name('invitations.destroy');
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

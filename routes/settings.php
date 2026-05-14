<?php

use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/ai-providers', [AiSettingsController::class, 'editProviders'])
        ->name('ai-providers.edit');
    Route::post('settings/ai-providers', [AiSettingsController::class, 'updateProviders'])
        ->name('ai-providers.update');
    Route::post('settings/ai-providers/test', [AiSettingsController::class, 'testProvider'])
        ->name('ai-providers.test');

    Route::get('settings/system-prompt', [AiSettingsController::class, 'editPrompt'])
        ->name('system-prompt.edit');
    Route::post('settings/system-prompt', [AiSettingsController::class, 'updatePrompt'])
        ->name('system-prompt.update');
});

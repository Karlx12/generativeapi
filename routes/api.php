<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenerativeController;

Route::prefix('v1')->group(function () {
    Route::prefix('marketing/generation')->group(function () {
        Route::post('facebook', [GenerativeController::class, 'generateFacebook']);
        Route::post('instagram', [GenerativeController::class, 'generateInstagram']);
        Route::post('podcast', [GenerativeController::class, 'generatePodcast']);
        Route::post('image', [GenerativeController::class, 'generateImage']);
        Route::post('audio', [GenerativeController::class, 'generateAudio']);
        Route::get('audio/list', [GenerativeController::class, 'listAudios']);
        Route::post('audio/send', [GenerativeController::class, 'sendAudio']);
        Route::get('audio/{id}', [GenerativeController::class, 'downloadAudio']);
        Route::post('video', [GenerativeController::class, 'generateVideo']);
    });
});

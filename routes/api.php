<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenerativeController;

Route::prefix('/generation')->group(function () {
    
    // Text Generation
    Route::post('facebook', [GenerativeController::class, 'generateFacebook']);
    Route::post('instagram', [GenerativeController::class, 'generateInstagram']);
    Route::post('podcast', [GenerativeController::class, 'generatePodcast']);
    // Image Generation
    Route::post('image', [GenerativeController::class, 'generateImage']);
    Route::get('images', [GenerativeController::class, 'listImages']);
    Route::get('image/{id}', [GenerativeController::class, 'downloadImage']);
    // Audio Generation
    Route::post('audio', [GenerativeController::class, 'generateAudio']);
    Route::get('audios', [GenerativeController::class, 'listAudios']);
    Route::get('audio/{id}', [GenerativeController::class, 'downloadAudio']);
    // Video Generation
    Route::post('video', [GenerativeController::class, 'generateVideo']);
    Route::get('videos', [GenerativeController::class, 'listVideos']);
    Route::get('video/{id}', [GenerativeController::class, 'downloadVideo']);
});
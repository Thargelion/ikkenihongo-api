<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', ProfileController::class);
    Route::patch('/user', [ProfileController::class, 'update']);
    Route::get('/kana', [CatalogController::class, 'kana']);
    Route::get('/kanji', [CatalogController::class, 'kanji']);
    Route::get('/kanji/{kanji}', [CatalogController::class, 'showKanji']);
    Route::get('/words', [CatalogController::class, 'words']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::get('/sessions/{session}', [SessionController::class, 'show']);
    Route::post('/sessions/{session}/answers', [SessionController::class, 'answer']);
    Route::post('/sessions/{session}/answers/sync', [SessionController::class, 'sync']);
    Route::post('/sessions/{session}/complete', [SessionController::class, 'complete']);
    Route::get('/me', [AccountController::class, 'me']);
    Route::post('/me/upgrade', [AccountController::class, 'upgrade']);
    Route::delete('/me/progress', [AccountController::class, 'destroyProgress']);
    Route::get('/me/summary', [AccountController::class, 'summary']);
    Route::get('/me/stats', [AccountController::class, 'stats']);
    Route::get('/me/activity', [AccountController::class, 'activity']);
    Route::patch('/me/settings', [AccountController::class, 'updateSettings']);
    Route::get('/leaderboard/weekly', [AccountController::class, 'leaderboard']);
});

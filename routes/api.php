<?php

use App\Http\Controllers\Api\V1\GrammarController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\ContentAdminController;
use App\Http\Controllers\Api\V1\PuzzleController;
use App\Http\Controllers\Api\V1\ReadingController;
use App\Http\Controllers\Api\V1\WordController;
use App\Http\Middleware\RequireContentAdminToken;
use App\Http\Middleware\AuthenticateMobileToken;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

Route::prefix('v1')->group(function (): void {
    Route::post('auth/wechat', [MobileAuthController::class, 'wechat'])->middleware('throttle:10,1');
    Route::middleware(AuthenticateMobileToken::class)->group(function (): void {
        Route::get('auth/me', [MobileAuthController::class, 'me']);
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
    });
    Route::prefix('admin')->middleware(RequireContentAdminToken::class)->group(function (): void {
        Route::get('reading-items', [ContentAdminController::class, 'readings']);
        Route::post('reading-items', [ContentAdminController::class, 'createReading']);
        Route::get('reading-items/{id}', [ContentAdminController::class, 'readingDetail']);
        Route::put('reading-items/{id}', [ContentAdminController::class, 'updateReading']);
        Route::put('reading-items/{id}/analysis', [ContentAdminController::class, 'saveAnalysis']);
        Route::put('reading-items/{id}/words', [ContentAdminController::class, 'syncReadingWords']);
        Route::post('reading-items/{id}/publish', [ContentAdminController::class, 'publishReading']);
        Route::post('reading-items/{id}/unpublish', [ContentAdminController::class, 'unpublishReading']);
        Route::get('words', [ContentAdminController::class, 'words']);
        Route::post('words', [ContentAdminController::class, 'createWord']);
        Route::get('words/{id}', [ContentAdminController::class, 'showWord']);
        Route::put('words/{id}', [ContentAdminController::class, 'updateWord']);
        Route::put('words/{id}/detail', [ContentAdminController::class, 'saveWordDetail']);
        Route::post('words/{id}/publish', [ContentAdminController::class, 'publishWord']);
        Route::post('words/{id}/unpublish', [ContentAdminController::class, 'unpublishWord']);
    });

    Route::get('reading-items', [ReadingController::class, 'index']);
    Route::get('reading-levels', [ReadingController::class, 'levels']);
    Route::get('reading-items/{id}', [ReadingController::class, 'show']);
    Route::get('reading-items/{id}/words', [ReadingController::class, 'words']);
    Route::get('reading-items/{id}/grammar-analysis', [ReadingController::class, 'grammarAnalysis']);
    Route::get('reading-items/{id}/questions', [ReadingController::class, 'questions']);
    Route::post('reading-questions/{id}/check', [ReadingController::class, 'checkQuestion']);
    Route::get('series', [ReadingController::class, 'seriesIndex']);
    Route::get('series/{id}', [ReadingController::class, 'seriesShow']);
    Route::get('words/{id}', [WordController::class, 'show']);
    Route::get('grammar-lessons', [GrammarController::class, 'index']);
    Route::get('grammar-lessons/{id}', [GrammarController::class, 'show']);
    Route::get('grammar-lessons/{id}/questions', [GrammarController::class, 'questions']);
    Route::post('grammar-questions/{id}/check', [GrammarController::class, 'checkQuestion']);
    Route::get('puzzle-sets', [PuzzleController::class, 'index']);
    Route::get('puzzle-sets/{id}', [PuzzleController::class, 'show']);
});

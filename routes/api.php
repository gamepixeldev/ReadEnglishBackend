<?php

use App\Http\Controllers\Api\V1\GrammarController;
use App\Http\Controllers\Api\V1\PuzzleController;
use App\Http\Controllers\Api\V1\ReadingController;
use App\Http\Controllers\Api\V1\WordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('reading-items', [ReadingController::class, 'index']);
    Route::get('reading-items/{id}', [ReadingController::class, 'show']);
    Route::get('reading-items/{id}/words', [ReadingController::class, 'words']);
    Route::get('reading-items/{id}/questions', [ReadingController::class, 'questions']);
    Route::post('reading-questions/{id}/check', [ReadingController::class, 'checkQuestion']);
    Route::get('series', [ReadingController::class, 'seriesIndex']);
    Route::get('series/{id}', [ReadingController::class, 'seriesShow']);
    Route::get('words/{id}', [WordController::class, 'show']);
    Route::get('grammar-lessons', [GrammarController::class, 'index']);
    Route::get('grammar-lessons/{id}/questions', [GrammarController::class, 'questions']);
    Route::post('grammar-questions/{id}/check', [GrammarController::class, 'checkQuestion']);
    Route::get('puzzle-sets', [PuzzleController::class, 'index']);
    Route::get('puzzle-sets/{id}', [PuzzleController::class, 'show']);
});

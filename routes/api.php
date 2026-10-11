<?php

use App\Http\Controllers\Api\V1\GrammarController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\PuzzleController;
use App\Http\Controllers\Api\V1\ReadingController;
use App\Http\Controllers\Api\V1\WordController;
use App\Http\Controllers\Api\V1\StudyProgressController;
use App\Http\Controllers\Api\V1\DailyWordController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\PracticeAttemptController;
use App\Http\Controllers\Api\V1\RewardedHintController;
use App\Http\Middleware\AuthenticateMobileToken;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

Route::prefix('v1')->group(function (): void {
    Route::post('auth/wechat', [MobileAuthController::class, 'wechat'])->middleware('throttle:10,1');
    Route::get('rewarded-hint-config', [RewardedHintController::class, 'config']);
    Route::post('feedback', [FeedbackController::class, 'store'])->middleware('throttle:5,1');
    Route::post('feedback/mine', [FeedbackController::class, 'mine'])->middleware('throttle:30,1');
    Route::post('feedback/{id}/mine', [FeedbackController::class, 'showMine'])->whereNumber('id')->middleware('throttle:30,1');
    Route::middleware(AuthenticateMobileToken::class)->group(function (): void {
        Route::get('auth/me', [MobileAuthController::class, 'me']);
        Route::get('me/rewarded-hint-config', [RewardedHintController::class, 'config']);
        Route::patch('auth/profile', [MobileAuthController::class, 'updateProfile']);
        Route::post('auth/avatar', [MobileAuthController::class, 'uploadAvatar']);
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('me/study-progress', [StudyProgressController::class, 'index']);
        Route::delete('me/recent-activity', [StudyProgressController::class, 'clearRecent']);
        Route::post('me/reading-completions', [StudyProgressController::class, 'completeReading']);
        Route::post('me/word-learning-events', [StudyProgressController::class, 'completeWord']);
        Route::put('me/word-favorites/{id}', [StudyProgressController::class, 'favoriteWord']);
        Route::delete('me/word-favorites/{id}', [StudyProgressController::class, 'unfavoriteWord']);
        Route::put('me/puzzle-progress/{id}', [StudyProgressController::class, 'savePuzzle']);
        Route::post('me/grammar-activity/{id}', [StudyProgressController::class, 'openGrammar']);
        Route::get('me/practice-attempts', [PracticeAttemptController::class, 'index']);
        Route::post('me/practice-attempts', [PracticeAttemptController::class, 'store']);
        Route::get('me/practice-attempts/{id}', [PracticeAttemptController::class, 'show']);
        Route::post('me/practice-attempts/{id}/answers', [PracticeAttemptController::class, 'answer']);
    });
    Route::get('reading-items', [ReadingController::class, 'index']);
    Route::get('reading-levels', [ReadingController::class, 'levels']);
    Route::get('reading-items/{id}', [ReadingController::class, 'show']);
    Route::get('reading-items/{id}/words', [ReadingController::class, 'words']);
    Route::get('reading-items/{id}/grammar-analysis', [ReadingController::class, 'grammarAnalysis']);
    Route::get('reading-items/{id}/questions', [ReadingController::class, 'questions']);
    Route::get('series', [ReadingController::class, 'seriesIndex']);
    Route::get('series/{id}', [ReadingController::class, 'seriesShow']);
    Route::get('words/{id}', [WordController::class, 'show']);
    Route::get('daily-words/today', [DailyWordController::class, 'today']);
    Route::get('grammar-levels', [GrammarController::class, 'levels']);
    Route::get('grammar-lessons', [GrammarController::class, 'index']);
    Route::get('grammar-lessons/{id}', [GrammarController::class, 'show']);
    Route::get('grammar-lessons/{id}/questions', [GrammarController::class, 'questions']);
    Route::get('puzzle-sets', [PuzzleController::class, 'index']);
    Route::get('puzzle-sets/{id}', [PuzzleController::class, 'show']);
    Route::post('puzzle-sentences/{id}/check', [PuzzleController::class, 'check']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ReadingLevelController;
use App\Http\Controllers\Admin\GrammarLessonController;
use App\Http\Controllers\Admin\GrammarLevelController;
use App\Http\Controllers\Admin\PuzzleSetController;
use App\Http\Controllers\Admin\DailyWordPlanController;

Route::get('/', function () {
    return response()->json(['service' => 'EnglishStudyAPI']);
});

Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');

Route::prefix('admin')->middleware(['auth', 'content-admin'])->group(function (): void {
    Route::get('/', [ContentController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/content', [ContentController::class, 'index'])->name('admin.content');
    Route::get('/articles', [ContentController::class, 'articles'])->name('admin.articles.index');
    Route::get('/articles/create', [ContentController::class, 'articleForm'])->name('admin.articles.create');
    Route::get('/articles/{id}/edit', [ContentController::class, 'articleForm'])->whereNumber('id')->name('admin.articles.edit');
    Route::get('/series', [ContentController::class, 'series'])->name('admin.series.index');
    Route::get('/series/create', [ContentController::class, 'seriesForm'])->name('admin.series.create');
    Route::get('/series/{id}/edit', [ContentController::class, 'seriesForm'])->whereNumber('id')->name('admin.series.edit');
    Route::get('/series/{seriesId}/chapters/create', [ContentController::class, 'chapterForm'])->whereNumber('seriesId')->name('admin.series.chapters.create');
    Route::get('/series/{seriesId}/chapters/{id}/edit', [ContentController::class, 'chapterForm'])->whereNumber('seriesId')->whereNumber('id')->name('admin.series.chapters.edit');
    Route::get('/words', [ContentController::class, 'words'])->name('admin.words.index');
    Route::post('/words/{id}/daily-plans', [DailyWordPlanController::class, 'addWordFromList'])->whereNumber('id')->name('admin.words.daily-plans.add');
    Route::get('/grammar', [GrammarLessonController::class, 'index'])->name('admin.grammar.index');
    Route::get('/grammar/create', [GrammarLessonController::class, 'form'])->name('admin.grammar.create');
    Route::get('/grammar/{id}/edit', [GrammarLessonController::class, 'form'])->whereNumber('id')->name('admin.grammar.edit');
    Route::post('/grammar', [GrammarLessonController::class, 'save'])->name('admin.grammar.store');
    Route::put('/grammar/{id}', [GrammarLessonController::class, 'save'])->whereNumber('id')->name('admin.grammar.update');
    Route::post('/grammar/{id}/toggle', [GrammarLessonController::class, 'toggle'])->whereNumber('id')->name('admin.grammar.toggle');
    Route::get('/grammar-levels', [GrammarLevelController::class, 'index'])->name('admin.grammar-levels.index');
    Route::post('/grammar-levels', [GrammarLevelController::class, 'store'])->name('admin.grammar-levels.store');
    Route::post('/grammar-levels/{id}/toggle', [GrammarLevelController::class, 'toggle'])->whereNumber('id')->name('admin.grammar-levels.toggle');
    Route::post('/grammar-levels/{id}/move/{direction}', [GrammarLevelController::class, 'move'])->whereNumber('id')->name('admin.grammar-levels.move');
    Route::get('/puzzles', [PuzzleSetController::class, 'index'])->name('admin.puzzles.index');
    Route::get('/puzzles/create', [PuzzleSetController::class, 'form'])->name('admin.puzzles.create');
    Route::get('/puzzles/reading-options', [PuzzleSetController::class, 'readingOptions'])->name('admin.puzzles.reading-options');
    Route::get('/puzzles/{id}/edit', [PuzzleSetController::class, 'form'])->whereNumber('id')->name('admin.puzzles.edit');
    Route::post('/puzzles', [PuzzleSetController::class, 'save'])->name('admin.puzzles.store');
    Route::put('/puzzles/{id}', [PuzzleSetController::class, 'save'])->whereNumber('id')->name('admin.puzzles.update');
    Route::post('/puzzles/{id}/toggle', [PuzzleSetController::class, 'toggle'])->whereNumber('id')->name('admin.puzzles.toggle');
    Route::get('/daily-words', [DailyWordPlanController::class, 'index'])->name('admin.daily-words.index');
    Route::get('/daily-words/create', [DailyWordPlanController::class, 'form'])->name('admin.daily-words.create');
    Route::get('/daily-words/word-options', [DailyWordPlanController::class, 'wordOptions'])->name('admin.daily-words.word-options');
    Route::get('/daily-words/{id}/edit', [DailyWordPlanController::class, 'form'])->whereNumber('id')->name('admin.daily-words.edit');
    Route::post('/daily-words', [DailyWordPlanController::class, 'save'])->name('admin.daily-words.store');
    Route::put('/daily-words/{id}', [DailyWordPlanController::class, 'save'])->whereNumber('id')->name('admin.daily-words.update');
    Route::post('/daily-words/{id}/toggle', [DailyWordPlanController::class, 'toggle'])->whereNumber('id')->name('admin.daily-words.toggle');
    Route::get('/reading-levels', [ReadingLevelController::class, 'index'])->name('admin.reading-levels.index');
    Route::post('/reading-levels', [ReadingLevelController::class, 'store'])->name('admin.reading-levels.store');
    Route::post('/reading-levels/{id}/toggle', [ReadingLevelController::class, 'toggle'])->whereNumber('id')->name('admin.reading-levels.toggle');
    Route::post('/reading-levels/{id}/move/{direction}', [ReadingLevelController::class, 'move'])->whereNumber('id')->name('admin.reading-levels.move');
    Route::get('/words/create', [ContentController::class, 'wordForm'])->name('admin.words.create');
    Route::get('/words/{id}/edit', [ContentController::class, 'wordForm'])->whereNumber('id')->name('admin.words.edit');
    Route::get('/password', [ContentController::class, 'passwordForm'])->name('admin.password');
    Route::post('/password', [ContentController::class, 'changePassword'])->name('admin.password.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::post('/articles', [ContentController::class, 'saveArticle'])->name('admin.article.create');
    Route::put('/articles/{id}', [ContentController::class, 'saveArticle'])->whereNumber('id')->name('admin.article.update');
    Route::post('/series', [ContentController::class, 'saveSeries'])->name('admin.series.store');
    Route::put('/series/{id}', [ContentController::class, 'saveSeries'])->whereNumber('id')->name('admin.series.update');
    Route::post('/series/{id}/publish', [ContentController::class, 'publishSeries'])->whereNumber('id')->name('admin.series.publish');
    Route::post('/series/{id}/unpublish', [ContentController::class, 'unpublishSeries'])->whereNumber('id')->name('admin.series.unpublish');
    Route::post('/series/{seriesId}/chapters', [ContentController::class, 'saveChapter'])->whereNumber('seriesId')->name('admin.series.chapters.store');
    Route::put('/series/{seriesId}/chapters/{id}', [ContentController::class, 'saveChapter'])->whereNumber('seriesId')->whereNumber('id')->name('admin.series.chapters.update');
    Route::post('/series/{seriesId}/chapters/{id}/move/{direction}', [ContentController::class, 'moveChapter'])->whereNumber('seriesId')->whereNumber('id')->name('admin.series.chapters.move');
    Route::post('/series/{seriesId}/chapters/{id}/publish', [ContentController::class, 'publishChapter'])->whereNumber('seriesId')->whereNumber('id')->name('admin.series.chapters.publish');
    Route::post('/series/{seriesId}/chapters/{id}/unpublish', [ContentController::class, 'unpublishChapter'])->whereNumber('seriesId')->whereNumber('id')->name('admin.series.chapters.unpublish');
    Route::get('/chapters/{id}/word-options', [ContentController::class, 'searchArticleWords'])->whereNumber('id')->name('admin.chapter.word-options');
    Route::post('/chapters/{id}/words/new', [ContentController::class, 'createArticleWord'])->whereNumber('id')->name('admin.chapter.word-create');
    Route::put('/chapters/{id}/analysis', [ContentController::class, 'saveAnalysis'])->whereNumber('id')->name('admin.chapter.analysis');
    Route::put('/chapters/{id}/questions', [ContentController::class, 'saveReadingQuestions'])->whereNumber('id')->name('admin.chapter.questions');
    Route::put('/chapters/{id}/words', [ContentController::class, 'saveReadingWords'])->whereNumber('id')->name('admin.chapter.words');
    Route::get('/articles/{id}/word-options', [ContentController::class, 'searchArticleWords'])->whereNumber('id')->name('admin.article.word-options');
    Route::post('/articles/{id}/words/new', [ContentController::class, 'createArticleWord'])->whereNumber('id')->name('admin.article.word-create');
    Route::put('/articles/{id}/analysis', [ContentController::class, 'saveAnalysis'])->whereNumber('id')->name('admin.article.analysis');
    Route::put('/articles/{id}/questions', [ContentController::class, 'saveReadingQuestions'])->whereNumber('id')->name('admin.article.questions');
    Route::put('/articles/{id}/words', [ContentController::class, 'saveReadingWords'])->whereNumber('id')->name('admin.article.words');
    Route::post('/articles/{id}/publish', [ContentController::class, 'publishArticle'])->whereNumber('id')->name('admin.article.publish');
    Route::post('/articles/{id}/unpublish', [ContentController::class, 'unpublishArticle'])->whereNumber('id')->name('admin.article.unpublish');

    Route::post('/words', [ContentController::class, 'saveWord'])->name('admin.word.create');
    Route::put('/words/{id}', [ContentController::class, 'saveWord'])->whereNumber('id')->name('admin.word.update');
    Route::post('/words/{id}/publish', [ContentController::class, 'publishWord'])->whereNumber('id')->name('admin.word.publish');
    Route::post('/words/{id}/unpublish', [ContentController::class, 'unpublishWord'])->whereNumber('id')->name('admin.word.unpublish');
});

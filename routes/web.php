<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ContentController;

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
    Route::get('/words', [ContentController::class, 'words'])->name('admin.words.index');
    Route::get('/words/create', [ContentController::class, 'wordForm'])->name('admin.words.create');
    Route::get('/words/{id}/edit', [ContentController::class, 'wordForm'])->whereNumber('id')->name('admin.words.edit');
    Route::get('/password', [ContentController::class, 'passwordForm'])->name('admin.password');
    Route::post('/password', [ContentController::class, 'changePassword'])->name('admin.password.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    Route::post('/articles', [ContentController::class, 'saveArticle'])->name('admin.article.create');
    Route::put('/articles/{id}', [ContentController::class, 'saveArticle'])->whereNumber('id')->name('admin.article.update');
    Route::put('/articles/{id}/analysis', [ContentController::class, 'saveAnalysis'])->whereNumber('id')->name('admin.article.analysis');
    Route::put('/articles/{id}/words', [ContentController::class, 'saveReadingWords'])->whereNumber('id')->name('admin.article.words');
    Route::post('/articles/{id}/publish', [ContentController::class, 'publishArticle'])->whereNumber('id')->name('admin.article.publish');
    Route::post('/articles/{id}/unpublish', [ContentController::class, 'unpublishArticle'])->whereNumber('id')->name('admin.article.unpublish');

    Route::post('/words', [ContentController::class, 'saveWord'])->name('admin.word.create');
    Route::put('/words/{id}', [ContentController::class, 'saveWord'])->whereNumber('id')->name('admin.word.update');
    Route::post('/words/{id}/publish', [ContentController::class, 'publishWord'])->whereNumber('id')->name('admin.word.publish');
    Route::post('/words/{id}/unpublish', [ContentController::class, 'unpublishWord'])->whereNumber('id')->name('admin.word.unpublish');
});

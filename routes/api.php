<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthorArticleController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware('throttle:api')->group(function (): void {
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');

    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/categories', [DirectoryController::class, 'categories'])->name('categories.index');
    Route::get('/authors/{author:slug}', [DirectoryController::class, 'author'])->name('authors.show');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/user', static function (Request $request) {
            return response()->json(['data' => $request->user()->load('roles:id,name,slug')]);
        })->middleware('abilities:profile:read')->name('user');

        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::prefix('author')->name('author.')->middleware(['verified', 'role:author,editor,admin,super-admin'])->group(function (): void {
            Route::get('/articles', [AuthorArticleController::class, 'index'])
                ->middleware('abilities:articles:read')
                ->name('articles.index');

            Route::middleware('abilities:articles:write')->group(function (): void {
                Route::post('/articles', [AuthorArticleController::class, 'store'])->name('articles.store');
                Route::patch('/articles/{article:slug}', [AuthorArticleController::class, 'update'])->name('articles.update');
                Route::post('/articles/{article:slug}/submit', [AuthorArticleController::class, 'submit'])->name('articles.submit');
            });
        });
    });
});

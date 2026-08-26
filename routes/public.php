<?php

use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\AuthorController;
use App\Http\Controllers\Public\CategoryController;
use App\Http\Controllers\Public\CommentController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/journals', [ArticleController::class, 'journals'])->name('journals.index');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/archive', [ArticleController::class, 'archive'])->name('archive.index');
Route::get('/archive/{issue}', [ArticleController::class, 'issue'])->whereNumber('issue')->name('archive.issue');
Route::get('/article/{slug}', [ArticleController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('articles.show');
Route::get('/article/{slug}/print', [ArticleController::class, 'print'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('articles.print');
Route::get('/article/{slug}/pdf', [ArticleController::class, 'pdf'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('articles.pdf');
Route::post('/article/{slug}/comments', [CommentController::class, 'store'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('throttle:3,1')
    ->name('comments.store');

Route::get('/authors', [AuthorController::class, 'index'])->name('authors.index');
Route::get('/author/{slug}', [AuthorController::class, 'show'])
    ->where('slug', '(?!dashboard$|login$|register$|articles$|submissions$|reviews$|profile$|settings$)[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('authors.show');

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/category/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('categories.show');

Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/policies/{page}', [PageController::class, 'policy'])
    ->whereIn('page', ['aims-scope', 'peer-review', 'publication-ethics', 'author-guidelines', 'copyright', 'open-access', 'fees', 'indexing', 'archiving', 'privacy', 'terms'])
    ->name('policies.show');
Route::get('/editorial-board', [PageController::class, 'editorialBoard'])->name('editorial-board');
Route::get('/downloads', [PageController::class, 'downloads'])->name('downloads');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->whereUuid('token')
    ->middleware('throttle:20,1')
    ->name('newsletter.unsubscribe');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageVariantController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/artikel/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/vorschau/artikel/{article}', [ArticleController::class, 'preview'])->middleware('auth')->name('articles.preview');
Route::get('/ausgaben', [IssueController::class, 'index'])->name('issues.index');
Route::get('/ausgaben/{slug}/download', [IssueController::class, 'download'])->name('issues.download');
Route::get('/ausgaben/{slug}', [IssueController::class, 'show'])->name('issues.show');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/bilder/optimiert/{width}/{path}', ImageVariantController::class)
    ->where('width', '[0-9]+')
    ->where('path', '.+')
    ->name('images.variant');
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('pages.show');

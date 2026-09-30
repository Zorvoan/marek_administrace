<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Posts: the home page is the post list; access rules live in PostController.
Route::get('/', [PostController::class, 'index'])->name('posts.index');
Route::resource('posts', PostController::class)->except('index');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

// Authentication. Login/sign up are throttled per IP to slow down brute force.
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('login', [SessionController::class, 'create'])->name('login');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:10,1');
});
Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

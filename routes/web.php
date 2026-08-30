<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DictionarySearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

Route::get('/dictionary', [DictionarySearchController::class, 'index'])->name('dictionary.index');
Route::get('/dictionary/suggestions', [DictionarySearchController::class, 'suggestions'])->name('dictionary.suggestions');
Route::get('/dictionary/meanings', [DictionarySearchController::class, 'meanings'])->name('dictionary.meanings');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', HomeController::class)->name('home');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::get('/study', [StudyController::class, 'settings'])->name('study.settings');
    Route::get('/study/session', [StudyController::class, 'session'])->name('study.session');

    Route::post('/dictionary/words', [DictionarySearchController::class, 'store'])->name('dictionary.words.store');
    Route::delete('/dictionary/words', [DictionarySearchController::class, 'destroy'])->name('dictionary.words.destroy');

    Route::get('/words', [WordController::class, 'index'])->name('words.index');
    Route::get('/words/create', [WordController::class, 'create'])->name('words.create');
    Route::post('/words', [WordController::class, 'store'])->name('words.store');
    Route::patch('/words/{word}/hard', [WordController::class, 'toggleHard'])->name('words.toggle-hard');
    Route::delete('/words/bulk', [WordController::class, 'destroyMany'])->name('words.destroy-many');
    Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');
});

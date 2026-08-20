<?php

use App\Http\Controllers\DictionarySearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/study', [StudyController::class, 'settings'])->name('study.settings');
Route::get('/study/session', [StudyController::class, 'session'])->name('study.session');

Route::get('/dictionary', [DictionarySearchController::class, 'index'])->name('dictionary.index');
Route::get('/dictionary/suggestions', [DictionarySearchController::class, 'suggestions'])->name('dictionary.suggestions');
Route::get('/dictionary/meanings', [DictionarySearchController::class, 'meanings'])->name('dictionary.meanings');
Route::post('/dictionary/words', [DictionarySearchController::class, 'store'])->name('dictionary.words.store');
Route::delete('/dictionary/words', [DictionarySearchController::class, 'destroy'])->name('dictionary.words.destroy');

Route::get('/words', [WordController::class, 'index'])->name('words.index');
Route::get('/words/create', [WordController::class, 'create'])->name('words.create');
Route::post('/words', [WordController::class, 'store'])->name('words.store');
Route::get('/words/{word}/edit', [WordController::class, 'edit'])->name('words.edit');
Route::put('/words/{word}', [WordController::class, 'update'])->name('words.update');
Route::patch('/words/{word}/hard', [WordController::class, 'toggleHard'])->name('words.toggle-hard');
Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');

<?php

use App\Http\Controllers\DictionarySearchController;
use App\Http\Controllers\WordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dictionary', [DictionarySearchController::class, 'index'])->name('dictionary.index');
Route::get('/dictionary/suggestions', [DictionarySearchController::class, 'suggestions'])->name('dictionary.suggestions');

Route::get('/words', [WordController::class, 'index'])->name('words.index');
Route::get('/words/create', [WordController::class, 'create'])->name('words.create');
Route::post('/words', [WordController::class, 'store'])->name('words.store');
Route::get('/words/{word}/edit', [WordController::class, 'edit'])->name('words.edit');
Route::put('/words/{word}', [WordController::class, 'update'])->name('words.update');
Route::patch('/words/{word}/hard', [WordController::class, 'toggleHard'])->name('words.toggle-hard');
Route::delete('/words/{word}', [WordController::class, 'destroy'])->name('words.destroy');

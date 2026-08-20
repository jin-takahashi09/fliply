<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $totalWords = Word::count();
        $hardWords = Word::where('is_hard', true)->count();
        $featuredWord = Word::latest()->first();

        return view('home', compact('totalWords', 'hardWords', 'featuredWord'));
    }
}

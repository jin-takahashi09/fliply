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

        // 最新の単語を3件取得
        $recentWords = Word::latest()
            ->take(3)
            ->get();

        return view('home', compact(
            'totalWords',
            'hardWords',
            'featuredWord',
            'recentWords'
        ));
    }
}
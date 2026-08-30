<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $words = $request->user()->words();

        $totalWords = $words->count();
        $hardWords = (clone $words)->where('is_hard', true)->count();
        $featuredWord = (clone $words)->latest()->first();

        // 最新の単語を最大6件取得（スマホはCSSで3件表示）
        $recentWords = (clone $words)->latest()
            ->take(6)
            ->get();

        return view('home', compact(
            'totalWords',
            'hardWords',
            'featuredWord',
            'recentWords'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyController extends Controller
{
    public function settings(): View
    {
        return view('study.settings', [
            'totalWords' => Word::count(),
            'hardWords' => Word::where('is_hard', true)->count(),
        ]);
    }

    public function session(Request $request): View
    {
        $direction = in_array($request->query('direction'), ['en-ja', 'ja-en'], true)
            ? $request->query('direction')
            : 'en-ja';
        $scope = $request->query('scope') === 'hard' ? 'hard' : 'all';
        $order = $request->query('order') === 'random' ? 'random' : 'registered';

        $words = Word::query()
            ->when($scope === 'hard', fn ($query) => $query->where('is_hard', true))
            ->orderBy('id')
            ->get(['id', 'english', 'japanese', 'is_hard']);

        if ($order === 'random') {
            $words = $words->shuffle()->values();
        }

        return view('study.session', compact('words', 'direction', 'scope', 'order'));
    }
}

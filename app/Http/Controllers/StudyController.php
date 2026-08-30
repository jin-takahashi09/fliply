<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyController extends Controller
{
    public function settings(Request $request): View
    {
        $words = $request->user()->words();

        return view('study.settings', [
            'totalWords' => $words->count(),
            'hardWords' => (clone $words)->where('is_hard', true)->count(),
        ]);
    }

    public function session(Request $request): View
    {
        $direction = in_array(
            $request->query('direction'),
            ['en-ja', 'ja-en'],
            true
        )
            ? $request->query('direction')
            : 'en-ja';

        $scope = $request->query('scope') === 'hard'
            ? 'hard'
            : 'all';

        $words = $request->user()
            ->words()
            ->when(
                $scope === 'hard',
                fn ($query) => $query->where('is_hard', true)
            )
            ->inRandomOrder()
            ->get([
                'id',
                'english',
                'japanese',
                'is_hard',
            ]);

        return view('study.session', compact(
            'words',
            'direction',
            'scope'
        ));
    }
}

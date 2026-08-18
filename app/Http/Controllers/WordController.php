<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter');

        $words = Word::query()
            ->when($filter === 'hard', fn ($query) => $query->where('is_hard', true))
            ->orderBy('id')
            ->get();

        return view('words.index', compact('words', 'filter'));
    }

    public function create(): View
    {
        return view('words.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
        ]);

        Word::create($validated);

        return redirect()->route('words.index');
    }

    public function edit(Word $word): View
    {
        return view('words.edit', compact('word'));
    }

    public function update(Request $request, Word $word): RedirectResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
        ]);

        $word->update($validated);

        return redirect()->route('words.index');
    }

    public function destroy(Word $word): RedirectResponse
    {
        $word->delete();

        return redirect()->route('words.index');
    }

    public function toggleHard(Request $request, Word $word): RedirectResponse
    {
        $word->update([
            'is_hard' => ! $word->is_hard,
        ]);

        return redirect()->route(
            'words.index',
            $request->input('filter') === 'hard' ? ['filter' => 'hard'] : []
        );
    }
}

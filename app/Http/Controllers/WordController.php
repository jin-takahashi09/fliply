<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WordController extends Controller
{
    public function index(): View
    {
        $words = Word::query()->orderBy('id')->get();

        return view('words.index', compact('words'));
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
}

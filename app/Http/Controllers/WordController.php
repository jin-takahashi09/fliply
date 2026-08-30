<?php

namespace App\Http\Controllers;

use App\Models\Word;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WordController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $q = trim((string) $request->query('q', ''));

        $words = $request->user()
            ->words()
            ->when($q !== '', fn ($query) => $query->where('english', 'like', '%'.$q.'%'))
            ->when($filter === 'hard', fn ($query) => $query->where('is_hard', true))
            ->when($filter === 'normal', fn ($query) => $query->where('is_hard', false))
            ->orderByDesc('id')
            ->get();

        return view('words.index', compact('words', 'filter', 'q'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('dictionary.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->words()->create($validated);

        return redirect()->route('words.index');
    }

    public function destroy(Request $request, Word $word): RedirectResponse
    {
        $word->delete();

        return redirect()->route('words.index');
    }

    public function destroyMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        /** @var list<int> $ids */
        $ids = $validated['ids'];

        $words = $request->user()
            ->words()
            ->whereIn('id', $ids)
            ->get();

        if ($words->count() !== count($ids)) {
            return response()->json([
                'message' => '削除対象の単語が見つかりません。',
            ], 422);
        }

        DB::transaction(function () use ($words): void {
            foreach ($words as $word) {
                $word->delete();
            }
        });

        return response()->json([
            'deleted_ids' => $words->pluck('id')->values()->all(),
            'deleted_count' => $words->count(),
        ]);
    }

    public function toggleHard(Request $request, Word $word): RedirectResponse|JsonResponse
    {
        $word->update([
            'is_hard' => ! $word->is_hard,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $word->id,
                'is_hard' => $word->is_hard,
            ]);
        }

        return redirect()->route('words.index', $this->indexQueryParams($request));
    }

    /**
     * @return array<string, string>
     */
    private function indexQueryParams(Request $request): array
    {
        $params = [];

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $params['q'] = $q;
        }

        $filter = $request->input('filter');
        if ($filter === 'hard' || $filter === 'normal') {
            $params['filter'] = $filter;
        }

        return $params;
    }
}

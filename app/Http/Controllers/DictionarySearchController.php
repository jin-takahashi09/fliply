<?php

namespace App\Http\Controllers;

use App\Models\DictionaryWord;
use App\Models\Word;
use App\Services\DictionaryMeaningsService;
use App\Services\WiktionaryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DictionarySearchController extends Controller
{
    public const PAGE_SIZE = 50;

    public function index(): View
    {
        return view('dictionary.index');
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $offset = max(0, (int) $request->query('offset', 0));

        if ($query === '') {
            return response()->json([
                'words' => [],
                'has_more' => false,
                'message' => null,
            ]);
        }

        // 入力バリデーション（空欄以外の無効入力は全て統一メッセージ）
        // - 英字 A-Z/a-z とアポストロフィ（'）のみ
        // - ただし英字を 1 文字以上含む必要がある（' だけは無効）
        $valid = preg_match('/^[a-zA-Z\']+$/', $query) === 1 && preg_match('/[a-zA-Z]/', $query) === 1;

        if (! $valid) {
            return response()->json([
                'words' => [],
                'has_more' => false,
                'message' => "英字とアポストロフィ（'）のみ入力できます。",
            ]);
        }

        $lower = strtolower($query);

        // lower(word) でグループ化し、小文字版があれば小文字版を優先して代表1語を選ぶ
        $selectExpr = "CASE
            WHEN lower(word) = word THEN word
            WHEN MIN(CASE WHEN lower(word) = word THEN word END) IS NOT NULL
                 THEN MIN(CASE WHEN lower(word) = word THEN word END)
            ELSE MIN(word)
        END";

        $baseWhere = "lower(word) LIKE ? AND length(word) > 1";
        $param = [$lower.'%'];

        $total = \Illuminate\Support\Facades\DB::table('dictionary_words')
            ->whereRaw($baseWhere, $param)
            ->groupByRaw('lower(word)')
            ->selectRaw($selectExpr.' AS chosen_word')
            ->get()
            ->count();

        $words = \Illuminate\Support\Facades\DB::table('dictionary_words')
            ->whereRaw($baseWhere, $param)
            ->groupByRaw('lower(word)')
            ->selectRaw($selectExpr.' AS chosen_word')
            ->orderByRaw('lower('.$selectExpr.')')
            ->orderByRaw($selectExpr)
            ->skip($offset)
            ->take(self::PAGE_SIZE)
            ->pluck('chosen_word')
            ->all();

        return response()->json([
            'words' => $words,
            'has_more' => ($offset + count($words)) < $total,
            'message' => null,
        ]);
    }

    public function meanings(Request $request, DictionaryMeaningsService $meaningsService): JsonResponse
    {
        $english = trim((string) $request->query('word', ''));

        return response()->json($meaningsService->resolve($english));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
            'is_hard' => ['sometimes', 'boolean'],
        ]);

        $validated['japanese'] = WiktionaryClient::normalizeJapaneseCandidate((string) $validated['japanese']);

        if (! DictionaryMeaningsService::isAcceptableMeaningCandidate(
            (string) $validated['english'],
            $validated['japanese']
        )) {
            return response()->json([
                'ok' => false,
                'message' => '日本語の意味を選択してください',
            ], 422);
        }

        if (Word::query()
            ->where('english', $validated['english'])
            ->where('japanese', $validated['japanese'])
            ->exists()
        ) {
            return response()->json([
                'ok' => false,
                'message' => '登録済み',
            ], 409);
        }

        $word = Word::create([
            'english' => $validated['english'],
            'japanese' => $validated['japanese'],
            'is_hard' => $request->boolean('is_hard'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => null,
            'word' => [
                'id' => $word->id,
                'english' => $word->english,
                'japanese' => $word->japanese,
                'is_hard' => $word->is_hard,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
        ]);

        $validated['japanese'] = WiktionaryClient::normalizeJapaneseCandidate((string) $validated['japanese']);

        $word = Word::query()
            ->where('english', $validated['english'])
            ->where('japanese', $validated['japanese'])
            ->first();

        if ($word === null) {
            return response()->json([
                'ok' => false,
                'message' => '登録されていません',
            ], 404);
        }

        $word->delete();

        return response()->json([
            'ok' => true,
            'message' => null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DictionaryWord;
use App\Models\Word;
use App\Services\DeepLClient;
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

    public function meanings(Request $request, WiktionaryClient $wiktionary, DeepLClient $deepl): JsonResponse
    {
        $english = trim((string) $request->query('word', ''));

        if ($english === '') {
            return response()->json([
                'english' => '',
                'candidates' => [],
                'message' => '意味を取得できませんでした',
            ]);
        }

        $wiktResult = $wiktionary->meanings($english);
        $groups = $wiktResult['groups'];

        // Meaning groups -> candidate list
        // - each candidate corresponds to one meaning group
        // - de-duplicate by japanese string, keeping the best prioritized group
        // - then limit to at most 4 candidates per english word
        $bestByJapanese = [];

        foreach ($groups as $group) {
            $groupCandidates = $group['candidates'] ?? [];

            // De-duplicate Japanese candidates inside the group.
            $deduped = [];
            foreach ($groupCandidates as $c) {
                if (! in_array($c, $deduped, true)) {
                    $deduped[] = $c;
                }
            }

            if ($deduped === []) {
                continue;
            }

            $japanese = implode('、', $deduped);

            $sourceOrder = (int) ($group['source_order'] ?? 0);
            $isDerived = (bool) ($group['is_derived'] ?? false);
            $labels = $group['labels'] ?? [];

            // Higher score = higher priority.
            // Base priority uses Wiktionary source order (earlier tends to be more central).
            $score = 100000 - $sourceOrder;

            // Derived senses (figurative/metaphorical/by extension) should be lower priority.
            if ($isDerived) {
                $score -= 5000;
            }

            // Rare/archaic/etc are not forbidden; they just come later when competing.
            foreach ($labels as $label) {
                $score -= 1200;
            }

            $entry = [
                'topic' => (string) ($group['topic'] ?? ''),
                'japanese' => $japanese,
                'part_of_speech' => (string) ($group['part_of_speech'] ?? ''),
                'etymology_id' => (int) ($group['etymology_id'] ?? 0),
                'source_order' => $sourceOrder,
                'score' => $score,
            ];

            if (! isset($bestByJapanese[$japanese]) || $entry['score'] > $bestByJapanese[$japanese]['score']) {
                $bestByJapanese[$japanese] = $entry;
            }
        }

        $candidates = array_values($bestByJapanese);

        if ($candidates === []) {
            // Wiktionary で日本語候補が 0 件の場合だけ DeepL へフォールバック
            $deeplResult = $deepl->translate($english);

            if ($deeplResult['ok'] && $deeplResult['translation'] !== null) {
                $candidates = [[
                    'topic' => '',
                    'japanese' => $deeplResult['translation'],
                    'part_of_speech' => '',
                    'etymology_id' => 0,
                    'source_order' => 0,
                    'score' => 0,
                ]];
            }
        }

        // Keep maximum 4 candidates for the UI.
        // Derived/rare senses already get penalized by score; take top-4 by score.
        if ($candidates !== []) {
            usort($candidates, function (array $a, array $b) {
                if ($a['score'] === $b['score']) {
                    return $a['source_order'] <=> $b['source_order'];
                }

                return $b['score'] <=> $a['score'];
            });

            $candidates = array_slice($candidates, 0, 4);
        }

        // registered check is english+japanese pair
        $en = $english;
        foreach ($candidates as &$candidate) {
            $existing = Word::query()
                ->where('english', $en)
                ->where('japanese', (string) $candidate['japanese'])
                ->first();

            $candidate['registered'] = $existing !== null;
            $candidate['word_id'] = $existing?->id;
            unset($candidate['score'], $candidate['part_of_speech'], $candidate['etymology_id'], $candidate['source_order']);
        }
        unset($candidate);

        return response()->json([
            'english' => $english,
            'candidates' => $candidates,
            'message' => ($candidates !== []) ? null : '意味を取得できませんでした',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'english' => ['required', 'string', 'max:255'],
            'japanese' => ['required', 'string', 'max:255'],
            'is_hard' => ['sometimes', 'boolean'],
        ]);

        $validated['japanese'] = WiktionaryClient::normalizeJapaneseCandidate((string) $validated['japanese']);

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

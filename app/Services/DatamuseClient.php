<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DatamuseClient
{
    public const DISPLAY_LIMIT = 50;

    public const FETCH_LIMIT = 200;

    /**
     * @return array{words: list<string>, ok: bool}
     */
    public function suggest(string $prefix): array
    {
        $prefix = trim($prefix);

        if ($prefix === '') {
            return ['words' => [], 'ok' => true];
        }

        try {
            $response = Http::timeout(5)->get('https://api.datamuse.com/words', [
                'sp' => $prefix.'*',
                'max' => self::FETCH_LIMIT,
            ]);
        } catch (ConnectionException) {
            return ['words' => [], 'ok' => false];
        }

        if ($response->failed()) {
            return ['words' => [], 'ok' => false];
        }

        $words = collect($response->json() ?? [])
            ->pluck('word')
            ->filter(fn ($word) => is_string($word) && $word !== '')
            ->filter(fn (string $word) => str_starts_with(strtolower($word), strtolower($prefix)))
            ->filter(fn (string $word) => ! str_contains($word, ' '))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->take(self::DISPLAY_LIMIT)
            ->values()
            ->all();

        return ['words' => $words, 'ok' => true];
    }
}

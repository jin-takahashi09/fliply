<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Facades\Cache;

class DictionaryMeaningsService
{
    public const CACHE_VERSION = 'v3';

    public const CACHE_PREFIX = 'dictionary:meanings:'.self::CACHE_VERSION.':';

    public const CACHE_TTL_SECONDS = 60 * 60 * 24 * 7;

    public function __construct(
        private WiktionaryClient $wiktionary,
        private DeepLClient $deepl,
    ) {}

    /**
     * Resolve dictionary meanings for an English word.
     *
     * Meaning candidates are cached for 7 days. Registration status is always
     * read fresh from the database on every request.
     *
     * @return array{english: string, candidates: list<array{topic: string, japanese: string, registered: bool, word_id: int|null}>, message: string|null}
     */
    public function resolve(string $english): array
    {
        $english = trim($english);

        if ($english === '') {
            return [
                'english' => '',
                'candidates' => [],
                'message' => '意味を取得できませんでした',
            ];
        }

        $cacheKey = self::cacheKeyFor($english);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $cached = $this->filterPayloadCandidates($cached);

            if (($cached['candidates'] ?? []) !== []) {
                return $this->attachRegistrationStatus($english, $cached);
            }
        }

        $payload = $this->fetchMeanings($english);

        if ($this->shouldCache($payload, $english)) {
            Cache::put($cacheKey, $this->stripCacheMetadata($payload), self::CACHE_TTL_SECONDS);
        }

        return $this->attachRegistrationStatus($english, $this->stripCacheMetadata($payload));
    }

    public static function cacheKeyFor(string $english): string
    {
        return self::CACHE_PREFIX.strtolower(trim($english));
    }

    /**
     * Whether a meaning candidate is the official English fallback for the searched word.
     */
    public static function isEnglishFallbackMeaning(string $english, string $japanese): bool
    {
        $english = trim($english);
        $japanese = WiktionaryClient::normalizeJapaneseCandidate($japanese);

        return $english !== '' && $english === $japanese;
    }

    /**
     * Whether a meaning candidate may be shown or registered for the searched word.
     */
    public static function isAcceptableMeaningCandidate(string $english, string $japanese): bool
    {
        return WiktionaryClient::isValidJapaneseMeaning($japanese)
            || self::isEnglishFallbackMeaning($english, $japanese);
    }

    /**
     * @param  array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null, cache?: bool}  $payload
     */
    private function shouldCache(array $payload, string $english): bool
    {
        if (($payload['candidates'] ?? []) === []) {
            return false;
        }

        if (array_key_exists('cache', $payload)) {
            return (bool) $payload['cache'];
        }

        return collect($payload['candidates'] ?? [])
            ->contains(fn (array $candidate): bool => WiktionaryClient::isValidJapaneseMeaning((string) ($candidate['japanese'] ?? ''))
                || self::isEnglishFallbackMeaning($english, (string) ($candidate['japanese'] ?? '')));
    }

    /**
     * @param  array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null, cache?: bool}  $payload
     * @return array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null}
     */
    private function stripCacheMetadata(array $payload): array
    {
        return [
            'english' => (string) ($payload['english'] ?? ''),
            'candidates' => $payload['candidates'] ?? [],
            'message' => $payload['message'] ?? null,
        ];
    }

    /**
     * Fetch and normalize meaning candidates from Wiktionary / DeepL.
     *
     * @return array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null}
     */
    private function fetchMeanings(string $english): array
    {
        $wiktResult = $this->wiktionary->meanings($english);
        $groups = $this->selectGroupsForCandidates($wiktResult['groups']);

        $bestByJapanese = [];

        foreach ($groups as $group) {
            $groupCandidates = $group['candidates'] ?? [];

            $deduped = [];
            foreach ($groupCandidates as $c) {
                if (! WiktionaryClient::isValidJapaneseMeaning((string) $c)) {
                    continue;
                }

                $normalized = WiktionaryClient::normalizeJapaneseCandidate((string) $c);

                if (! in_array($normalized, $deduped, true)) {
                    $deduped[] = $normalized;
                }
            }

            if ($deduped === []) {
                continue;
            }

            $japanese = implode('、', $deduped);

            $sourceOrder = (int) ($group['source_order'] ?? 0);
            $isDerived = (bool) ($group['is_derived'] ?? false);
            $labels = $group['labels'] ?? [];

            $score = 100000 - $sourceOrder;

            if ($isDerived) {
                $score -= 5000;
            }

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
        $hasJapaneseFromWiktionary = $candidates !== [];
        $deeplOk = false;

        if ($candidates === []) {
            $deeplResult = $this->deepl->translate($english);
            $deeplOk = $deeplResult['ok'];

            if ($deeplResult['ok'] && $deeplResult['translation'] !== null) {
                $translation = WiktionaryClient::normalizeJapaneseCandidate($deeplResult['translation']);

                if (WiktionaryClient::isValidJapaneseMeaning($translation)) {
                    $candidates = [[
                        'topic' => '',
                        'japanese' => $translation,
                        'part_of_speech' => '',
                        'etymology_id' => 0,
                        'source_order' => 0,
                        'score' => 0,
                    ]];
                }
            }
        }

        if ($candidates !== []) {
            usort($candidates, function (array $a, array $b) {
                if ($a['score'] === $b['score']) {
                    return $a['source_order'] <=> $b['source_order'];
                }

                return $b['score'] <=> $a['score'];
            });

            $candidates = array_slice($candidates, 0, 4);
        }

        $normalizedCandidates = array_map(function (array $candidate) {
            return [
                'topic' => (string) ($candidate['topic'] ?? ''),
                'japanese' => (string) $candidate['japanese'],
            ];
        }, $candidates);

        if ($normalizedCandidates === []) {
            $normalizedCandidates = [[
                'topic' => '',
                'japanese' => $english,
            ]];
        }

        $payload = $this->filterPayloadCandidates([
            'english' => $english,
            'candidates' => $normalizedCandidates,
            'message' => null,
        ]);

        $hasJapaneseCandidate = collect($payload['candidates'] ?? [])
            ->contains(fn (array $candidate): bool => WiktionaryClient::isValidJapaneseMeaning((string) ($candidate['japanese'] ?? '')));

        $payload['cache'] = $hasJapaneseCandidate
            || $hasJapaneseFromWiktionary
            || ($deeplOk ?? false)
            || ($wiktResult['ok'] ?? false);

        return $payload;
    }

    /**
     * Keep acceptable meaning candidates: valid Japanese, or the searched English word itself.
     *
     * @param  array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null}  $payload
     * @return array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null}
     */
    private function filterPayloadCandidates(array $payload): array
    {
        $english = trim((string) ($payload['english'] ?? ''));
        $candidates = [];

        foreach ($payload['candidates'] ?? [] as $candidate) {
            $japanese = WiktionaryClient::normalizeJapaneseCandidate((string) ($candidate['japanese'] ?? ''));

            if (! self::isAcceptableMeaningCandidate($english, $japanese)) {
                continue;
            }

            $candidates[] = [
                'topic' => (string) ($candidate['topic'] ?? ''),
                'japanese' => $japanese,
            ];
        }

        return [
            'english' => $english,
            'candidates' => $candidates,
            'message' => ($candidates !== []) ? null : '意味を取得できませんでした',
        ];
    }

    /**
     * Prefer normal senses over slang / informal / figurative / by-extension when both exist.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function selectGroupsForCandidates(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $hasNormal = false;

        foreach ($groups as $group) {
            if (! ($group['is_special'] ?? false)) {
                $hasNormal = true;
                break;
            }
        }

        if (! $hasNormal) {
            return $groups;
        }

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => ! ($group['is_special'] ?? false)
        ));
    }

    /**
     * @param  array{english: string, candidates: list<array{topic: string, japanese: string}>, message: string|null}  $payload
     * @return array{english: string, candidates: list<array{topic: string, japanese: string, registered: bool, word_id: int|null}>, message: string|null}
     */
    private function attachRegistrationStatus(string $english, array $payload): array
    {
        $candidates = [];

        foreach ($payload['candidates'] ?? [] as $candidate) {
            $existing = Word::query()
                ->where('english', $english)
                ->where('japanese', (string) $candidate['japanese'])
                ->first();

            $candidates[] = [
                'topic' => (string) ($candidate['topic'] ?? ''),
                'japanese' => (string) $candidate['japanese'],
                'registered' => $existing !== null,
                'word_id' => $existing?->id,
            ];
        }

        return [
            'english' => $english,
            'candidates' => $candidates,
            'message' => $payload['message'] ?? null,
        ];
    }
}

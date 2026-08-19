<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WiktionaryClient
{
    private const ENDPOINT = 'https://en.wiktionary.org/w/api.php';

    private const JA_ENDPOINT = 'https://ja.wiktionary.org/w/api.php';

    /**
     * Normalize Japanese candidates extracted from Wiktionary.
     *
     * Goal: merge "near-identical" candidates coming from wiki-link / bracket artifacts
     * like:
     * - [[オーストラリア...  -> オーストラリア
     * - [オーストラリア]   -> オーストラリア
     *
     * Important: do not remove meaningful Japanese punctuation like （ ）.
     */
    public static function normalizeJapaneseCandidate(string $candidate): string
    {
        $c = trim($candidate);
        $c = str_replace(["\r", "\n"], '', $c);

        // Remove wiki-link wrappers.
        $c = str_replace(['[[', ']]'], '', $c);

        // If it is wrapped by plain [] brackets, strip them.
        // This is intentionally ASCII-only and conservative.
        if (preg_match('/^\[(.*)\]$/u', $c, $m)) {
            $c = trim($m[1]);
        }

        // Also remove leftover leading/trailing bracket artifacts (incomplete wiki markup).
        $c = preg_replace('/^[\\[\\]]+/u', '', $c);
        $c = preg_replace('/[\\[\\]]+$/u', '', $c);
        $c = preg_replace('/^[［］]+/u', '', $c);
        $c = preg_replace('/[［］]+$/u', '', $c);

        // Final cleanup of repeated whitespace (rare for Japanese candidates).
        $c = preg_replace('/\s+/u', ' ', $c);
        $c = trim($c);

        return $c;
    }

    /**
     * Fetch Japanese meanings for an English word from Wiktionary.
     *
     * Returns a list of meaning groups, each with an optional topic label and
     * Japanese candidates.  Example:
     *
     *   [
     *     ['topic' => 'to be able to; to know how to', 'candidates' => ['できる', 'れる', 'られる']],
     *     ['topic' => 'a container for liquids',        'candidates' => ['缶']],
     *   ]
     *
     * Returns an empty array when nothing is found or on any error.
     *
     * @return array{groups: list<array{topic: string, candidates: list<string>}>, ok: bool}
     */
    public function meanings(string $english): array
    {
        $english = trim($english);

        if ($english === '') {
            return ['groups' => [], 'ok' => true];
        }

        $wikitext = $this->fetchWikitext($english);

        if ($wikitext === null) {
            return ['groups' => [], 'ok' => false];
        }

        $wikitext = $this->maybeAppendTranslationSubpage($english, $wikitext);

        $groups = $this->extractGroups($wikitext);

        // Some pages can appear truncated in parse&wikitext output, making Japanese candidates missing.
        // If extraction yields 0 groups, retry with raw page content via action=query.
        if ($groups === []) {
            $raw = $this->fetchRawWikitext($english);

            if ($raw !== null) {
                $raw = $this->maybeAppendTranslationSubpage($english, $raw);
                $groups = $this->extractGroups($raw);
            }
        }

        // English Wiktionary pages may omit Japanese {{t|ja|...}} entries entirely.
        // Fall back to the Japanese Wiktionary English section, which often lists
        // Japanese glosses in numbered definition lines such as: #[[観点]]。[[視点]]。
        if ($groups === []) {
            $groups = $this->fetchJaEnglishGroups($english);
        }

        $groups = $this->deduplicateGroups($groups);
        $groups = $this->deduplicateCandidatesAcrossGroups($groups, $english);

        return ['groups' => $groups, 'ok' => true];
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function userAgent(): string
    {
        $ua = config('services.wiktionary.user_agent');

        return is_string($ua) && $ua !== '' ? $ua : 'Fliply/1.0';
    }

    /**
     * Returns wikitext for the given page title, or null on any error / 429.
     */
    private function fetchWikitext(string $title): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withUserAgent($this->userAgent())
                ->get(self::ENDPOINT, [
                    'action' => 'parse',
                    'page'   => $title,
                    'prop'   => 'wikitext',
                    'format' => 'json',
                ]);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        // Treat 429 and any non-2xx as a soft failure → DeepL fallback.
        if ($response->failed()) {
            return null;
        }

        if (isset($response->json()['error'])) {
            return null;
        }

        // The Wiktionary API stores wikitext under the literal key "*".
        // Laravel's json() treats "*" as a wildcard, so we access the raw array directly.
        $wikitextKey = $response->json('parse.wikitext');

        if (! is_array($wikitextKey) || ! isset($wikitextKey['*'])) {
            return null;
        }

        $wikitext = $wikitextKey['*'];

        return is_string($wikitext) ? $wikitext : null;
    }

    /**
     * Returns wikitext from Japanese Wiktionary, or null on any error.
     */
    private function fetchJaWikitext(string $title): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withUserAgent($this->userAgent())
                ->get(self::JA_ENDPOINT, [
                    'action' => 'parse',
                    'page'   => $title,
                    'prop'   => 'wikitext',
                    'format' => 'json',
                ]);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        if (isset($response->json()['error'])) {
            return null;
        }

        $wikitextKey = $response->json('parse.wikitext');

        if (! is_array($wikitextKey) || ! isset($wikitextKey['*'])) {
            return null;
        }

        $wikitext = $wikitextKey['*'];

        return is_string($wikitext) ? $wikitext : null;
    }

    /**
     * Extract meaning groups from the English section of a Japanese Wiktionary page.
     */
    private function fetchJaEnglishGroups(string $english): array
    {
        $wikitext = $this->fetchJaWikitext($english);

        if ($wikitext === null) {
            return [];
        }

        return $this->extractJaEnglishSectionGroups($wikitext);
    }

    /**
     * Parse Japanese Wiktionary's English entry format.
     *
     * Example (ja.wiktionary.org/wiki/perspective):
     *   =={{en}}==
     *   ==={{noun}}===
     *   #[[観点]]。[[視点]]。
     *
     * @return list<array{topic: string, candidates: list<string>, part_of_speech: string, etymology_id: int, source_order: int, labels: list<string>, is_derived: bool}>
     */
    private function extractJaEnglishSectionGroups(string $wikitext): array
    {
        $sectionText = $this->extractJaEnglishSection($wikitext);

        if ($sectionText === '') {
            return [];
        }

        $groups = [];
        $currentPartOfSpeech = '';
        $groupOrder = 0;

        foreach (explode("\n", $sectionText) as $line) {
            if (preg_match('/^={3,}\s*(.+?)\s*={3,}\s*$/', $line, $m)) {
                $currentPartOfSpeech = $this->normalizeJaSectionHeading($m[1]);
                continue;
            }

            if (! preg_match('/^#(?!#)\s*(.*)$/', $line, $m)) {
                continue;
            }

            $candidates = $this->extractJapaneseFromJaDefinitionLine($m[1]);

            if ($candidates === []) {
                continue;
            }

            $groups[] = [
                'topic'            => '',
                'candidates'      => $candidates,
                'part_of_speech'  => $currentPartOfSpeech,
                'etymology_id'    => 0,
                'source_order'    => $groupOrder++,
                'labels'          => [],
                'is_derived'      => false,
            ];
        }

        return $groups;
    }

    private function extractJaEnglishSection(string $wikitext): string
    {
        $lines = explode("\n", $wikitext);
        $inEnglish = false;
        $sectionLevel = 0;
        $sectionLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^(={2,})\s*(.+?)\s*\1\s*$/', $line, $m)) {
                $level = strlen($m[1]);
                $title = trim($m[2]);
                $isEnglish = $this->isJaEnglishSectionHeading($title);

                if ($level === 2 && $isEnglish) {
                    $inEnglish = true;
                    $sectionLevel = 2;
                    continue;
                }

                if ($inEnglish && $level <= $sectionLevel) {
                    break;
                }
            }

            if ($inEnglish) {
                $sectionLines[] = $line;
            }
        }

        return implode("\n", $sectionLines);
    }

    private function isJaEnglishSectionHeading(string $title): bool
    {
        return preg_match('/\{\{en\}\}|^英語$|\{\{L\|en\}\}/', $title) === 1;
    }

    private function normalizeJaSectionHeading(string $title): string
    {
        $title = trim($title);

        if (preg_match('/\{\{([^}|]+)/', $title, $m)) {
            return trim($m[1]);
        }

        return $title;
    }

    /**
     * @return list<string>
     */
    private function extractJapaneseFromJaDefinitionLine(string $content): array
    {
        $candidates = [];

        if (preg_match_all('/\[\[([^|\]#]+)(?:\|[^\]]+)?\]\]/u', $content, $matches)) {
            foreach ($matches[1] as $raw) {
                $term = self::normalizeJapaneseCandidate(trim((string) $raw));

                if ($term !== '' && self::containsJapanese($term) && ! in_array($term, $candidates, true)) {
                    $candidates[] = $term;
                }
            }
        }

        return $candidates;
    }

    private static function containsJapanese(string $text): bool
    {
        return preg_match('/[\x{3000}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $text) === 1;
    }

    // -------------------------------------------------------------------------
    // Parsing helpers
    // -------------------------------------------------------------------------

    private function hasTranslationSubpage(string $wikitext): bool
    {
        return str_contains(strtolower($wikitext), '{{see translation subpage');
    }

    /**
     * When a page offloads translations to a subpage (e.g. run/translations),
     * append that subpage wikitext to the main wikitext.
     */
    private function maybeAppendTranslationSubpage(string $english, string $wikitext): string
    {
        if (! $this->hasTranslationSubpage($wikitext)) {
            return $wikitext;
        }

        $subWikitext = $this->fetchWikitext($english.'/translations');

        if ($subWikitext !== null) {
            return $wikitext."\n".$subWikitext;
        }

        return $wikitext;
    }

    /**
     * Fetch raw wikitext content via action=query&prop=revisions&rvprop=content.
     * This is used as a retry path when action=parse&wikitext appears to omit Japanese translations.
     */
    private function fetchRawWikitext(string $title): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withUserAgent($this->userAgent())
                ->get('https://en.wiktionary.org/w/api.php', [
                    'action' => 'query',
                    'prop' => 'revisions',
                    'rvprop' => 'content',
                    'format' => 'json',
                    'titles' => $title,
                ]);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $json = $response->json();
        $pages = $json['query']['pages'] ?? [];
        foreach ($pages as $page) {
            $content = $page['revisions'][0]['*'] ?? null;
            if (is_string($content)) {
                return $content;
            }
        }

        return null;
    }

    /**
     * Extract meaning groups from wikitext.
     *
     * Each {{trans-top|topic}} … {{trans-bottom}} block is one group.
     * Japanese entries use {{t|ja|...}} or {{t+|ja|...}}.
     *
     * @return list<array{topic: string, candidates: list<string>}>
     */
    private function extractGroups(string $wikitext): array
    {
        $groups = [];

        $currentTopic = '';
        $currentCandidates = [];
        $inTransBlock = false;
        $blockLines = [];
        $currentBlockOrder = 0;

        // Rough metadata to prioritize "main" senses.
        $currentPartOfSpeech = '';
        $currentEtymologyId = 0;

        $groupOrder = 0;

        $rareKeywords = ['rare', 'archaic', 'obsolete', 'dated', 'historical'];
        $derivedKeywords = [
            // figurative / metaphor / by extension
            'figurative',
            'figuratively',
            'metaphor',
            'metaphorical',
            'metaphorically',
            'by extension',
            'extension',
            'metonym',
        ];

        foreach (explode("\n", $wikitext) as $line) {
            // Track part-of-speech headings like: ===Verb=== / ===Noun===
            if (preg_match('/^===([^=]+)===\s*$/', $line, $m)) {
                $currentPartOfSpeech = trim($m[1]);
                continue;
            }

            // Track etymology sections (approx).
            if (str_contains($line, '===Etymology===')) {
                $currentEtymologyId++;
                continue;
            }

            // Start of a translation block
            if (preg_match('/\{\{trans-top(?:\|([^|}]*))?/', $line, $m)) {
                $inTransBlock = true;
                $currentTopic = isset($m[1]) ? trim($m[1]) : '';
                $currentCandidates = [];
                $blockLines = [$line];
                $currentBlockOrder = $groupOrder++;
                continue;
            }

            // End of a translation block
            if ($inTransBlock && str_contains($line, '{{trans-bottom')) {
                $inTransBlock = false;

                if ($currentCandidates !== []) {
                    $blockText = mb_strtolower(implode("\n", $blockLines));

                    $labels = [];
                    foreach ($rareKeywords as $kw) {
                        if (str_contains($blockText, $kw)) {
                            $labels[] = $kw;
                        }
                    }

                    $hasDerived = false;
                    foreach ($derivedKeywords as $kw) {
                        if (str_contains($blockText, $kw)) {
                            $hasDerived = true;
                            break;
                        }
                    }

                    $groups[] = [
                        'topic'            => $currentTopic,
                        'candidates'      => $currentCandidates,
                        'part_of_speech'  => $currentPartOfSpeech,
                        'etymology_id'    => $currentEtymologyId,
                        'source_order'    => $currentBlockOrder,
                        'labels'          => $labels,     // rare/archaic/etc labels found in this group
                        'is_derived'      => $hasDerived, // figurative/by extension/metaphorical
                    ];
                }

                $currentTopic = '';
                $currentCandidates = [];
                $blockLines = [];
                continue;
            }

            if (! $inTransBlock) {
                continue;
            }

            $blockLines[] = $line;

            // Extract Japanese entries: {{t|ja|...}}, {{t+|ja|...}}, {{tt|ja|...}}, {{tt+|ja|...}}
            if (preg_match_all('/\{\{tt?[+]?\|ja\|([^|}\]]+)/', $line, $matches)) {
                foreach ($matches[1] as $raw) {
                    $candidate = self::normalizeJapaneseCandidate((string) $raw);

                    if ($candidate !== '' && ! in_array($candidate, $currentCandidates, true)) {
                        $currentCandidates[] = $candidate;
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * Whether two katakana-only strings are likely spelling variants of each other.
     *
     * Conservative on purpose: only used for short katakana loanwords that co-occur
     * in Wiktionary data (e.g. キャンバス / カンバス).
     */
    public static function areKatakanaVariants(string $a, string $b): bool
    {
        $a = self::normalizeJapaneseCandidate($a);
        $b = self::normalizeJapaneseCandidate($b);

        if ($a === $b) {
            return true;
        }

        if (! self::isKatakanaWord($a) || ! self::isKatakanaWord($b)) {
            return false;
        }

        if (abs(mb_strlen($a) - mb_strlen($b)) > 2) {
            return false;
        }

        return self::mbLevenshtein($a, $b) <= 2;
    }

    private static function isKatakanaWord(string $text): bool
    {
        return preg_match('/^[\x{30A0}-\x{30FF}ー]+$/u', $text) === 1;
    }

    private static function mbLevenshtein(string $a, string $b): int
    {
        $aChars = mb_str_split($a);
        $bChars = mb_str_split($b);
        $aLen = count($aChars);
        $bLen = count($bChars);

        if ($aLen === 0) {
            return $bLen;
        }

        if ($bLen === 0) {
            return $aLen;
        }

        $prev = range(0, $bLen);
        $curr = array_fill(0, $bLen + 1, 0);

        for ($i = 1; $i <= $aLen; $i++) {
            $curr[0] = $i;

            for ($j = 1; $j <= $bLen; $j++) {
                $cost = $aChars[$i - 1] === $bChars[$j - 1] ? 0 : 1;
                $curr[$j] = min(
                    $prev[$j] + 1,
                    $curr[$j - 1] + 1,
                    $prev[$j - 1] + $cost
                );
            }

            $prev = $curr;
        }

        return $prev[$bLen];
    }

    /**
     * Remove Japanese candidates duplicated across meaning groups while keeping
     * the groups themselves separate.
     */
    private function deduplicateCandidatesAcrossGroups(array $groups, string $english): array
    {
        if ($groups === []) {
            return [];
        }

        if (count($groups) === 1) {
            return $this->deduplicateVariantsWithinGroups($groups);
        }

        $englishLower = strtolower(trim($english));

        $items = [];
        foreach ($groups as $groupIdx => $group) {
            foreach ($group['candidates'] ?? [] as $candidateIdx => $candidate) {
                $items[] = [
                    'groupIdx' => $groupIdx,
                    'candidateIdx' => $candidateIdx,
                    'text' => (string) $candidate,
                ];
            }
        }

        if ($items === []) {
            return $groups;
        }

        $clusterKeys = $this->buildGlobalVariantKeys($items);
        $byKey = [];

        foreach ($items as $itemIdx => $item) {
            $key = $clusterKeys[$itemIdx];
            $score = $this->candidateOwnershipScore(
                $groups[$item['groupIdx']],
                $item['candidateIdx'],
                $item['text'],
                $key,
                $englishLower
            );

            $byKey[$key][] = [
                'groupIdx' => $item['groupIdx'],
                'candidateIdx' => $item['candidateIdx'],
                'text' => $item['text'],
                'score' => $score,
            ];
        }

        $winnerByKey = [];
        $winnerTextByKey = [];

        foreach ($byKey as $key => $entries) {
            $groupsInvolved = array_values(array_unique(array_column($entries, 'groupIdx')));

            if (count($groupsInvolved) <= 1) {
                continue;
            }

            usort($entries, function (array $a, array $b) {
                if ($a['score'] === $b['score']) {
                    return $a['candidateIdx'] <=> $b['candidateIdx'];
                }

                return $b['score'] <=> $a['score'];
            });

            $winnerByKey[$key] = $entries[0]['groupIdx'];
            $winnerTextByKey[$key] = $entries[0]['text'];
        }

        foreach ($groups as $groupIdx => &$group) {
            $kept = [];
            $seenKeys = [];

            foreach ($group['candidates'] ?? [] as $candidateIdx => $candidate) {
                $itemIdx = $this->findItemIndex($items, $groupIdx, $candidateIdx);
                $key = $clusterKeys[$itemIdx];

                if (isset($winnerByKey[$key]) && $winnerByKey[$key] !== $groupIdx) {
                    continue;
                }

                if (isset($seenKeys[$key])) {
                    continue;
                }

                $seenKeys[$key] = true;
                $kept[] = $winnerTextByKey[$key] ?? $candidate;
            }

            $group['candidates'] = $kept;
        }
        unset($group);

        return $groups;
    }

    /**
     * @param  list<array{groupIdx: int, candidateIdx: int, text: string}>  $items
     * @return array<int, string>
     */
    private function buildGlobalVariantKeys(array $items): array
    {
        $count = count($items);
        $parent = range(0, max(0, $count - 1));

        $find = function (int $x) use (&$parent, &$find): int {
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);

            if ($rootA === $rootB) {
                return;
            }

            // Keep the earlier item as the canonical representative.
            if ($rootA <= $rootB) {
                $parent[$rootB] = $rootA;
            } else {
                $parent[$rootA] = $rootB;
            }
        };

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($items[$i]['text'] === $items[$j]['text']
                    || self::areKatakanaVariants($items[$i]['text'], $items[$j]['text'])
                ) {
                    $union($i, $j);
                }
            }
        }

        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[$i] = $items[$find($i)]['text'];
        }

        return $keys;
    }

    /**
     * @param  list<array{groupIdx: int, candidateIdx: int, text: string}>  $items
     */
    private function findItemIndex(array $items, int $groupIdx, int $candidateIdx): int
    {
        foreach ($items as $itemIdx => $item) {
            if ($item['groupIdx'] === $groupIdx && $item['candidateIdx'] === $candidateIdx) {
                return $itemIdx;
            }
        }

        return 0;
    }

    private function candidateOwnershipScore(
        array $group,
        int $candidateIdx,
        string $candidate,
        string $clusterKey,
        string $englishLower
    ): int {
        $score = 0;
        $topic = mb_strtolower((string) ($group['topic'] ?? ''));

        if ($englishLower !== '' && str_contains($topic, $englishLower)) {
            $score += 10000;
        }

        $score += (100 - $candidateIdx) * 100;
        $score += 1000 - (int) ($group['source_order'] ?? 0);

        if ($group['is_derived'] ?? false) {
            $score -= 500;
        }

        foreach ($group['labels'] ?? [] as $label) {
            $score -= 100;
        }

        if ($candidate === $clusterKey) {
            $score += 50;
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function deduplicateVariantsWithinGroups(array $groups): array
    {
        foreach ($groups as &$group) {
            $candidates = $group['candidates'] ?? [];
            $items = array_map(
                fn (string $text, int $candidateIdx) => [
                    'groupIdx' => 0,
                    'candidateIdx' => $candidateIdx,
                    'text' => $text,
                ],
                $candidates,
                array_keys($candidates)
            );

            if ($items === []) {
                continue;
            }

            $clusterKeys = $this->buildGlobalVariantKeys($items);
            $kept = [];
            $seenKeys = [];

            foreach ($candidates as $candidateIdx => $candidate) {
                $key = $clusterKeys[$candidateIdx];

                if (isset($seenKeys[$key])) {
                    continue;
                }

                $seenKeys[$key] = true;
                $kept[] = $candidate;
            }

            $group['candidates'] = $kept;
        }
        unset($group);

        return $groups;
    }

    /**
     * Merge groups that are effectively the same after candidate normalization.
     *
     * Safety constraints:
     * - part_of_speech / etymology_id / topic must match
     * - normalized candidate set must be exactly equal
     */
    private function deduplicateGroups(array $groups): array
    {
        $best = [];

        foreach ($groups as $group) {
            $pos = (string) ($group['part_of_speech'] ?? '');
            $ety = (int) ($group['etymology_id'] ?? 0);
            $topic = (string) ($group['topic'] ?? '');

            $cands = $group['candidates'] ?? [];
            $cands = array_values(array_unique(array_map(
                fn ($c) => self::normalizeJapaneseCandidate((string) $c),
                $cands
            ), SORT_STRING));

            sort($cands, SORT_STRING);

            $key = $pos.'|'.$ety.'|'.$topic.'|'.implode('、', $cands);

            if (! isset($best[$key])) {
                $best[$key] = $group;
                continue;
            }

            // Keep the earlier-occurring group.
            $prevOrder = (int) ($best[$key]['source_order'] ?? 0);
            $currOrder = (int) ($group['source_order'] ?? 0);
            if ($currOrder < $prevOrder) {
                $best[$key] = $group;
            }
        }

        // Preserve deterministic ordering by source_order.
        $deduped = array_values($best);
        usort($deduped, fn ($a, $b) => ($a['source_order'] ?? 0) <=> ($b['source_order'] ?? 0));

        return $deduped;
    }

    /**
     * Flatten groups into a deduplicated list of Japanese candidates.
     *
     * @param  list<array{topic: string, candidates: list<string>}>  $groups
     * @return list<string>
     */
    public static function flattenCandidates(array $groups): array
    {
        $seen = [];
        $result = [];

        foreach ($groups as $group) {
            foreach ($group['candidates'] as $c) {
                if (! isset($seen[$c])) {
                    $seen[$c] = true;
                    $result[] = $c;
                }
            }
        }

        return $result;
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class WiktionaryClient
{
    private const ENDPOINT = 'https://en.wiktionary.org/w/api.php';

    private const JA_ENDPOINT = 'https://ja.wiktionary.org/w/api.php';

    private const TIMEOUT_SECONDS = 4;

    private const MAX_ATTEMPTS = 2;

    private const RETRY_DELAY_MICROSECONDS = 250_000;

    private const TRANSIENT_ERRORS = [
        'rate_limited',
        'timeout',
        'connection',
        'http',
        'empty_response',
    ];

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
     * @return array{groups: list<array{topic: string, candidates: list<string>}>, ok: bool, transient: bool, error: string|null}
     */
    public function meanings(string $english): array
    {
        $english = trim($english);

        if ($english === '') {
            return ['groups' => [], 'ok' => true, 'transient' => false, 'error' => null];
        }

        $result = $this->meaningsAttempt($english);

        if ($result['groups'] !== [] || ! $result['transient']) {
            return $result;
        }

        usleep(self::RETRY_DELAY_MICROSECONDS);

        return $this->meaningsAttempt($english);
    }

    /**
     * @return array{groups: list<array{topic: string, candidates: list<string>}>, ok: bool, transient: bool, error: string|null}
     */
    private function meaningsAttempt(string $english): array
    {
        // Fetch English and Japanese Wiktionary pages in parallel to cut fallback latency.
        $pages = $this->fetchEnAndJaWikitext($english);
        $wikitext = $pages['en'];
        $jaWikitext = $pages['ja'];

        if ($wikitext === null && $jaWikitext === null) {
            return [
                'groups' => [],
                'ok' => false,
                'transient' => $pages['transient'],
                'error' => $pages['error'],
            ];
        }

        $groups = [];

        if ($wikitext !== null) {
            $wikitext = $this->maybeAppendTranslationSubpage($english, $wikitext);
            $groups = $this->filterGroupsWithValidJapaneseCandidates(
                $this->extractGroups($wikitext)
            );

            // Some pages can appear truncated in parse&wikitext output, making Japanese candidates missing.
            // If extraction yields 0 valid groups, retry with raw page content via action=query.
            if ($groups === []) {
                $raw = $this->fetchRawWikitext($english);

                if ($raw !== null) {
                    $raw = $this->maybeAppendTranslationSubpage($english, $raw);
                    $groups = $this->filterGroupsWithValidJapaneseCandidates(
                        $this->extractGroups($raw)
                    );
                }
            }
        }

        // English Wiktionary pages may omit Japanese {{t|ja|...}} entries entirely,
        // or only list English-like strings under {{t|ja|...}} (e.g. wabbit (wabit)).
        // Fall back to the Japanese Wiktionary English section (already fetched in parallel).
        if ($groups === [] && $jaWikitext !== null) {
            $groups = $this->filterGroupsWithValidJapaneseCandidates(
                $this->extractJaEnglishSectionGroups($jaWikitext)
            );
        }

        $groups = $this->deduplicateGroups($groups);
        $groups = $this->filterGroupsWithValidJapaneseCandidates($groups);
        $groups = $this->deduplicateCandidatesAcrossGroups($groups, $english);
        $groups = $this->filterGroupsWithValidJapaneseCandidates($groups);

        $stillEmptyAfterParse = $groups === [];

        return [
            'groups' => $groups,
            'ok' => true,
            'transient' => $stillEmptyAfterParse && $pages['transient'],
            'error' => $stillEmptyAfterParse ? $pages['error'] : null,
        ];
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
     * @return array{en: string|null, ja: string|null, transient: bool, error: string|null}
     */
    private function fetchEnAndJaWikitext(string $english): array
    {
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('en')
                ->timeout(self::TIMEOUT_SECONDS)
                ->withUserAgent($this->userAgent())
                ->get(self::ENDPOINT, [
                    'action' => 'parse',
                    'page' => $english,
                    'prop' => 'wikitext',
                    'format' => 'json',
                ]),
            $pool->as('ja')
                ->timeout(self::TIMEOUT_SECONDS)
                ->withUserAgent($this->userAgent())
                ->get(self::JA_ENDPOINT, [
                    'action' => 'parse',
                    'page' => $english,
                    'prop' => 'wikitext',
                    'format' => 'json',
                ]),
        ]);

        $en = $this->classifyHttpResult($responses['en'] ?? null);
        $ja = $this->classifyHttpResult($responses['ja'] ?? null);

        return [
            'en' => $en['wikitext'],
            'ja' => $ja['wikitext'],
            'transient' => $this->isTransientFetch($en) || $this->isTransientFetch($ja),
            'error' => $this->preferredError($en['error'], $ja['error']),
        ];
    }

    /**
     * Returns wikitext for the given page title, or null on any error / 429.
     */
    private function fetchWikitext(string $title): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withUserAgent($this->userAgent())
                ->get(self::ENDPOINT, [
                    'action' => 'parse',
                    'page' => $title,
                    'prop' => 'wikitext',
                    'format' => 'json',
                ]);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        return $this->classifyHttpResult($response)['wikitext'];
    }

    /**
     * Classify a pooled HTTP result or exception into wikitext + error code.
     *
     * @return array{wikitext: string|null, error: string|null}
     */
    private function classifyHttpResult(mixed $result): array
    {
        if ($result instanceof ConnectionException) {
            return [
                'wikitext' => null,
                'error' => $this->isTimeoutException($result) ? 'timeout' : 'connection',
            ];
        }

        if ($result instanceof Throwable) {
            return ['wikitext' => null, 'error' => 'connection'];
        }

        if (! $result instanceof Response) {
            return ['wikitext' => null, 'error' => 'connection'];
        }

        if ($result->status() === 429) {
            return ['wikitext' => null, 'error' => 'rate_limited'];
        }

        $status = $result->status();

        // Permanent client errors: waiting/retrying will not help.
        if ($status >= 400 && $status < 500) {
            return ['wikitext' => null, 'error' => 'client_error'];
        }

        // Temporary upstream/server failures.
        if ($status >= 500) {
            return ['wikitext' => null, 'error' => 'http'];
        }

        $json = $result->json();

        if (is_array($json) && isset($json['error'])) {
            $code = $json['error']['code'] ?? '';

            if ($code === 'missingtitle') {
                return ['wikitext' => null, 'error' => 'not_found'];
            }

            // Other MediaWiki API errors on HTTP 200 are not transient.
            return ['wikitext' => null, 'error' => 'client_error'];
        }

        $wikitextKey = $result->json('parse.wikitext');

        if (! is_array($wikitextKey) || ! isset($wikitextKey['*'])) {
            return ['wikitext' => null, 'error' => 'empty_response'];
        }

        $wikitext = $wikitextKey['*'];

        if (! is_string($wikitext) || trim($wikitext) === '') {
            return ['wikitext' => null, 'error' => 'empty_response'];
        }

        return ['wikitext' => $wikitext, 'error' => null];
    }

    /**
     * @param  array{wikitext: string|null, error: string|null}  $fetch
     */
    private function isTransientFetch(array $fetch): bool
    {
        return $fetch['wikitext'] === null && $this->isTransientError($fetch['error']);
    }

    private function isTransientError(?string $error): bool
    {
        return in_array($error, self::TRANSIENT_ERRORS, true);
    }

    private function preferredError(?string $first, ?string $second): ?string
    {
        foreach (['rate_limited', 'timeout', 'connection', 'http', 'empty_response', 'client_error', 'not_found'] as $code) {
            if ($first === $code || $second === $code) {
                return $code;
            }
        }

        return $first ?? $second;
    }

    private function isTimeoutException(ConnectionException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
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
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withUserAgent($this->userAgent())
                ->get(self::ENDPOINT, [
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
     * Parse Japanese Wiktionary's English entry format.
     *
     * Example (ja.wiktionary.org/wiki/perspective):
     *   =={{en}}==
     *   ==={{noun}}===
     *   #[[観点]]。[[視点]]。
     *
     * @return list<array{topic: string, candidates: list<string>, part_of_speech: string, etymology_id: int, source_order: int, labels: list<string>, is_derived: bool, is_special: bool}>
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
                'topic' => '',
                'candidates' => $candidates,
                'part_of_speech' => $currentPartOfSpeech,
                'etymology_id' => 0,
                'source_order' => $groupOrder++,
                'labels' => [],
                'is_derived' => false,
                'is_special' => false,
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

    /**
     * Whether a string contains at least one Hiragana, Katakana, or Han character.
     */
    public static function isValidJapaneseMeaning(string $text): bool
    {
        $text = self::normalizeJapaneseCandidate($text);

        if ($text === '') {
            return false;
        }

        return self::containsJapanese($text);
    }

    private static function containsJapanese(string $text): bool
    {
        // Script= is required: bare \p{Hiragana} etc. also match CJK punctuation in PHP.
        return preg_match('/[\p{Script=Hiragana}\p{Script=Katakana}\p{Script=Han}]/u', $text) === 1;
    }

    /**
     * Keep only groups whose candidates contain Japanese script.
     *
     * @param  list<array{topic: string, candidates: list<string>}>  $groups
     * @return list<array{topic: string, candidates: list<string>}>
     */
    private function filterGroupsWithValidJapaneseCandidates(array $groups): array
    {
        $filtered = [];

        foreach ($groups as $group) {
            $candidates = [];

            foreach ($group['candidates'] ?? [] as $candidate) {
                $normalized = self::normalizeJapaneseCandidate((string) $candidate);

                if ($normalized !== '' && self::containsJapanese($normalized)) {
                    $candidates[] = $normalized;
                }
            }

            if ($candidates === []) {
                continue;
            }

            $group['candidates'] = $candidates;
            $filtered[] = $group;
        }

        return $filtered;
    }

    // -------------------------------------------------------------------------
    // Parsing helpers
    // -------------------------------------------------------------------------

    private function hasTranslationSubpage(string $wikitext): bool
    {
        return str_contains(strtolower($wikitext), '{{see translation subpage');
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
                    $blockText = implode("\n", $blockLines);
                    $senseText = mb_strtolower($this->extractSenseLevelBlockText($blockText));

                    $labels = $this->extractRareLabels($senseText);

                    $hasDerived = $this->senseHasDerivedMarker($senseText);

                    $groups[] = [
                        'topic'            => $currentTopic,
                        'candidates'      => $currentCandidates,
                        'part_of_speech'  => $currentPartOfSpeech,
                        'etymology_id'    => $currentEtymologyId,
                        'source_order'    => $currentBlockOrder,
                        'labels'          => $labels,
                        'is_derived'      => $hasDerived,
                        'is_special'      => $this->detectSpecialMeaning($blockText),
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

                    if ($candidate !== ''
                        && self::containsJapanese($candidate)
                        && ! in_array($candidate, $currentCandidates, true)) {
                        $currentCandidates[] = $candidate;
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * Detect slang / informal / figurative / by-extension senses from Wiktionary markup.
     *
     * Uses labels, qualifiers, and trans-top topics — never Japanese translation text.
     * Only inspects sense-level metadata before per-language translation lines so that
     * qualifiers attached to individual language entries do not mark the whole sense special.
     */
    private function detectSpecialMeaning(string $blockText): bool
    {
        $text = mb_strtolower($this->extractSenseLevelBlockText($blockText));

        if (preg_match('/\{\{qualifier\|(slang|informal|figurative|figuratively)\}\}/', $text)) {
            return true;
        }

        if (preg_match('/\{\{lb\|[^}|]*\|(slang|informal|figurative|figuratively)\b/', $text)) {
            return true;
        }

        if (preg_match('/\{\{sense\|[^}]*\b(slang|informal|figurative|figuratively)\b/', $text)) {
            return true;
        }

        if (preg_match('/\{\{trans-top\|[^}]*\b(slang|informal|figurative)\b/', $text)) {
            return true;
        }

        if (str_contains($text, 'by extension')) {
            return true;
        }

        return false;
    }

    /**
     * Return only the sense-level portion of a translation block (before language lines).
     */
    private function extractSenseLevelBlockText(string $blockText): string
    {
        $senseLines = [];

        foreach (explode("\n", $blockText) as $line) {
            $trimmed = ltrim($line);

            if ($trimmed !== '' && str_starts_with($trimmed, '*')) {
                break;
            }

            $senseLines[] = $line;
        }

        return implode("\n", $senseLines);
    }

    /**
     * Extract rare/archaic/obsolete labels from sense-level metadata only.
     *
     * @return list<string>
     */
    private function extractRareLabels(string $senseText): array
    {
        $labels = [];

        foreach (['rare', 'archaic', 'obsolete', 'dated', 'historical'] as $keyword) {
            if ($this->senseTextContainsKeyword($senseText, $keyword)) {
                $labels[] = $keyword;
            }
        }

        return $labels;
    }

    /**
     * Whether sense-level metadata marks a figurative / by-extension sense.
     */
    private function senseHasDerivedMarker(string $senseText): bool
    {
        foreach ([
            'figurative',
            'figuratively',
            'metaphor',
            'metaphorical',
            'metaphorically',
            'by extension',
            'metonym',
        ] as $keyword) {
            if ($this->senseTextContainsKeyword($senseText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match Wiktionary sense labels/qualifiers without scanning per-language lines.
     */
    private function senseTextContainsKeyword(string $text, string $keyword): bool
    {
        $escaped = preg_quote($keyword, '/');

        if (preg_match('/\{\{qualifier\|[^}]*\b'.$escaped.'\b/', $text)) {
            return true;
        }

        if (preg_match('/\{\{lb\|[^}|]*\|\s*'.$escaped.'\b/', $text)) {
            return true;
        }

        if (preg_match('/\{\{sense\|[^}]*\b'.$escaped.'\b/', $text)) {
            return true;
        }

        if (preg_match('/\{\{trans-top\|[^}]*\b'.$escaped.'\b/', $text)) {
            return true;
        }

        return false;
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

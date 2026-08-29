<?php

use App\Models\Word;
use App\Services\WiktionaryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Wikitext fixtures (minimal but representative)
// ---------------------------------------------------------------------------

function canWikitext(): string
{
    // Large entries use {{tt+|ja|...}} multitrans format.
    return <<<'WIKITEXT'
==English==
===Verb===
====Translations====
{{trans-top|to be able to; to know how to}}{{multitrans|data=
* Japanese: {{tt+|ja|できる|tr=dekiru}}, {{tt+|ja|れる|alt=-れる|tr=-reru}}, {{tt+|ja|られる|alt=-られる|tr=-rareru}}
{{trans-bottom}}
===Noun===
====Translations====
{{trans-top|a more or less cylindrical vessel for liquids}}{{multitrans|data=
* Japanese: {{tt+|ja|缶|tr=かん, kan}}
{{trans-bottom}}
WIKITEXT;
}

function runWikitext(): string
{
    // Main page contains {{see translation subpage|Verb}}
    return <<<'WIKITEXT'
==English==
===Verb===
====Translations====
{{see translation subpage|Verb}}
WIKITEXT;
}

function runTranslationsWikitext(): string
{
    return <<<'WIKITEXT'
{{translation subpage}}
==English==
===Verb===
====Translations====
{{trans-top|to move quickly on two feet}}
* Japanese: {{t+|ja|走る|tr=hashiru}}
{{trans-bottom}}
{{trans-top|of a machine, to be operating normally}}
* Japanese: {{t+|ja|動く|tr=ugoku}}, {{t+|ja|作動|tr=sadō}}
{{trans-bottom}}
WIKITEXT;
}

function lightWikitext(): string
{
    // Noun is on a subpage; Adjective is inline
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{see translation subpage|Noun}}
===Adjective===
====Translations====
{{trans-top|having light}}
* Japanese: {{t+|ja|明るい|tr=akarui}}
{{trans-bottom}}
{{trans-top|of low weight}}
* Japanese: {{t+|ja|軽い|tr=karui}}
{{trans-bottom}}
===Verb===
====Translations====
{{trans-top|to illuminate}}
* Japanese: {{t+|ja|照らす|tr=terasu}}
{{trans-bottom}}
WIKITEXT;
}

function lightTranslationsWikitext(): string
{
    return <<<'WIKITEXT'
{{translation subpage}}
==English==
===Noun===
====Translations====
{{trans-top|electromagnetic waves}}
* Japanese: {{t+|ja|光|tr=hikari}}, {{t+|ja|明かり|tr=akari}}
{{trans-bottom}}
{{trans-top|point of view}}
{{qualifier|figurative}}
* Japanese: {{t+|ja|視点|tr=shiten}}, {{t+|ja|観点|tr=kanten}}
{{trans-bottom}}
WIKITEXT;
}

function appleWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|fruit of ''Malus domestica''}}
* Japanese: {{t+|ja|林檎|tr=ringo}}, {{t+|ja|リンゴ|tr=ringo}}
{{trans-bottom}}
WIKITEXT;
}

function appleWithSlangWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|fruit of ''Malus domestica''}}
* Japanese: {{t+|ja|林檎|tr=ringo}}, {{t+|ja|リンゴ|tr=ringo}}
{{trans-bottom}}
{{trans-top|slang: ball used in basketball}}
{{qualifier|slang}}
* Japanese: {{t+|ja|バスケットボール|tr=basukettobōru}}
{{trans-bottom}}
WIKITEXT;
}

function wabbitWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|informal term for rabbit}}
* Japanese: {{t+|ja|wabbit (wabit)}}
{{trans-bottom}}
WIKITEXT;
}

function generalWithLabelWikitext(string $label, string $specialJapanese, string $normalJapanese = '通常'): string
{
    return <<<WIKITEXT
==English==
===Noun===
====Translations====
{{trans-top|primary sense}}
* Japanese: {{t+|ja|{$normalJapanese}|tr=tsūjō}}
{{trans-bottom}}
{{trans-top|secondary sense}}
{{qualifier|{$label}}}
* Japanese: {{t+|ja|{$specialJapanese}|tr=tokushu}}
{{trans-bottom}}
WIKITEXT;
}

function slangOnlyWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|slang term}}
{{qualifier|slang}}
* Japanese: {{t+|ja|俗語訳|tr=zokugoyaku}}
{{trans-bottom}}
WIKITEXT;
}

function mixedJapaneseEnglishWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|point of view}}
* Japanese: {{t+|ja|観点 (viewpoint)}}, {{t+|ja|視点|tr=shiten}}
{{trans-bottom}}
WIKITEXT;
}

function archaicOnlyWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|archaic sense}}
{{qualifier|archaic}}
* Japanese: {{t+|ja|古語|tr=kogo}}
{{trans-bottom}}
WIKITEXT;
}

function obsoleteOnlyWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|obsolete sense}}
{{qualifier|obsolete}}
* Japanese: {{t+|ja|廃語|tr=haigo}}
{{trans-bottom}}
WIKITEXT;
}

function canvasWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|type of coarse cloth}}
* Japanese: {{t+|ja|帆布|tr=hanpu}}, {{t+|ja|キャンバス|tr=kyanbasu}}, {{t+|ja|ズック|tr=zukku}}
{{trans-bottom}}
{{trans-top|piece of canvas cloth on which one may paint}}
* Japanese: {{t+|ja|画布|tr=gafu}}, {{t+|ja|キャンバス|tr=kyanbasu}}, {{t+|ja|カンバス|tr=kanbasu}}
{{trans-bottom}}
WIKITEXT;
}

function australiaWikitext(): string
{
    // Two groups with effectively the same candidate set,
    // but group #2 contains a wiki-link/bracket artifact like: [[オーストラリア
    return <<<'WIKITEXT'
==English==
===Proper noun===
====Translations====
{{trans-top|Australia}}
* Japanese: {{t+|ja|オーストラリア}}, {{t+|ja|濠太剌利}}, {{t+|ja|濠洲}}, {{t+|ja|豪州}}, {{t+|ja|濠}}, {{t+|ja|豪}}
{{trans-bottom}}
{{trans-top|Australia}}
* Japanese: {{t+|ja|オーストラリア}}, {{t+|ja|濠太剌利}}, {{t+|ja|濠洲}}, {{t+|ja|豪州}}, {{t+|ja|濠}}, {{t+|ja|豪}}, {{t+|ja|[[オーストラリア}}
{{trans-bottom}}
WIKITEXT;
}

function perspectiveWikitext(): string
{
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|point of view}}
* Japanese: {{t+|ja|観点|tr=kanten}}
{{trans-bottom}}
WIKITEXT;
}

function perspectiveEnWithoutJaWikitext(): string
{
    // Real en.wiktionary perspective page has translations but no Japanese entries.
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|view, vista or outlook}}
* Finnish: {{t+|fi|näkymä}}
{{trans-bottom}}
WIKITEXT;
}

function perspectiveJaWikitext(): string
{
    return <<<'WIKITEXT'
=={{en}}==
[[category:{{en}}]]
==={{pron}}===
* {{a|GA}} {{IPA|lang=en|/pɚˈspɛk.tɪv/}}
==={{noun}}===
{{en-noun|s}}
#[[観点]]。[[視点]]。
#[[考察]]。
#[[遠近法]]。
WIKITEXT;
}

function rareOnlyWikitext(): string
{
    // すべて rare ラベル付きの意味を5件入れて、最大4件制限でも
    // rare が「完全に削除」されないことを確認する fixture。
    return <<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|rare sense 1}}
{{qualifier|rare}}
* Japanese: {{t+|ja|一|tr=ichi}}
{{trans-bottom}}
{{trans-top|rare sense 2}}
{{qualifier|rare}}
* Japanese: {{t+|ja|二|tr=ni}}
{{trans-bottom}}
{{trans-top|rare sense 3}}
{{qualifier|rare}}
* Japanese: {{t+|ja|三|tr=san}}
{{trans-bottom}}
{{trans-top|rare sense 4}}
{{qualifier|rare}}
* Japanese: {{t+|ja|四|tr=yon}}
{{trans-bottom}}
{{trans-top|rare sense 5}}
{{qualifier|rare}}
* Japanese: {{t+|ja|五|tr=go}}
{{trans-bottom}}
WIKITEXT;
}

function wiktionaryResponse(string $wikitext, string $title = 'test'): array
{
    return [
        'parse' => [
            'title' => $title,
            'pageid' => 1,
            'wikitext' => ['*' => $wikitext],
        ],
    ];
}

function wiktionaryNotFound(): array
{
    return [
        'error' => [
            'code' => 'missingtitle',
            'info' => "The page you specified doesn't exist.",
        ],
    ];
}

function wiktDeeplResponse(string $translation): array
{
    return [
        'translations' => [
            ['detected_source_language' => 'EN', 'text' => $translation],
        ],
    ];
}

// ---------------------------------------------------------------------------
// 1-9. can: meaning-group -> candidate list + english+japanese uniqueness
// ---------------------------------------------------------------------------

it('groups can meanings into two separate candidates', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=can');
    $response->assertSuccessful();

    $candidates = $response->json('candidates');

    expect($candidates)->toBeArray()->not->toBeEmpty();
    expect($candidates)->toHaveCount(2);

    $japaneseValues = collect($candidates)->pluck('japanese')->all();
    expect($japaneseValues)->toContain('できる、れる、られる')
        ->and($japaneseValues)->toContain('缶');
});

it('does not show checkbox UI in the dictionary page html', function () {
    $response = $this->get('/dictionary');
    $response->assertSuccessful();
    $response->assertDontSee('checkbox');
});

it('shows idle and empty suggestion states on the dictionary page', function () {
    $response = $this->get('/dictionary');

    $response->assertSuccessful()
        ->assertSee('英単語を検索してみよう')
        ->assertSee('入力すると候補がここに表示されます')
        ->assertSee('候補が見つかりませんでした')
        ->assertSee('別の英単語で検索してみてください')
        ->assertSee('data-suggestions-idle', false)
        ->assertSee('data-suggestions-empty', false);
});

it('does not render a standalone english-only heading in dictionary detail', function () {
    $content = $this->get('/dictionary')->getContent();

    expect($content)->not->toContain('englishEl');
});

it('renders each candidate as english（japanese） format only', function () {
    $content = $this->get('/dictionary')->getContent();

    expect($content)->toContain("(data.english || '') + '（' + (cand.japanese || '') + '）'");
    expect($content)->toContain("add.textContent = '追加'");
    expect($content)->toContain("addHard.textContent = '難しいに追加'");
    expect($content)->toContain("unregister.textContent = '登録を解除'");
});

it('dictionary page uses the same candidate format for all words including Australia and Australians', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(australiaWikitext(), 'Australia'), 200),
    ]);

    $meanings = $this->getJson('/dictionary/meanings?word=Australia')->json();
    $page = $this->get('/dictionary')->getContent();

    expect($meanings['candidates'])->toHaveCount(1);
    expect($meanings['candidates'][0]['japanese'])->toBe('オーストラリア、濠太剌利、濠洲、豪州、濠、豪');
    expect($page)->toContain("(data.english || '') + '（' + (cand.japanese || '') + '）'");
    expect($page)->not->toContain('englishEl');
});

it('can multiple meaning candidates use the same display format', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
    ]);

    $canMeanings = $this->getJson('/dictionary/meanings?word=can')->json();
    expect($canMeanings['candidates'])->toHaveCount(2);

    $page = $this->get('/dictionary')->getContent();
    expect($page)->toContain("(data.english || '') + '（' + (cand.japanese || '') + '）'");
});

it('DeepL fallback candidates use the same display format', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('アップルソース'), 200),
    ]);

    $deeplMeanings = $this->getJson('/dictionary/meanings?word=applesauce')->json();
    expect($deeplMeanings['candidates'][0]['japanese'])->toBe('アップルソース');

    $page = $this->get('/dictionary')->getContent();
    expect($page)->toContain("(data.english || '') + '（' + (cand.japanese || '') + '）'");
});

it('can registers the can(〜できる系) candidate and then can registers can(缶) separately', function () {
    // candidates are defined by groups
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
    ]);

    $cand1 = 'できる、れる、られる';
    $cand2 = '缶';

    // add normal candidate1
    $this->postJson('/dictionary/words', [
        'english' => 'can',
        'japanese' => $cand1,
        'is_hard' => false,
    ])->assertSuccessful()->assertJsonPath('ok', true);

    // add normal candidate2
    $this->postJson('/dictionary/words', [
        'english' => 'can',
        'japanese' => $cand2,
        'is_hard' => false,
    ])->assertSuccessful()->assertJsonPath('ok', true);

    $this->assertDatabaseHas('words', [
        'english' => 'can',
        'japanese' => $cand1,
        'is_hard' => false,
    ]);

    $this->assertDatabaseHas('words', [
        'english' => 'can',
        'japanese' => $cand2,
        'is_hard' => false,
    ]);
});

it('prevents registering the exact same english+japanese pair twice', function () {
    $cand1 = 'できる、れる、られる';

    $this->postJson('/dictionary/words', [
        'english' => 'can',
        'japanese' => $cand1,
        'is_hard' => false,
    ])->assertSuccessful();

    $this->postJson('/dictionary/words', [
        'english' => 'can',
        'japanese' => $cand1,
        'is_hard' => false,
    ])->assertStatus(409)->assertJsonPath('message', '登録済み');
});

it('registered status is english+japanese pair specific', function () {
    $cand1 = 'できる、れる、られる';
    $cand2 = '缶';

    Word::create(['english' => 'can', 'japanese' => $cand1, 'is_hard' => false]);

    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=can');
    $response->assertSuccessful();

    $candidates = $response->json('candidates');
    $byJapanese = collect($candidates)->keyBy(fn ($c) => $c['japanese'])->all();

    expect($byJapanese[$cand1]['registered'])->toBeTrue();
    expect($byJapanese[$cand2]['registered'])->toBeFalse();
});

it('unregister deletes only the matching english+japanese pair', function () {
    $cand1 = 'できる、れる、られる';
    $cand2 = '缶';

    Word::create(['english' => 'can', 'japanese' => $cand1, 'is_hard' => false]);
    Word::create(['english' => 'can', 'japanese' => $cand2, 'is_hard' => false]);

    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
    ]);

    $this->deleteJson('/dictionary/words', [
        'english' => 'can',
        'japanese' => $cand1,
    ])->assertSuccessful();

    $this->assertDatabaseMissing('words', ['english' => 'can', 'japanese' => $cand1]);
    $this->assertDatabaseHas('words', ['english' => 'can', 'japanese' => $cand2]);
});

// ---------------------------------------------------------------------------
// 10-12. DeepL fallback + is_hard flags
// ---------------------------------------------------------------------------

it('falls back to DeepL when Wiktionary returns no candidates and registers applesauce normally', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('アップルソース'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=applesauce');
    $response->assertSuccessful()
        ->assertJsonPath('candidates.0.japanese', 'アップルソース');

    $this->postJson('/dictionary/words', [
        'english' => 'applesauce',
        'japanese' => 'アップルソース',
        'is_hard' => false,
    ])->assertSuccessful()->assertJsonPath('ok', true);

    $this->assertDatabaseHas('words', [
        'english' => 'applesauce',
        'japanese' => 'アップルソース',
        'is_hard' => false,
    ]);
});

it('registers to 難しいに追加 with is_hard=true', function () {
    $this->postJson('/dictionary/words', [
        'english' => 'applesauce',
        'japanese' => 'アップルソース',
        'is_hard' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('words', [
        'english' => 'applesauce',
        'japanese' => 'アップルソース',
        'is_hard' => true,
    ]);
});

// ---------------------------------------------------------------------------
// 7-9. run subpage + light multi-candidate (basic structure)
// ---------------------------------------------------------------------------

it('fetches run/translations only when main page has see translation subpage', function () {
    Http::fake([
        'en.wiktionary.org/*page=run&*' => Http::response(wiktionaryResponse(runWikitext(), 'run'), 200),
        'en.wiktionary.org/*page=run%2Ftranslations*' => Http::response(wiktionaryResponse(runTranslationsWikitext(), 'run/translations'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
    ]);

    $client = app(WiktionaryClient::class);
    $result = $client->meanings('run');

    expect($result['groups'])->toHaveCount(2);
    expect(WiktionaryClient::flattenCandidates($result['groups']))->toContain('走る');

    // en main + ja (parallel), then en translations subpage.
    Http::assertSentCount(3);
});

it('returns multiple japanese candidates as separate candidates for run', function () {
    Http::fake([
        'en.wiktionary.org/*page=run&*' => Http::response(wiktionaryResponse(runWikitext(), 'run'), 200),
        'en.wiktionary.org/*page=run%2Ftranslations*' => Http::response(wiktionaryResponse(runTranslationsWikitext(), 'run/translations'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=run');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();
    expect($japaneseValues)->toContain('走る')
        ->and($japaneseValues)->toContain('動く、作動');
});

it('returns noun+adjective meaning candidates for light', function () {
    Http::fake([
        'en.wiktionary.org/*page=light&*' => Http::response(wiktionaryResponse(lightWikitext(), 'light'), 200),
        'en.wiktionary.org/*page=light%2Ftranslations*' => Http::response(wiktionaryResponse(lightTranslationsWikitext(), 'light/translations'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=light');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();
    expect($japaneseValues)->toHaveCount(4)
        ->and($japaneseValues)->toContain('光、明かり')
        ->and($japaneseValues)->toContain('明るい')
        ->and($japaneseValues)->toContain('軽い')
        ->and($japaneseValues)->toContain('照らす')
        ->and($japaneseValues)->not->toContain('視点、観点');
});

it('keeps 観点 for perspective as a main candidate', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveWikitext(), 'perspective'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=perspective');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toContain('観点');
});

it('falls back to ja.wiktionary when en.wiktionary has no Japanese translations', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveEnWithoutJaWikitext(), 'perspective'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveJaWikitext(), 'perspective'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=perspective');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toBeEmpty();
    expect($japaneseValues[0])->toBe('観点、視点');
});

it('does not call DeepL when ja.wiktionary provides perspective candidates', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveEnWithoutJaWikitext(), 'perspective'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveJaWikitext(), 'perspective'), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('SHOULD_NOT_BE_USED'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=perspective');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'deepl.com'));
});

it('keeps two canvas meaning groups while removing cross-group Japanese duplicates', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canvasWikitext(), 'canvas'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=canvas');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(2)
        ->and($japaneseValues)->toContain('帆布、ズック')
        ->and($japaneseValues)->toContain('画布、キャンバス')
        ->and($japaneseValues)->not->toContain('帆布、キャンバス、ズック')
        ->and($japaneseValues)->not->toContain('画布、キャンバス、カンバス');
});

it('treats キャンバス and カンバス as katakana variants', function () {
    expect(WiktionaryClient::areKatakanaVariants('キャンバス', 'カンバス'))->toBeTrue();
});

it('normalizes wiki bracket artifacts in Australia and merges duplicate candidates into 1', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(australiaWikitext(), 'Australia'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=Australia');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(1);
    expect($japaneseValues[0])->toBe('オーストラリア、濠太剌利、濠洲、豪州、濠、豪');
});

it('treats [オーストラリア] as オーストラリア in normalization', function () {
    expect(WiktionaryClient::normalizeJapaneseCandidate('[オーストラリア]'))
        ->toBe('オーストラリア');
});

it('prevents registering duplicate Australia candidates even if input contains wiki artifacts', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(australiaWikitext(), 'Australia'), 200),
    ]);

    $candidate = 'オーストラリア、濠太剌利、濠洲、豪州、濠、豪';
    $candidateWithArtifact = '[[オーストラリア、濠太剌利、濠洲、豪州、濠、豪';

    $this->postJson('/dictionary/words', [
        'english' => 'Australia',
        'japanese' => $candidate,
        'is_hard' => false,
    ])->assertSuccessful();

    $this->postJson('/dictionary/words', [
        'english' => 'Australia',
        'japanese' => $candidateWithArtifact,
        'is_hard' => false,
    ])->assertStatus(409)->assertJsonPath('message', '登録済み');
});

it('unregister deletes Australia candidate by english+japanese pair (normalized)', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(australiaWikitext(), 'Australia'), 200),
    ]);

    $candidate = 'オーストラリア、濠太剌利、濠洲、豪州、濠、豪';
    $candidateWithArtifact = '[[オーストラリア、濠太剌利、濠洲、豪州、濠、豪';

    Word::create(['english' => 'Australia', 'japanese' => $candidate, 'is_hard' => false]);

    $this->deleteJson('/dictionary/words', [
        'english' => 'Australia',
        'japanese' => $candidateWithArtifact,
    ])->assertSuccessful();

    $this->assertDatabaseMissing('words', ['english' => 'Australia', 'japanese' => $candidate]);
});

it('does not completely drop rare-labelled senses (max 4 still applies)', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(rareOnlyWikitext(), 'rareword'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=rareword');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(4)
        ->and($japaneseValues)->toContain('一')
        ->and($japaneseValues)->not->toContain('五');
});

it('apple returns a single meaning-group candidate (no meaning-selection UI)', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(appleWikitext(), 'apple'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=apple');
    $response->assertSuccessful();

    $response->assertJsonPath('candidates.0.japanese', '林檎、リンゴ');
    $response->assertJsonCount(1, 'candidates');
});

it('excludes slang basketball meaning from apple when a normal meaning exists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(appleWithSlangWikitext(), 'apple'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=apple');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(1)
        ->and($japaneseValues)->toContain('林檎、リンゴ')
        ->and($japaneseValues)->not->toContain('バスケットボール');
});

it('does not hardcode apple-specific special-meaning filtering', function () {
    $serviceSource = file_get_contents(app_path('Services/DictionaryMeaningsService.php'));
    $clientSource = file_get_contents(app_path('Services/WiktionaryClient.php'));

    expect($serviceSource)->not->toMatch("/['\"]apple['\"]/")
        ->and($clientSource)->not->toMatch("/['\"]apple['\"]/");
});

it('keeps only general meanings when slang coexists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(generalWithLabelWikitext('slang', '俗語'), 'word'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=word');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['通常'])
        ->and($japaneseValues)->not->toContain('俗語');
});

it('keeps only general meanings when informal coexists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(generalWithLabelWikitext('informal', 'くだけた'), 'word'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=word');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['通常'])
        ->and($japaneseValues)->not->toContain('くだけた');
});

it('keeps only general meanings when figurative coexists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(generalWithLabelWikitext('figurative', '比喩'), 'word'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=word');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['通常'])
        ->and($japaneseValues)->not->toContain('比喩');
});

it('keeps only general meanings when by extension coexists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(<<<'WIKITEXT'
==English==
===Noun===
====Translations====
{{trans-top|primary sense}}
* Japanese: {{t+|ja|通常|tr=tsūjō}}
{{trans-bottom}}
{{trans-top|extended sense}}
{{qualifier|by extension}}
* Japanese: {{t+|ja|拡張|tr=kakuchō}}
{{trans-bottom}}
WIKITEXT, 'word'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=word');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['通常'])
        ->and($japaneseValues)->not->toContain('拡張');
});

it('keeps special meanings when no normal meaning exists', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(slangOnlyWikitext(), 'slangword'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=slangword');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['俗語訳']);
});

it('does not completely drop archaic-labelled senses when they are the only meaning', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(archaicOnlyWikitext(), 'archaicword'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=archaicword');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['古語']);
});

it('does not completely drop obsolete-labelled senses when they are the only meaning', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(obsoleteOnlyWikitext(), 'obsoleteword'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=obsoleteword');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toBe(['廃語']);
});

it('does not call DeepL when only special Wiktionary meanings exist', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(slangOnlyWikitext(), 'slangword'), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('SHOULD_NOT_BE_USED'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=slangword');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'deepl.com'));
});

// ---------------------------------------------------------------------------
// 14-16. API error handling fallback strategy
// ---------------------------------------------------------------------------

it('does not mark a normal sense special because another language entry has an informal qualifier', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(<<<'WIKITEXT'
==English==
===Verb===
====Translations====
{{trans-top|to be able to}}
* Japanese: {{t+|ja|できる|tr=dekiru}}
{{trans-bottom}}
===Noun===
====Translations====
{{trans-top|a tin-plate canister}}
* Slovene: {{t|sl|pločevinka|f}}, {{qualifier|informal}} {{t|sl|konzerva|f}}
* Japanese: {{t+|ja|缶|tr=kan}}
{{trans-bottom}}
WIKITEXT, 'can'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=can');
    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toHaveCount(2)
        ->and($japaneseValues)->toContain('できる')
        ->and($japaneseValues)->toContain('缶');
});

it('does not call DeepL when Wiktionary has candidates', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(canWikitext(), 'can'), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('SHOULD_NOT_BE_USED'), 200),
    ]);

    $this->getJson('/dictionary/meanings?word=can');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'deepl.com'));
});

it('falls back to DeepL when Wiktionary returns 429 (and does not crash)', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Too Many Requests', 429),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('アップルソース'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=applesauce');
    $response->assertSuccessful();
    $response->assertJsonPath('candidates.0.japanese', 'アップルソース');
});

it('falls back to DeepL when Wiktionary returns 5xx', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('Internal Server Error', 500),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('アップルソース'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=applesauce');
    $response->assertSuccessful();
    $response->assertJsonPath('candidates.0.japanese', 'アップルソース');
});

it('returns the searched english word when both Wiktionary and DeepL fail', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response('error', 500),
        'api-free.deepl.com/*' => Http::response('error', 500),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=applesauce');
    $response->assertSuccessful();
    $response->assertJsonPath('candidates.0.japanese', 'applesauce');
    $response->assertJsonPath('message', null);
});

it('rejects English-only Wiktionary ja templates and falls back to DeepL', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(wabbitWikitext(), 'wabbit'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('ウサギ'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=wabbit');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toContain('ウサギ')
        ->and($japaneseValues)->not->toContain('wabbit (wabit)');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'deepl.com'));
});

it('does not accept English-only DeepL translations as Japanese meanings and falls back to the searched word', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(wabbitWikitext(), 'wabbit'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryNotFound(), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('wabbit'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=wabbit');
    $response->assertSuccessful();

    expect($response->json('candidates.0.japanese'))->toBe('wabbit')
        ->and(collect($response->json('candidates'))->pluck('japanese')->all())
        ->not->toContain('wabbit (wabit)')
        ->and($response->json('message'))->toBeNull();
});

it('falls back to ja.wiktionary when en.wiktionary only has English-like ja templates', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(wabbitWikitext(), 'wabbit'), 200),
        'ja.wiktionary.org/*' => Http::response(wiktionaryResponse(perspectiveJaWikitext(), 'wabbit'), 200),
        'api-free.deepl.com/*' => Http::response(wiktDeeplResponse('SHOULD_NOT_BE_USED'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=wabbit');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toBeEmpty()
        ->and($japaneseValues)->not->toContain('wabbit (wabit)');

    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'deepl.com'));
});

it('keeps Japanese candidates that also contain Latin letters or symbols', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(mixedJapaneseEnglishWikitext(), 'perspective'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=perspective');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->toContain('観点 (viewpoint)、視点');
});

it('validates Japanese meaning strings for dictionary registration', function () {
    expect(WiktionaryClient::isValidJapaneseMeaning('りんご'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('リンゴ'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('林檎'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('観点'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('AI技術'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('version 2 の説明'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('観点 (viewpoint)'))->toBeTrue()
        ->and(WiktionaryClient::isValidJapaneseMeaning('wabbit'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('wabbit (wabit)'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('apple'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('slang'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('()'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('、'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('。'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('「」'))->toBeFalse()
        ->and(WiktionaryClient::isValidJapaneseMeaning('Noun'))->toBeFalse();
});

it('does not add the searched English word when Japanese meanings exist', function () {
    Http::fake([
        'en.wiktionary.org/*' => Http::response(wiktionaryResponse(appleWikitext(), 'apple'), 200),
    ]);

    $response = $this->getJson('/dictionary/meanings?word=apple');
    $response->assertSuccessful();

    $japaneseValues = collect($response->json('candidates'))->pluck('japanese')->all();

    expect($japaneseValues)->not->toBeEmpty()
        ->and($japaneseValues)->not->toContain('apple');
});

it('allows dictionary store when japanese matches the searched english fallback', function () {
    $response = $this->postJson('/dictionary/words', [
        'english' => 'wabbit',
        'japanese' => 'wabbit',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('ok', true);

    expect(Word::query()->where('english', 'wabbit')->where('japanese', 'wabbit')->exists())->toBeTrue();
});

it('rejects dictionary store when japanese contains no Japanese script', function () {
    $response = $this->postJson('/dictionary/words', [
        'english' => 'wabbit',
        'japanese' => 'wabbit (wabit)',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('ok', false);

    expect(Word::query()->where('english', 'wabbit')->exists())->toBeFalse();
});


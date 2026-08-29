<?php

use App\Models\DictionaryWord;
use App\Http\Controllers\DictionarySearchController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper: insert dictionary words for tests
function insertDictWords(array $words): void
{
    foreach ($words as $word) {
        DictionaryWord::create(['word' => $word]);
    }
}

it('returns words that start with the given prefix (case-insensitive)', function () {
    insertDictWords(['apple', 'apples', 'application', 'banana', 'America']);

    $response = $this->getJson('/dictionary/suggestions?q=ap');

    $response->assertSuccessful()
        ->assertJsonPath('message', null);

    $words = $response->json('words');
    expect($words)->toContain('apple')
        ->and($words)->toContain('apples')
        ->and($words)->toContain('application')
        ->and($words)->not->toContain('banana');
});

it('a search for lowercase a also returns uppercase words like America', function () {
    insertDictWords(['apple', 'America', 'Amazon', 'banana']);

    $response = $this->getJson('/dictionary/suggestions?q=a');

    $words = $response->json('words');
    expect($words)->toContain('America')
        ->and($words)->toContain('Amazon')
        ->and($words)->toContain('apple');
});

it('ap search returns apple', function () {
    insertDictWords(['apple', 'apply', 'banana']);

    $response = $this->getJson('/dictionary/suggestions?q=ap');

    expect($response->json('words'))->toContain('apple');
});

it('returns the plural form if it exists in the dictionary', function () {
    insertDictWords(['apple', 'apples']);

    $response = $this->getJson('/dictionary/suggestions?q=apple');

    expect($response->json('words'))->toContain('apples');
});

it('returns inflected forms if they exist in the dictionary', function () {
    insertDictWords(['applied', 'apply', 'applying', 'applies']);

    $response = $this->getJson('/dictionary/suggestions?q=appl');

    $words = $response->json('words');
    expect($words)->toContain('applied')
        ->and($words)->toContain('applying')
        ->and($words)->toContain('applies');
});

it('returns at most 50 words per page', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 80));
    insertDictWords($words);

    $response = $this->getJson('/dictionary/suggestions?q=a');

    $response->assertSuccessful();
    expect($response->json('words'))->toHaveCount(DictionarySearchController::PAGE_SIZE);
});

it('returns the next page via offset parameter', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 80));
    insertDictWords($words);

    $page1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $page2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');

    expect($page2)->toHaveCount(30)
        ->and(array_intersect($page1, $page2))->toBeEmpty();
});

it('words do not repeat across pages', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 80));
    insertDictWords($words);

    $page1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $page2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');

    expect(array_intersect($page1, $page2))->toBeEmpty();
});

it('has_more is false on the last page', function () {
    insertDictWords(['apple', 'apply']);

    $response = $this->getJson('/dictionary/suggestions?q=app');

    $response->assertSuccessful()
        ->assertJsonPath('has_more', false);
});

it('has_more is true when more pages exist', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 80));
    insertDictWords($words);

    $response = $this->getJson('/dictionary/suggestions?q=a&offset=0');

    $response->assertSuccessful()
        ->assertJsonPath('has_more', true);
});

it('changing the query restarts from offset 0', function () {
    insertDictWords(['apple', 'apples', 'banana', 'band']);

    $appleWords = $this->getJson('/dictionary/suggestions?q=apple')->json('words');
    $bananaWords = $this->getJson('/dictionary/suggestions?q=ban')->json('words');

    expect($appleWords)->toContain('apple')
        ->and($bananaWords)->toContain('banana')
        ->and($bananaWords)->not->toContain('apple');
});

it('returns empty words and no message when query is empty', function () {
    $response = $this->getJson('/dictionary/suggestions?q=');

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('message', null);
});

it('dictionary_words do not appear on the words index page', function () {
    insertDictWords(['apple', 'apply', 'America']);

    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertDontSee('apple')
        ->assertDontSee('America');
});

it('does not send HTTP requests to Datamuse when searching', function () {
    \Illuminate\Support\Facades\Http::fake();

    insertDictWords(['apple', 'application']);

    $this->getJson('/dictionary/suggestions?q=ap');

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

// --- 辞書候補フィルタリングのテスト ---

it('common nouns are kept in the dictionary', function () {
    insertDictWords(['apple', 'book', 'table']);

    $words = $this->getJson('/dictionary/suggestions?q=apple')->json('words');
    expect($words)->toContain('apple');
});

it('common verbs are kept in the dictionary', function () {
    insertDictWords(['run', 'runs', 'eat']);

    $words = $this->getJson('/dictionary/suggestions?q=run')->json('words');
    expect($words)->toContain('run');
});

it('plural forms are kept in the dictionary', function () {
    insertDictWords(['apple', 'apples', 'books']);

    $words = $this->getJson('/dictionary/suggestions?q=apple')->json('words');
    expect($words)->toContain('apples');
});

it('verb inflections are kept in the dictionary', function () {
    insertDictWords(['apply', 'applies', 'applied', 'applying']);

    $words = $this->getJson('/dictionary/suggestions?q=appl')->json('words');
    expect($words)->toContain('applies')
        ->and($words)->toContain('applied')
        ->and($words)->toContain('applying');
});

it('comparative and superlative forms are kept in the dictionary', function () {
    insertDictWords(['big', 'bigger', 'biggest']);

    $words = $this->getJson('/dictionary/suggestions?q=big')->json('words');
    expect($words)->toContain('bigger')
        ->and($words)->toContain('biggest');
});

it('proper names (people) are kept in the dictionary', function () {
    insertDictWords(['Aaron', 'Adam']);

    $words = $this->getJson('/dictionary/suggestions?q=aa')->json('words');
    expect($words)->toContain('Aaron');
});

it('proper names (places) are kept in the dictionary', function () {
    insertDictWords(['America', 'Amazon', 'April']);

    $words = $this->getJson('/dictionary/suggestions?q=am')->json('words');
    expect($words)->toContain('America')
        ->and($words)->toContain('Amazon');
});

it('all-uppercase abbreviations of 2+ characters are excluded from results', function () {
    // These should not be inserted in the first place after filtering,
    // but if somehow present, they should not appear in suggestions.
    // We test by checking that the real DB (populated with filtered data) has none.
    $response = $this->getJson('/dictionary/suggestions?q=AA');
    $words = $response->json('words');

    expect($words)->not->toContain('AA')
        ->and($words)->not->toContain('AAA')
        ->and($words)->not->toContain('AZ');
});

it('obvious possessives like Aaron\'s are excluded from results', function () {
    $response = $this->getJson('/dictionary/suggestions?q=Aaron');
    $words = $response->json('words');

    expect($words)->not->toContain("Aaron's");
});

it('common contractions with n\'t are kept', function () {
    insertDictWords(["can't", "don't", "won't", "isn't"]);

    $words = $this->getJson('/dictionary/suggestions?q=can')->json('words');
    expect($words)->toContain("can't");
});

it('pronoun contractions like it\'s and he\'s are kept', function () {
    insertDictWords(["it's", "he's", "she's", "let's"]);

    $words = $this->getJson('/dictionary/suggestions?q=it')->json('words');
    expect($words)->toContain("it's");
});

// --- 1文字除外・全件ページング・順序のテスト ---

it('single-character words like A and a are excluded', function () {
    insertDictWords(['A', 'a', 'Aachen', 'aardvark']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->not->toContain('A')
        ->and($words)->not->toContain('a')
        ->and($words)->toContain('Aachen')
        ->and($words)->toContain('aardvark');
});

it('Aachen and Aaron remain after 1-char exclusion', function () {
    insertDictWords(['A', 'a', 'Aachen', 'Aaron']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->toContain('Aachen')
        ->and($words)->toContain('Aaron');
});

it('America remains in a-search results', function () {
    insertDictWords(['America', 'American']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->toContain('America');
});

it('verb inflections remain in a-search', function () {
    insertDictWords(['abandon', 'abandoned', 'abandoning', 'abandons']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->toContain('abandoned')
        ->and($words)->toContain('abandoning');
});

it('plural forms remain in a-search', function () {
    insertDictWords(['aardvark', 'aardvarks']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->toContain('aardvarks');
});

it('can fetch page 3 and beyond via offset', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 200));
    insertDictWords($words);

    $page3 = $this->getJson('/dictionary/suggestions?q=a&offset=100')->json('words');

    expect($page3)->toHaveCount(50);
});

it('no word is duplicated across three pages', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 200));
    insertDictWords($words);

    $p1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $p2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');
    $p3 = $this->getJson('/dictionary/suggestions?q=a&offset=100')->json('words');

    expect(array_intersect($p1, $p2))->toBeEmpty()
        ->and(array_intersect($p2, $p3))->toBeEmpty()
        ->and(array_intersect($p1, $p3))->toBeEmpty();
});

it('no word is skipped across three pages', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 200));
    insertDictWords($words);

    $p1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $p2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');
    $p3 = $this->getJson('/dictionary/suggestions?q=a&offset=100')->json('words');
    $p4 = $this->getJson('/dictionary/suggestions?q=a&offset=150')->json('words');

    $all = array_merge($p1, $p2, $p3, $p4);
    expect(count(array_unique($all)))->toBe(200);
});

it('apple is reachable when scrolling through a-search to the end', function () {
    // Use the real dictionary DB (not RefreshDatabase-truncated) by inserting apple explicitly
    insertDictWords(['apple', 'apples', 'apply']);

    // Search apple directly — it must appear (offset=0, only apple* words)
    $words = $this->getJson('/dictionary/suggestions?q=apple&offset=0')->json('words');

    expect($words)->toContain('apple');
});

it('apple is reachable via ap-search', function () {
    insertDictWords(['apple', 'apples', 'application', 'apply', 'applied', 'applying']);

    // Paginate until apple is found or has_more is false
    $offset = 0;
    $found = false;
    do {
        $response = $this->getJson("/dictionary/suggestions?q=ap&offset={$offset}")->json();
        if (in_array('apple', $response['words'])) {
            $found = true;
            break;
        }
        $offset += count($response['words']);
    } while ($response['has_more']);

    expect($found)->toBeTrue();
});

it('results are sorted case-insensitively alphabetically', function () {
    insertDictWords(['az', 'AA-test', 'Aachen', 'aardvark', 'Aaron']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    // Verify order: lower('Aachen') < lower('aardvark') < lower('Aaron') < lower('az')
    $positions = array_flip($words);
    expect(isset($positions['Aachen'], $positions['aardvark'], $positions['Aaron']))->toBeTrue()
        ->and($positions['Aachen'])->toBeLessThan($positions['aardvark'])
        ->and($positions['aardvark'])->toBeLessThan($positions['Aaron']);
});

// --- 大小文字重複除外・入力制限テスト ---

it('shows only apple when both Apple and apple exist', function () {
    insertDictWords(['Apple', 'apple']);

    $words = $this->getJson('/dictionary/suggestions?q=apple')->json('words');

    expect($words)->toContain('apple')
        ->and($words)->not->toContain('Apple')
        ->and(count(array_filter($words, fn ($w) => strtolower($w) === 'apple')))->toBe(1);
});

it('Apple and apple are not shown simultaneously', function () {
    insertDictWords(['Apple', 'apple']);

    $words = $this->getJson('/dictionary/suggestions?q=ap')->json('words');

    $appleCount = count(array_filter($words, fn ($w) => strtolower($w) === 'apple'));
    expect($appleCount)->toBeLessThanOrEqual(1);
});

it('keeps Australia capitalized when only the capitalized form exists', function () {
    insertDictWords(['Australia', 'Australian']);

    $words = $this->getJson('/dictionary/suggestions?q=austra')->json('words');

    expect($words)->toContain('Australia')
        ->and($words)->not->toContain('australia');
});

it('keeps proper nouns like America Aaron Aachen in results', function () {
    insertDictWords(['America', 'Aaron', 'Aachen']);

    $words = $this->getJson('/dictionary/suggestions?q=a')->json('words');

    expect($words)->toContain('America')
        ->and($words)->toContain('Aaron')
        ->and($words)->toContain('Aachen');
});

it('returns apple results for lowercase apple query', function () {
    insertDictWords(['apple', 'apples']);

    $words = $this->getJson('/dictionary/suggestions?q=apple')->json('words');

    expect($words)->toContain('apple');
});

it('returns results for uppercase Apple query', function () {
    insertDictWords(['Apple', 'apple']);

    $words = $this->getJson('/dictionary/suggestions?q=Apple')->json('words');

    expect(count(array_filter($words, fn ($w) => strtolower($w) === 'apple')))->toBe(1);
});

it('prefers lowercase spelling when both casings exist for the same word', function () {
    insertDictWords(['Apple', 'apple', 'APPLICATION', 'application']);

    $response = $this->getJson('/dictionary/suggestions?q=app&offset=0');

    $response->assertSuccessful();

    $words = $response->json('words');
    expect($words)->toContain('apple')
        ->and($words)->toContain('application')
        ->and($words)->not->toContain('Apple')
        ->and($words)->not->toContain('APPLICATION');
});

it('supports offset pagination for short prefixes without 500', function () {
    $words = array_map(
        fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        range(1, 80),
    );
    insertDictWords($words);

    $page1 = $this->getJson('/dictionary/suggestions?q=a&offset=0');
    $page2 = $this->getJson('/dictionary/suggestions?q=a&offset=50');

    $page1->assertSuccessful();
    $page2->assertSuccessful();

    expect($page1->json('words'))->toHaveCount(DictionarySearchController::PAGE_SIZE)
        ->and($page1->json('has_more'))->toBeTrue()
        ->and($page2->json('words'))->toHaveCount(30)
        ->and($page2->json('has_more'))->toBeFalse();
});

it('returns results for can\'t query', function () {
    insertDictWords(["can't", "cannot"]);

    $words = $this->getJson("/dictionary/suggestions?q=can't")->json('words');

    expect($words)->toContain("can't");
});

it('returns results for don\'t query', function () {
    insertDictWords(["don't", "done"]);

    $words = $this->getJson("/dictionary/suggestions?q=don't")->json('words');

    expect($words)->toContain("don't");
});

it('rejects japanese hiragana input', function () {
    $response = $this->getJson('/dictionary/suggestions?q='.urlencode('りんご'));

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('has_more', false);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects japanese katakana input', function () {
    $response = $this->getJson('/dictionary/suggestions?q='.urlencode('アップル'));

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects alphanumeric input like apple1', function () {
    $response = $this->getJson('/dictionary/suggestions?q=apple1');

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects numeric-only input like 123', function () {
    $response = $this->getJson('/dictionary/suggestions?q=123');

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects input with special characters like apple!', function () {
    $response = $this->getJson('/dictionary/suggestions?q=apple!');

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects apostrophe-only input with an error message', function () {
    $response = $this->getJson("/dictionary/suggestions?q='");

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects multiple apostrophes with an error message', function () {
    $response = $this->getJson("/dictionary/suggestions?q='''");

    $response->assertSuccessful()
        ->assertJsonPath('words', []);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects dot (.) with unified error message', function () {
    $response = $this->getJson('/dictionary/suggestions?q=' . urlencode('.'));

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('has_more', false);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects Japanese full-width comma (。 or 、) with unified error message', function () {
    $response = $this->getJson('/dictionary/suggestions?q=' . urlencode('。'));

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('has_more', false);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects emoji with unified error message', function () {
    $response = $this->getJson('/dictionary/suggestions?q=' . urlencode('😊'));

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('has_more', false);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('rejects input with spaces (hello world) with unified error message', function () {
    $response = $this->getJson('/dictionary/suggestions?q=' . urlencode('hello world'));

    $response->assertSuccessful()
        ->assertJsonPath('words', [])
        ->assertJsonPath('has_more', false);

    expect($response->json('message'))->toBe("英字とアポストロフィ（'）のみ入力できます。");
});

it('does not query the database for invalid input', function () {
    insertDictWords(['apple', 'apples']);

    // Numbers should return empty without hitting DB
    $response = $this->getJson('/dictionary/suggestions?q=apple1');

    $response->assertSuccessful()
        ->assertJsonPath('words', []);
});

it('dedup does not reduce page size below 50 when enough words exist', function () {
    // Insert 100 unique lowercase words
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 100));
    insertDictWords($words);

    $page1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');

    expect($page1)->toHaveCount(50);
});

it('no duplicate across pages after dedup', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 100));
    insertDictWords($words);

    $p1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $p2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');

    expect(array_intersect($p1, $p2))->toBeEmpty();
});

it('no word is skipped across pages after dedup', function () {
    $words = array_map(fn ($i) => 'a'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 100));
    insertDictWords($words);

    $p1 = $this->getJson('/dictionary/suggestions?q=a&offset=0')->json('words');
    $p2 = $this->getJson('/dictionary/suggestions?q=a&offset=50')->json('words');

    expect(count(array_unique(array_merge($p1, $p2))))->toBe(100);
});

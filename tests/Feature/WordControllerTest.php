<?php

use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can access the words index page', function () {
    $response = $this->get('/words');

    $response->assertSuccessful();
});

it('stores a word when valid english and japanese are submitted', function () {
    $response = $this->post('/words', [
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response->assertRedirect(route('words.index'));

    $this->assertDatabaseHas('words', [
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);
});

it('returns validation error when english is empty', function () {
    $response = $this->from(route('words.index'))->post('/words', [
        'english' => '',
        'japanese' => 'りんご',
    ]);

    $response->assertSessionHasErrors('english');

    $this->assertDatabaseCount('words', 0);
});

it('returns validation error when japanese is empty', function () {
    $response = $this->from(route('words.index'))->post('/words', [
        'english' => 'apple',
        'japanese' => '',
    ]);

    $response->assertSessionHasErrors('japanese');

    $this->assertDatabaseCount('words', 0);
});

it('deletes a word', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->delete("/words/{$word->id}");

    $response->assertRedirect(route('words.index'));

    $this->assertDatabaseMissing('words', [
        'id' => $word->id,
    ]);
});

it('marks a normal word as hard', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $response = $this->patch("/words/{$word->id}/hard");

    $response->assertRedirect(route('words.index'));

    expect($word->fresh()->is_hard)->toBeTrue();
});

it('unmarks a hard word', function () {
    $word = Word::create([
        'english' => 'necessary',
        'japanese' => '必要な',
        'is_hard' => true,
    ]);

    $response = $this->patch("/words/{$word->id}/hard");

    $response->assertRedirect(route('words.index'));

    expect($word->fresh()->is_hard)->toBeFalse();
});

it('returns json when toggling hard via ajax', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $response = $this->patchJson("/words/{$word->id}/hard");

    $response
        ->assertSuccessful()
        ->assertJson([
            'id' => $word->id,
            'is_hard' => true,
        ]);

    expect($word->fresh()->is_hard)->toBeTrue();
});

it('returns json when unmarking hard via ajax', function () {
    $word = Word::create([
        'english' => 'necessary',
        'japanese' => '必要な',
        'is_hard' => true,
    ]);

    $response = $this->patchJson("/words/{$word->id}/hard");

    $response
        ->assertSuccessful()
        ->assertJson([
            'id' => $word->id,
            'is_hard' => false,
        ]);

    expect($word->fresh()->is_hard)->toBeFalse();
});

it('shows only hard words when filter is hard', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    Word::create([
        'english' => 'necessary',
        'japanese' => '必要な',
        'is_hard' => true,
    ]);

    $response = $this->get('/words?filter=hard');

    $response->assertSuccessful()
        ->assertSee('necessary')
        ->assertSee('必要な')
        ->assertDontSee('apple')
        ->assertDontSee('りんご');
});

it('shows all words on the index page', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    Word::create([
        'english' => 'necessary',
        'japanese' => '必要な',
        'is_hard' => true,
    ]);

    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('りんご')
        ->assertSee('necessary')
        ->assertSee('必要な');
});

it('does not change english or japanese when toggling hard', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $this->patch("/words/{$word->id}/hard");

    $word->refresh();

    expect($word->english)->toBe('apple')
        ->and($word->japanese)->toBe('りんご')
        ->and($word->is_hard)->toBeTrue();
});

it('searches english words by partial match', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::create(['english' => 'application', 'japanese' => '応用']);
    Word::create(['english' => 'banana', 'japanese' => 'バナナ']);

    $response = $this->get('/words?q=app');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('application')
        ->assertDontSee('banana');
});

it('searches english words case-insensitively', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);

    $response = $this->get('/words?q=APP');

    $response->assertSuccessful()
        ->assertSee('apple');
});

it('shows all words when the search query is empty', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::create(['english' => 'banana', 'japanese' => 'バナナ']);

    $response = $this->get('/words?q=');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('banana');
});

it('combines english search with the hard filter', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご', 'is_hard' => true]);
    Word::create(['english' => 'application', 'japanese' => '応用', 'is_hard' => false]);
    Word::create(['english' => 'banana', 'japanese' => 'バナナ', 'is_hard' => true]);

    $response = $this->get('/words?q=app&filter=hard');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertDontSee('application')
        ->assertDontSee('banana');
});

it('shows an empty search result page when nothing matches', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);

    $response = $this->get('/words?q=zzz');

    $response->assertSuccessful()
        ->assertSee('該当する単語がありません')
        ->assertDontSee('apple');
});

// --- 今回追加テスト ---

it('redirects GET /words/create to /dictionary', function () {
    $response = $this->get('/words/create');

    $response->assertRedirect(route('dictionary.index'));
});

it('words index page has a link to dictionary', function () {
    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertSee(route('dictionary.index'));
});

it('shows empty message when no words are registered', function () {
    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertSee('単語はまだ登録されていません');
});

it('shows a word added via dictionary store on the words index', function () {
    Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('りんご');
});

it('shows only non-hard words when filter is normal', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご', 'is_hard' => false]);
    Word::create(['english' => 'necessary', 'japanese' => '必要な', 'is_hard' => true]);

    $response = $this->get('/words?filter=normal');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertDontSee('necessary');
});

it('shows all words when no filter is set', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご', 'is_hard' => false]);
    Word::create(['english' => 'necessary', 'japanese' => '必要な', 'is_hard' => true]);

    $response = $this->get('/words');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('necessary');
});

it('combines english search with the normal filter', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご', 'is_hard' => false]);
    Word::create(['english' => 'application', 'japanese' => '応用', 'is_hard' => true]);
    Word::create(['english' => 'banana', 'japanese' => 'バナナ', 'is_hard' => false]);

    $response = $this->get('/words?q=app&filter=normal');

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertDontSee('application')
        ->assertDontSee('banana');
});

it('does not search japanese translations', function () {
    Word::create(['english' => 'apple', 'japanese' => 'りんご']);

    $response = $this->get('/words?q=りんご');

    $response->assertSuccessful()
        ->assertSee('該当する単語がありません')
        ->assertDontSee('apple');
});

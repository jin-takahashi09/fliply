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
    $response = $this->from(route('words.create'))->post('/words', [
        'english' => '',
        'japanese' => 'りんご',
    ]);

    $response->assertRedirect(route('words.create'))
        ->assertSessionHasErrors('english');

    $this->assertDatabaseCount('words', 0);
});

it('returns validation error when japanese is empty', function () {
    $response = $this->from(route('words.create'))->post('/words', [
        'english' => 'apple',
        'japanese' => '',
    ]);

    $response->assertRedirect(route('words.create'))
        ->assertSessionHasErrors('japanese');

    $this->assertDatabaseCount('words', 0);
});

it('can access the word edit page', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->get("/words/{$word->id}/edit");

    $response->assertSuccessful()
        ->assertSee('apple')
        ->assertSee('りんご');
});

it('updates a word when valid english and japanese are submitted', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->put("/words/{$word->id}", [
        'english' => 'orange',
        'japanese' => 'オレンジ',
    ]);

    $response->assertRedirect(route('words.index'));

    $this->assertDatabaseHas('words', [
        'id' => $word->id,
        'english' => 'orange',
        'japanese' => 'オレンジ',
    ]);

    $this->assertDatabaseMissing('words', [
        'id' => $word->id,
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);
});

it('returns validation error when updating with empty english', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->from(route('words.edit', $word))->put("/words/{$word->id}", [
        'english' => '',
        'japanese' => 'オレンジ',
    ]);

    $response->assertRedirect(route('words.edit', $word))
        ->assertSessionHasErrors('english');

    $this->assertDatabaseHas('words', [
        'id' => $word->id,
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);
});

it('returns validation error when updating with empty japanese', function () {
    $word = Word::create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $response = $this->from(route('words.edit', $word))->put("/words/{$word->id}", [
        'english' => 'orange',
        'japanese' => '',
    ]);

    $response->assertRedirect(route('words.edit', $word))
        ->assertSessionHasErrors('japanese');

    $this->assertDatabaseHas('words', [
        'id' => $word->id,
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);
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

<?php

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

<?php

use App\Models\DictionaryWord;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps each user word list isolated on the words index', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    Word::factory()->for($userA)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $this->actingAs($userB)
        ->get(route('words.index'))
        ->assertSuccessful()
        ->assertDontSee('apple')
        ->assertSee('単語はまだ登録されていません');
});

it('allows each user to register the same english word independently', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    Word::factory()->for($userA)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $this->actingAs($userB)
        ->post('/words', [
            'english' => 'apple',
            'japanese' => 'りんご',
        ])
        ->assertRedirect(route('words.index'));

    $this->assertDatabaseHas('words', [
        'user_id' => $userB->id,
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    expect(Word::where('english', 'apple')->count())->toBe(2);
});

it('shows home word counts per user', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    Word::factory()->for($userA)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($userA)->create(['english' => 'book', 'japanese' => '本', 'is_hard' => true]);
    Word::factory()->for($userB)->create(['english' => 'cat', 'japanese' => '猫']);

    $this->actingAs($userA)
        ->get(route('home'))
        ->assertSuccessful()
        ->assertSee('2')
        ->assertSee('apple')
        ->assertDontSee('cat');

    $this->actingAs($userB)
        ->get(route('home'))
        ->assertSuccessful()
        ->assertSee('1')
        ->assertSee('cat')
        ->assertDontSee('apple');
});

it('filters hard words per user on the words index', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    Word::factory()->for($userA)->create(['english' => 'apple', 'japanese' => 'りんご', 'is_hard' => true]);
    Word::factory()->for($userB)->create(['english' => 'book', 'japanese' => '本', 'is_hard' => true]);

    $this->actingAs($userA)
        ->get('/words?filter=hard')
        ->assertSuccessful()
        ->assertSee('apple')
        ->assertDontSee('book');
});

it('scopes study sessions to the logged in user', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    Word::factory()->for($userA)->create(['english' => 'apple', 'japanese' => 'りんご']);
    Word::factory()->for($userB)->create(['english' => 'zebra', 'japanese' => 'シマウマ']);

    $this->actingAs($userA)
        ->get(route('study.session'))
        ->assertSuccessful()
        ->assertSee('apple')
        ->assertDontSee('zebra');
});

it('returns not found when another users word id is deleted directly', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    $word = Word::factory()->for($userA)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $this->actingAs($userB)
        ->delete("/words/{$word->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('words', ['id' => $word->id]);
});

it('returns not found when another users word id hard toggle is requested', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    $word = Word::factory()->for($userA)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
        'is_hard' => false,
    ]);

    $this->actingAs($userB)
        ->patch("/words/{$word->id}/hard")
        ->assertNotFound();

    expect($word->fresh()->is_hard)->toBeFalse();
});

it('does not delete another users words in bulk delete', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    $word = Word::factory()->for($userA)->create([
        'english' => 'apple',
        'japanese' => 'りんご',
    ]);

    $this->actingAs($userB)
        ->deleteJson('/words/bulk', ['ids' => [$word->id]])
        ->assertUnprocessable()
        ->assertJson([
            'message' => '削除対象の単語が見つかりません。',
        ]);

    $this->assertDatabaseHas('words', ['id' => $word->id]);
});

it('keeps dictionary_words shared across users', function () {
    DictionaryWord::create(['word' => 'apple']);
    DictionaryWord::create(['word' => 'application']);

    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    $responseA = $this->actingAs($userA)->getJson('/dictionary/suggestions?q=ap');
    $responseB = $this->actingAs($userB)->getJson('/dictionary/suggestions?q=ap');

    $responseA->assertSuccessful();
    $responseB->assertSuccessful();
    expect($responseA->json('words'))->toEqual($responseB->json('words'));
});

it('stores dictionary additions only for the logged in user', function () {
    $userA = User::factory()->create(['email' => 'a@example.com']);
    $userB = User::factory()->create(['email' => 'b@example.com']);

    $this->actingAs($userA)->postJson('/dictionary/words', [
        'english' => 'apple',
        'japanese' => 'apple',
    ])->assertSuccessful();

    $this->assertDatabaseHas('words', [
        'user_id' => $userA->id,
        'english' => 'apple',
        'japanese' => 'apple',
    ]);

    $this->assertDatabaseMissing('words', [
        'user_id' => $userB->id,
        'english' => 'apple',
    ]);
});

it('redirects guests from protected pages to login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    'home' => ['/'],
    'words index' => ['/words'],
    'study settings' => ['/study'],
    'study session' => ['/study/session'],
]);

it('redirects guests from dictionary word mutations to login', function () {
    $this->postJson('/dictionary/words', [
        'english' => 'apple',
        'japanese' => 'apple',
    ])->assertUnauthorized();

    $this->deleteJson('/dictionary/words', [
        'english' => 'apple',
        'japanese' => 'apple',
    ])->assertUnauthorized();
});

it('allows guest access to dictionary browsing endpoints', function () {
    DictionaryWord::create(['word' => 'apple']);

    $this->get(route('dictionary.index'))->assertSuccessful();
    $this->getJson('/dictionary/suggestions?q=ap')->assertSuccessful();
});

it('registers a new user and logs them in', function () {
    $response = $this->post('/register', [
        'name' => 'Fliply User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post('/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
});

it('logs out and clears the authenticated session', function () {
    $user = User::factory()->create([
        'email' => 'logout@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rate limits repeated failed login attempts', function () {
    User::factory()->create([
        'email' => 'rate@example.com',
        'password' => 'password123',
    ]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from(route('login'))->post('/login', [
            'email' => 'rate@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    $this->from(route('login'))->post('/login', [
        'email' => 'rate@example.com',
        'password' => 'wrong-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->get('email')[0])
        ->toContain('ログイン試行回数が上限に達しました');

    $this->assertGuest();
});

it('clears the login rate limit after a successful login', function () {
    User::factory()->create([
        'email' => 'recover@example.com',
        'password' => 'password123',
    ]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post('/login', [
            'email' => 'recover@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/login', [
        'email' => 'recover@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

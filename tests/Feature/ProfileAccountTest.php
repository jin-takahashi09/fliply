<?php

use App\Models\User;
use App\Models\UserAvatar;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('updates the authenticated users name', function () {
    $user = User::factory()->create(['name' => '旧名前']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => '新しい名前',
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    expect($user->fresh()->name)->toBe('新しい名前');
});

it('redirects guests away from the profile page', function () {
    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'));

    $this->patch(route('profile.update'), [
        'name' => '誰か',
    ])->assertRedirect(route('login'));
});

it('does not update another user when extra user ids are sent', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    $this->actingAs($userA)
        ->patch(route('profile.update'), [
            'name' => 'Changed',
            'user_id' => $userB->id,
            'id' => $userB->id,
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    expect($userA->fresh()->name)->toBe('Changed');
    expect($userB->fresh()->name)->toBe('Bob');
});

it('stores a valid jpeg avatar for the authenticated user', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 80, 80);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => $file,
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    $avatar = UserAvatar::query()->where('user_id', $user->id)->first();

    expect($avatar)->not->toBeNull()
        ->and($avatar->mime_type)->toBe('image/jpeg')
        ->and(strlen($avatar->binaryContents()))->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('profile.avatar'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('rejects invalid avatar files', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('avatar');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('icon.gif', 40, 40),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('avatar');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->create('icon.svg', 20, 'image/svg+xml'),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('avatar');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('huge.jpg', 80, 80)->size(2048),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('avatar');

    expect(UserAvatar::query()->count())->toBe(0);
});

it('replaces an existing avatar with a new image', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('first.jpg', 60, 60),
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('second.png', 80, 80),
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    expect(UserAvatar::query()->where('user_id', $user->id)->count())->toBe(1);

    $avatar = UserAvatar::query()->where('user_id', $user->id)->first();

    expect($avatar->mime_type)->toBe('image/png');
});

it('deletes the authenticated users avatar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 50, 50),
        ]);

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionMissing('status');

    expect(UserAvatar::query()->where('user_id', $user->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('profile.avatar'))
        ->assertNotFound();
});

it('does not delete another users avatar', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->patch(route('profile.update'), [
            'name' => $userA->name,
            'avatar' => UploadedFile::fake()->image('a.jpg', 40, 40),
        ]);

    $this->actingAs($userB)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    expect(UserAvatar::query()->where('user_id', $userA->id)->exists())->toBeTrue();
});

it('does not serve another users avatar', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->patch(route('profile.update'), [
            'name' => $userA->name,
            'avatar' => UploadedFile::fake()->image('a.jpg', 40, 40),
        ]);

    $this->actingAs($userB)
        ->get(route('profile.avatar'))
        ->assertNotFound();
});

it('uses distinct avatar cache busters per user even with the same updated_at', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->patch(route('profile.update'), [
            'name' => $userA->name,
            'avatar' => UploadedFile::fake()->image('a.jpg', 40, 40),
        ]);

    $this->actingAs($userB)
        ->patch(route('profile.update'), [
            'name' => $userB->name,
            'avatar' => UploadedFile::fake()->image('b.jpg', 40, 40),
        ]);

    $sharedTimestamp = '2026-08-30 10:00:00';

    UserAvatar::query()->where('user_id', $userA->id)->update(['updated_at' => $sharedTimestamp]);
    UserAvatar::query()->where('user_id', $userB->id)->update(['updated_at' => $sharedTimestamp]);

    $versionA = $userA->fresh()->avatarVersion();
    $versionB = $userB->fresh()->avatarVersion();

    expect($versionA)->not->toBe($versionB)
        ->and($versionA)->toBe("{$userA->id}-{$sharedTimestamp}")
        ->and($versionB)->toBe("{$userB->id}-{$sharedTimestamp}");

    $this->actingAs($userA)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('profile.avatar', ['v' => $versionA]), false);

    $this->actingAs($userB)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('profile.avatar', ['v' => $versionB]), false);
});

it('rejects a password change when the current password is wrong', function () {
    $user = User::factory()->create([
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->patch(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->patch(route('account.password.update'), [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertRedirect(route('home'))
        ->assertSessionMissing('status');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password123', $user->fresh()->password))->toBeFalse();
});

it('allows login with the new password after a password change', function () {
    $user = User::factory()->create([
        'email' => 'changed@example.com',
        'password' => 'password123',
    ]);

    $this->actingAs($user)
        ->patch(route('account.password.update'), [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->from(route('login'))->post('/login', [
        'email' => 'changed@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->post('/login', [
        'email' => 'changed@example.com',
        'password' => 'newpassword123',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

it('still logs out with a post request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('renders avatar delete below change in the profile picker', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 50, 50),
        ]);

    $html = $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('名前とプロフィール画像を変更できます。')
        ->getContent();

    expect($html)->toContain('profile-avatar-picker')
        ->and(strpos($html, '画像を変更'))->toBeLessThan(strpos($html, '画像を削除'))
        ->and($html)->toContain('profile-avatar__delete-form');
});

it('keeps profile update and avatar delete as sibling forms', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 50, 50),
        ]);

    $html = $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//form//form')->length)->toBe(0);

    $updateForm = $xpath->query('//form[@id="profile-update-form"]')->item(0);
    $deleteForm = $xpath->query('//form[contains(@class, "profile-avatar__delete-form")]')->item(0);

    expect($updateForm)->not->toBeNull()
        ->and($deleteForm)->not->toBeNull();

    $updateAction = parse_url($updateForm->getAttribute('action'), PHP_URL_PATH);
    $deleteAction = parse_url($deleteForm->getAttribute('action'), PHP_URL_PATH);

    expect($updateAction)->toBe('/profile')
        ->and($deleteAction)->toBe('/profile/avatar');

    $updateMethod = $xpath->query('.//input[@name="_method"]', $updateForm)->item(0)?->getAttribute('value');
    $deleteMethod = $xpath->query('.//input[@name="_method"]', $deleteForm)->item(0)?->getAttribute('value');

    expect($updateMethod)->toBe('PATCH')
        ->and($deleteMethod)->toBe('DELETE');
});

it('does not allow DELETE on the profile update route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/profile')
        ->assertStatus(405);
});

it('redirects guests away from account settings', function () {
    $this->get(route('account.edit'))
        ->assertRedirect(route('login'));
});

it('does not show the account settings description', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertSee('アカウント設定')
        ->assertDontSee('メールアドレスの確認と、パスワードの変更ができます。');
});

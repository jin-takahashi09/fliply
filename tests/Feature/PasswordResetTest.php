<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('shows the profile edit heading without a trailing period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('プロフィール編集')
        ->assertDontSee('プロフィールを編集。')
        ->assertDontSee('名前とプロフィール画像を変更できます。');
});

it('renders a clickable avatar picker with preview support', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-profile-avatar-picker', false)
        ->assertSee('data-profile-avatar-input', false)
        ->assertSee('data-profile-avatar-preview', false)
        ->assertSee('画像を変更')
        ->assertSee('accept="image/jpeg,image/png,image/webp"', false);
});

it('shows a generic status message for unknown reset emails', function () {
    Notification::fake();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas(
            'status',
            '登録されている場合は、再設定メールを送信しました。',
        )
        ->assertSessionMissing('errors');

    Notification::assertNothingSent();
});

it('does not reveal whether an email is registered in validation errors', function () {
    Notification::fake();

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])
        ->assertSessionDoesntHaveErrors('email');

    Notification::assertNothingSent();
});

it('sends a reset link notification to registered users', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'reset@example.com',
        ])->assertRedirect(route('password.request'))
        ->assertSessionHas(
            'status',
            '登録されている場合は、再設定メールを送信しました。',
        );

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('sends a Fliply branded password reset email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'branded@example.com',
    ]);

    $this->post(route('password.email'), [
        'email' => 'branded@example.com',
    ]);

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $htmlView = is_array($mail->view) ? $mail->view['html'] : $mail->view;
            $html = view($htmlView, $mail->viewData)->render();
            $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
            $expectedUrl = url(route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ], false));

            expect($mail->subject)->toBe('パスワード再設定のお知らせ | Fliply')
                ->and($html)->toContain('Fliply')
                ->and($html)->toContain('パスワードを再設定する')
                ->and($html)->toContain("このリンクは{$expireMinutes}分間有効です。")
                ->and($html)->toContain($expectedUrl)
                ->and($html)->not->toContain('Laravel')
                ->and($html)->not->toContain('Laravel Logo')
                ->and($html)->not->toContain('laravel.com');

            return true;
        },
    );
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'valid@example.com',
        'password' => 'password123',
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'valid@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password123', $user->fresh()->password))->toBeFalse();
});

it('rejects password reset requests with an invalid token', function () {
    $user = User::factory()->create([
        'email' => 'invalid@example.com',
        'password' => 'password123',
    ]);

    $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'invalid@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
        ->assertSessionHasErrors('email');

    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

it('allows login with the new password after a reset', function () {
    $user = User::factory()->create([
        'email' => 'login-after-reset@example.com',
        'password' => 'password123',
    ]);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'login-after-reset@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $this->post('/login', [
        'email' => 'login-after-reset@example.com',
        'password' => 'newpassword123',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('links to forgot password from the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('password.request'), false)
        ->assertSee('パスワードを忘れた方');
});

it('links to forgot password from account settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertSee(route('password.request'), false)
        ->assertSee('メールで再設定');
});

it('throttles repeated password reset requests', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'throttle@example.com',
    ]);

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'throttle@example.com',
        ])->assertRedirect(route('password.request'));

    $this->from(route('password.request'))
        ->post(route('password.email'), [
            'email' => 'throttle@example.com',
        ])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');
});

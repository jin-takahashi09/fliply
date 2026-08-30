@extends('layouts.app')

@section('title', 'アカウント設定 - Fliply')

@section('content')
    <section class="form-heading reveal">
        <p class="eyebrow">ACCOUNT</p>
        <h1>アカウント設定。</h1>
    </section>

    <div class="account-settings">
        <section class="form-card reveal reveal--delay-1">
            <div class="account-email">
                <div class="account-email__label">
                    <span>メールアドレス</span>
                    <small>EMAIL</small>
                </div>
                <p class="account-email__value">{{ $user->email }}</p>
                <p class="account-email__hint">メールアドレスの変更はできません。</p>
            </div>
        </section>

        <section class="form-card reveal reveal--delay-2">
            <form method="POST" action="{{ route('account.password.update') }}" class="word-form">
                @csrf
                @method('PATCH')

                <div class="field-group">
                    <label for="current_password">
                        <span>現在のパスワード</span>
                        <small>CURRENT</small>
                    </label>
                    <div class="field-control field-control--plain {{ $errors->has('current_password') ? 'has-error' : '' }}">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                    @error('current_password')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="password">
                        <span>新しいパスワード</span>
                        <small>NEW</small>
                    </label>
                    <div class="field-control field-control--plain {{ $errors->has('password') ? 'has-error' : '' }}">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="8文字以上"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                    @error('password')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="password_confirmation">
                        <span>新しいパスワード確認</span>
                        <small>CONFIRM</small>
                    </label>
                    <div class="field-control field-control--plain">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="もう一度入力"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <div class="form-actions form-actions--single">
                    <button type="submit" class="primary-button">パスワードを変更</button>
                </div>
            </form>

            <p class="account-forgot">
                パスワードを忘れましたか？
                <a href="{{ route('password.request') }}">メールで再設定 →</a>
            </p>
        </section>
    </div>
@endsection

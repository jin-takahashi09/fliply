@extends('layouts.app', ['hideNav' => true])

@section('title', '新しいパスワード - Fliply')

@section('content')
    <section class="auth-screen">
        <div class="auth-card">
            <p class="auth-brand brand">
                <span class="brand__mark" aria-hidden="true">
                    <x-icon name="fliply-mark" :size="24" />
                </span>
                <span class="brand__name">Fliply</span>
            </p>

            <header class="auth-heading">
                <p class="eyebrow">NEW PASSWORD</p>
                <h1>新しいパスワード。</h1>
            </header>

            <form method="POST" action="{{ route('password.update') }}" class="word-form auth-form">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div class="field-group">
                    <label for="email">
                        <span>メールアドレス</span>
                        <small>EMAIL</small>
                    </label>
                    <div class="field-control {{ $errors->has('email') ? 'has-error' : '' }}">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            placeholder="you@example.com"
                            autocomplete="email"
                            required
                            autofocus
                        >
                    </div>
                    @error('email')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="password">
                        <span>新しいパスワード</span>
                        <small>PASSWORD</small>
                    </label>
                    <div class="field-control {{ $errors->has('password') ? 'has-error' : '' }}">
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
                    <div class="field-control">
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

                <button type="submit" class="primary-button auth-submit">
                    パスワードを保存
                </button>
            </form>

            <p class="auth-switch">
                <a href="{{ route('login') }}">ログインへ戻る →</a>
            </p>
        </div>
    </section>
@endsection

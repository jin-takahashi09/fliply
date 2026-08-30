@extends('layouts.app', ['hideNav' => true])

@section('title', 'ログイン - Fliply')

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
                <p class="eyebrow">LOGIN</p>
            </header>

            <form method="POST" action="{{ route('login') }}" class="word-form auth-form">
                @csrf

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
                            value="{{ old('email') }}"
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
                        <span>パスワード</span>
                        <small>PASSWORD</small>
                    </label>
                    <div class="field-control {{ $errors->has('password') ? 'has-error' : '' }}">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="パスワード"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                    @error('password')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="primary-button auth-submit">
                    ログイン
                </button>
            </form>

            <p class="auth-switch">
                はじめてですか？
                <a href="{{ route('register') }}">新規登録 →</a>
            </p>
        </div>
    </section>
@endsection

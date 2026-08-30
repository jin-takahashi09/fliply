@extends('layouts.app', ['hideNav' => true])

@section('title', 'パスワード再設定 - Fliply')

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
                <p class="eyebrow">RESET</p>
                <h1>パスワードを再設定。</h1>
            </header>

            @if (session('status'))
                <p class="auth-status" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="word-form auth-form">
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

                <button type="submit" class="primary-button auth-submit">
                    再設定メールを送信
                </button>
            </form>

            <p class="auth-switch">
                <a href="{{ route('login') }}">ログインへ戻る →</a>
            </p>
        </div>
    </section>
@endsection

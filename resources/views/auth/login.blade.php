@extends('layouts.app', ['hideNav' => true])

@section('title', 'ログイン - Fliply')

@section('content')
    <section class="form-heading reveal">
        <p class="eyebrow">LOGIN</p>
        <h1>ログイン</h1>
        <p>メールアドレスとパスワードでログインしてください。</p>
    </section>

    <section class="form-card reveal reveal--delay-1">
        <form method="POST" action="{{ route('login') }}" class="word-form">
            @csrf

            <div class="field-group">
                <label for="email">
                    <span>メールアドレス</span>
                    <small>EMAIL</small>
                </label>
                <div class="field-control {{ $errors->has('email') ? 'has-error' : '' }}">
                    <x-icon name="search" :size="18" />
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
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field-group">
                <label for="password">
                    <span>パスワード</span>
                    <small>PASSWORD</small>
                </label>
                <div class="field-control {{ $errors->has('password') ? 'has-error' : '' }}">
                    <x-icon name="book" :size="18" />
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
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <a href="{{ route('register') }}" class="secondary-button">新規登録</a>
                <button type="submit" class="primary-button">
                    <x-icon name="check" :size="17" />
                    ログイン
                </button>
            </div>
        </form>
    </section>
@endsection

@extends('layouts.app', ['hideNav' => true])

@section('title', '新規登録 - Fliply')

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
                <p class="eyebrow">REGISTER</p>
                <h1>Fliplyをはじめよう。</h1>
            </header>

            <form method="POST" action="{{ route('register') }}" class="word-form auth-form">
                @csrf

                <div class="field-group">
                    <label for="name">
                        <span>名前</span>
                        <small>NAME</small>
                    </label>
                    <div class="field-control {{ $errors->has('name') ? 'has-error' : '' }}">
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="例：Fliply 太郎"
                            autocomplete="name"
                            required
                            autofocus
                        >
                    </div>
                    @error('name')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

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
                        <span>パスワード確認</span>
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
                    新規登録
                </button>
            </form>

            <p class="auth-switch">
                アカウントをお持ちですか？
                <a href="{{ route('login') }}">ログイン →</a>
            </p>
        </div>
    </section>
@endsection

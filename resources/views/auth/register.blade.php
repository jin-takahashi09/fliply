@extends('layouts.app', ['hideNav' => true])

@section('title', '新規登録 - Fliply')

@section('content')
    <section class="form-heading reveal">
        <p class="eyebrow">REGISTER</p>
        <h1>新規登録</h1>
        <p>アカウントを作成して、自分専用の単語帳を始めましょう。</p>
    </section>

    <section class="form-card reveal reveal--delay-1">
        <form method="POST" action="{{ route('register') }}" class="word-form">
            @csrf

            <div class="field-group">
                <label for="name">
                    <span>名前</span>
                    <small>NAME</small>
                </label>
                <div class="field-control {{ $errors->has('name') ? 'has-error' : '' }}">
                    <x-icon name="search" :size="18" />
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
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

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
                        placeholder="8文字以上"
                        autocomplete="new-password"
                        required
                    >
                </div>
                @error('password')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field-group">
                <label for="password_confirmation">
                    <span>パスワード確認</span>
                    <small>CONFIRM</small>
                </label>
                <div class="field-control">
                    <x-icon name="book" :size="18" />
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

            <div class="form-actions">
                <a href="{{ route('login') }}" class="secondary-button">ログイン</a>
                <button type="submit" class="primary-button">
                    <x-icon name="check" :size="17" />
                    登録する
                </button>
            </div>
        </form>
    </section>
@endsection

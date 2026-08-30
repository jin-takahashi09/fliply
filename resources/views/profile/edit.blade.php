@extends('layouts.app')

@section('title', 'プロフィール編集 - Fliply')

@section('content')
    <section class="form-heading reveal">
        <p class="eyebrow">PROFILE</p>
        <h1>プロフィール編集</h1>
    </section>

    <section class="form-card reveal reveal--delay-1">
        <div class="profile-edit">
            <div class="profile-avatar-picker" data-profile-avatar-picker>
                <input
                    form="profile-update-form"
                    type="file"
                    id="avatar"
                    name="avatar"
                    class="profile-avatar-picker__input"
                    accept="image/jpeg,image/png,image/webp"
                    data-profile-avatar-input
                >

                <label for="avatar" class="profile-avatar-picker__preview" data-profile-avatar-preview>
                    @if ($avatarVersion)
                        <img
                            src="{{ route('profile.avatar', ['v' => $avatarVersion]) }}"
                            alt=""
                            width="112"
                            height="112"
                            class="user-menu__photo"
                            data-profile-avatar-image
                        >
                    @else
                        <span class="profile-avatar-picker__fallback" data-profile-avatar-fallback>
                            <x-icon name="user" :size="40" />
                        </span>
                    @endif
                </label>

                <label for="avatar" class="profile-avatar-picker__change">
                    画像を変更
                </label>

                @if ($avatarVersion)
                    <form
                        method="POST"
                        action="{{ route('profile.avatar.destroy') }}"
                        class="profile-avatar__delete-form"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="profile-avatar__delete">画像を削除</button>
                    </form>
                @endif

                @error('avatar')
                    <p class="field-error profile-avatar-picker__error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <form
                id="profile-update-form"
                method="POST"
                action="{{ route('profile.update') }}"
                class="word-form profile-form"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PATCH')

                <div class="field-group">
                    <label for="name">
                        <span>名前</span>
                        <small>NAME</small>
                    </label>
                    <div class="field-control field-control--plain {{ $errors->has('name') ? 'has-error' : '' }}">
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            maxlength="255"
                            autocomplete="name"
                            required
                        >
                    </div>
                    @error('name')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-actions form-actions--single">
                    <button type="submit" class="primary-button">保存する</button>
                </div>
            </form>
        </div>
    </section>
@endsection

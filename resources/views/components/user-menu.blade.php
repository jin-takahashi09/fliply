<div class="user-menu" data-user-menu>
    @php($avatarVersion = auth()->user()->avatarVersion())

    <button
        type="button"
        class="user-menu__trigger"
        data-user-menu-trigger
        aria-expanded="false"
        aria-controls="user-menu-panel"
        aria-label="ユーザーメニュー"
    >
        @if ($avatarVersion)
            <img
                src="{{ route('profile.avatar', ['v' => $avatarVersion]) }}"
                alt=""
                width="42"
                height="42"
                class="user-menu__photo"
            >
        @else
            <x-icon name="user" :size="20" />
        @endif
    </button>

    <div
        class="user-menu__panel"
        id="user-menu-panel"
        data-user-menu-panel
        hidden
    >
        <div class="user-menu__identity">
            <span class="user-menu__identity-photo" aria-hidden="true">
                @if ($avatarVersion)
                    <img
                        src="{{ route('profile.avatar', ['v' => $avatarVersion]) }}"
                        alt=""
                        width="32"
                        height="32"
                        class="user-menu__photo"
                    >
                @else
                    <x-icon name="user" :size="16" />
                @endif
            </span>
            <p class="user-menu__name">{{ auth()->user()->name }}</p>
        </div>

        <a href="{{ route('profile.edit') }}" class="user-menu__item">
            <span>プロフィール編集</span>
            <x-icon name="arrow-right" :size="14" />
        </a>

        <a href="{{ route('account.edit') }}" class="user-menu__item">
            <span>アカウント設定</span>
            <x-icon name="arrow-right" :size="14" />
        </a>

        <div class="user-menu__divider" aria-hidden="true"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="user-menu__logout">
                <x-icon name="logout" :size="16" />
                ログアウト
            </button>
        </form>
    </div>
</div>

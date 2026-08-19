@php
    $editing = isset($word);
@endphp

<form method="POST" action="{{ $editing ? route('words.update', $word) : route('words.store') }}" class="word-form">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="field-group">
        <label for="english">
            <span>英単語</span>
            <small>ENGLISH</small>
        </label>
        <div class="field-control {{ $errors->has('english') ? 'has-error' : '' }}">
            <x-icon name="search" :size="18" />
            <input
                type="text"
                id="english"
                name="english"
                value="{{ old('english', $word->english ?? '') }}"
                placeholder="例：resilient"
                autocomplete="off"
                required
                autofocus
            >
        </div>
        @error('english')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="field-group">
        <label for="japanese">
            <span>意味</span>
            <small>MEANING</small>
        </label>
        <div class="field-control {{ $errors->has('japanese') ? 'has-error' : '' }}">
            <x-icon name="book" :size="18" />
            <input
                type="text"
                id="japanese"
                name="japanese"
                value="{{ old('japanese', $word->japanese ?? '') }}"
                placeholder="例：回復力のある、しなやかな"
                required
            >
        </div>
        @error('japanese')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>


    <div class="form-actions">
        <a href="{{ route('words.index') }}" class="secondary-button">キャンセル</a>
        <button type="submit" class="primary-button">
            <x-icon name="check" :size="17" />
            {{ $editing ? '変更を保存' : '単語を追加' }}
        </button>
    </div>
</form>

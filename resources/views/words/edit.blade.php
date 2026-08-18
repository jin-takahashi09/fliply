<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>単語編集 - Fliply</title>
</head>
<body>
    <h1>単語編集</h1>

    <p><a href="{{ route('words.index') }}">一覧へ戻る</a></p>

    <form method="POST" action="{{ route('words.update', $word) }}">
        @csrf
        @method('PUT')

        <div>
            <label for="english">英単語</label><br>
            <input type="text" id="english" name="english" value="{{ old('english', $word->english) }}">
            @error('english')
                <div>{{ $message }}</div>
            @enderror
        </div>

        <br>

        <div>
            <label for="japanese">日本語</label><br>
            <input type="text" id="japanese" name="japanese" value="{{ old('japanese', $word->japanese) }}">
            @error('japanese')
                <div>{{ $message }}</div>
            @enderror
        </div>

        <br>

        <button type="submit">更新</button>
    </form>
</body>
</html>

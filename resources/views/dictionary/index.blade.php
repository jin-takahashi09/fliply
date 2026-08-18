<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>辞書検索 - Fliply</title>
</head>
<body>
    <h1>辞書検索</h1>

    <p><a href="{{ route('words.index') }}">単語一覧へ</a></p>

    <p>
        <label for="dictionary-query">英単語を検索</label><br>
        <input type="text" id="dictionary-query" autocomplete="off">
    </p>

    <p id="dictionary-message"></p>

    <ul id="dictionary-results"></ul>

    <script>
        const input = document.getElementById('dictionary-query');
        const results = document.getElementById('dictionary-results');
        const message = document.getElementById('dictionary-message');
        const suggestionsUrl = @json(route('dictionary.suggestions'));
        let debounceId = null;

        input.addEventListener('input', function () {
            clearTimeout(debounceId);
            debounceId = setTimeout(search, 300);
        });

        function search() {
            const query = input.value.trim();

            if (query === '') {
                results.innerHTML = '';
                message.textContent = '';
                return;
            }

            const url = new URL(suggestionsUrl, window.location.origin);
            url.searchParams.set('q', query);

            fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    message.textContent = data.message || '';
                    results.innerHTML = '';

                    (data.words || []).forEach(function (word) {
                        const item = document.createElement('li');
                        item.textContent = word;
                        results.appendChild(item);
                    });
                })
                .catch(function () {
                    results.innerHTML = '';
                    message.textContent = '単語を取得できませんでした';
                });
        }
    </script>
</body>
</html>

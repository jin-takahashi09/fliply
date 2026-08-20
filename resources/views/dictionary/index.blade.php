<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    <div id="dictionary-sentinel" style="height: 1px;"></div>

    <div id="dictionary-detail"></div>

    <script>
        const input = document.getElementById('dictionary-query');
        const results = document.getElementById('dictionary-results');
        const message = document.getElementById('dictionary-message');
        const detail = document.getElementById('dictionary-detail');
        const sentinel = document.getElementById('dictionary-sentinel');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const suggestionsUrl = @json(route('dictionary.suggestions'));
        const meaningsUrl = @json(route('dictionary.meanings'));
        const storeUrl = @json(route('dictionary.words.store'));
        const destroyUrl = @json(route('dictionary.words.destroy'));

        let debounceId = null;
        let currentQuery = '';
        let currentOffset = 0;
        let isLoading = false;
        let hasMore = false;
        // Track the fetch generation so stale responses are discarded
        let fetchGen = 0;

        const observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && hasMore && !isLoading) {
                loadPage(currentQuery, currentOffset);
            }
        }, { rootMargin: '200px' });

        observer.observe(sentinel);

        input.addEventListener('input', function () {
            clearTimeout(debounceId);
            debounceId = setTimeout(function () {
                const q = input.value.trim();
                startSearch(q);
            }, 300);
        });

        function startSearch(q) {
            currentQuery = q;
            currentOffset = 0;
            hasMore = false;
            fetchGen++;
            results.innerHTML = '';
            detail.innerHTML = '';

            if (q === '') {
                message.textContent = '';
                return;
            }

            // 無効入力チェック（空欄以外は統一メッセージ）
            // - 使える文字: A-Z / a-z / '
            // - ただし英字を 1 文字以上含む必要がある（' だけは無効）
            if (!/^[a-zA-Z']+$/.test(q) || !/[a-zA-Z]/.test(q)) {
                message.textContent = "英字とアポストロフィ（'）のみ入力できます。";
                return;
            }

            message.textContent = '';
            loadPage(q, 0);
        }

        function loadPage(q, offset) {
            if (isLoading) return;
            isLoading = true;

            const gen = fetchGen;
            const url = new URL(suggestionsUrl, window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('offset', offset);

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (gen !== fetchGen) return; // stale response

                    message.textContent = data.message || '';

                    (data.words || []).forEach(function (word) {
                        const item = document.createElement('li');
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = word;
                        button.addEventListener('click', function () {
                            loadMeanings(word);
                        });
                        item.appendChild(button);
                        results.appendChild(item);
                    });

                    currentOffset = offset + (data.words || []).length;
                    hasMore = data.has_more || false;
                    isLoading = false;
                })
                .catch(function () {
                    if (gen !== fetchGen) return;
                    message.textContent = '単語を取得できませんでした';
                    isLoading = false;
                });
        }

        function loadMeanings(word) {
            const url = new URL(meaningsUrl, window.location.origin);
            url.searchParams.set('word', word);

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(renderDetail)
                .catch(function () {
                    renderDetail({
                        english: word,
                        candidates: [],
                        message: '意味を取得できませんでした'
                    });
                });
        }

        function renderDetail(data) {
            detail.innerHTML = '';

            if (data.message) {
                const error = document.createElement('p');
                error.textContent = data.message;
                detail.appendChild(error);
                return;
            }

            const candidates = data.candidates || [];

            if (candidates.length === 0) {
                const noMeaning = document.createElement('p');
                noMeaning.textContent = '意味を取得できませんでした';
                detail.appendChild(noMeaning);
                return;
            }

            // Meaning-group -> candidate list
            // No meaning-selection UI. User picks which candidate (english+japanese pair) to register.
            candidates.forEach(function (cand) {
                const candLabel = document.createElement('p');
                candLabel.textContent = (data.english || '') + '（' + (cand.japanese || '') + '）';
                detail.appendChild(candLabel);

                if (cand.registered) {
                    const status = document.createElement('p');
                    status.textContent = '登録済み';
                    detail.appendChild(status);

                    const unregister = document.createElement('button');
                    unregister.type = 'button';
                    unregister.textContent = '登録を解除';
                    unregister.addEventListener('click', function () {
                        unregisterWord(data.english, cand.japanese);
                    });
                    detail.appendChild(unregister);
                    return;
                }

                const add = document.createElement('button');
                add.type = 'button';
                add.textContent = '追加';
                add.addEventListener('click', function () {
                    saveWord(data.english, cand.japanese, false);
                });
                detail.appendChild(add);

                const addHard = document.createElement('button');
                addHard.type = 'button';
                addHard.textContent = '難しいに追加';
                addHard.addEventListener('click', function () {
                    saveWord(data.english, cand.japanese, true);
                });
                detail.appendChild(addHard);
            });
        }

        function saveWord(english, japanese, isHard) {
            fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ english: english, japanese: japanese, is_hard: isHard })
            })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok) {
                        loadMeanings(english);
                        return;
                    }
                    const error = document.createElement('p');
                    error.textContent = result.data.message || '登録できませんでした';
                    detail.appendChild(error);
                })
                .catch(function () {
                    const error = document.createElement('p');
                    error.textContent = '登録できませんでした';
                    detail.appendChild(error);
                });
        }

        function unregisterWord(english, japanese) {
            fetch(destroyUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ english: english, japanese: japanese })
            })
                .then(function (r) { return r.json(); })
                .then(function () { loadMeanings(english); })
                .catch(function () {
                    const error = document.createElement('p');
                    error.textContent = '登録を解除できませんでした';
                    detail.appendChild(error);
                });
        }
    </script>
</body>
</html>

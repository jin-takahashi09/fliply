@extends('layouts.app')

@section('title', '単語を追加 - Fliply')

@section('content')
<div class="page-back reveal">
    <a href="{{ route('words.index') }}">
        <x-icon name="arrow-left" :size="17" />
        単語帳へ戻る
    </a>
</div>

<section class="form-heading reveal reveal--delay-1">
    <p class="eyebrow">ADD A WORD</p>

    <h1>新しい言葉を探す。</h1>

    <p>
        英単語を入力すると、辞書から候補と日本語の意味を検索できます。
    </p>
</section>


<section
    class="form-card dictionary-card reveal reveal--delay-2"
    data-dictionary
    data-suggestions-url="{{ route('dictionary.suggestions') }}"
    data-meanings-url="{{ route('dictionary.meanings') }}"
    data-store-url="{{ route('dictionary.words.store') }}"
    data-destroy-url="{{ route('dictionary.words.destroy') }}">

    <div class="dictionary-layout">
        <div class="dictionary-layout__primary">
            <div class="field-group">
                <label for="dictionary-query">
                    <span>英単語を検索</span>
                    <small>SEARCH</small>
                </label>

                <div class="field-control">
                    <x-icon name="search" :size="18" />

                    <input
                        type="search"
                        id="dictionary-query"
                        placeholder="例：apple"
                        autocomplete="off"
                        spellcheck="false"
                        autofocus>
                </div>
            </div>

            <p
                id="dictionary-message"
                class="dictionary-message"
                aria-live="polite"></p>
        </div>

        <div class="dictionary-layout__detail dictionary-area dictionary-area--detail">
            <div class="dictionary-section-head">
                <span>意味</span>
                <small>MEANING</small>
            </div>

            <div
                id="dictionary-detail"
                class="dictionary-detail"
                aria-live="polite">
                <p class="dictionary-placeholder">
                    候補を選ぶと、ここに意味が表示されます。
                </p>
            </div>
        </div>

        <div class="dictionary-layout__suggestions dictionary-area dictionary-area--suggestions">
            <div class="dictionary-section-head">
                <span>候補</span>
                <small>SUGGESTIONS</small>
            </div>

            <div class="dictionary-results-scroll">
                <ul
                    id="dictionary-results"
                    class="dictionary-results"
                    aria-label="検索候補"></ul>

                <div
                    id="dictionary-sentinel"
                    class="dictionary-sentinel"
                    aria-hidden="true"></div>
            </div>
        </div>
    </div>
    </section>

    <script>
        const input = document.getElementById('dictionary-query');
        const results = document.getElementById('dictionary-results');
        const message = document.getElementById('dictionary-message');
        const detail = document.getElementById('dictionary-detail');
        const sentinel = document.getElementById('dictionary-sentinel');
        const resultsScroll = document.querySelector('.dictionary-results-scroll');

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute('content');

        const dictionaryRoot = document.querySelector('[data-dictionary]');

        const suggestionsUrl = dictionaryRoot.dataset.suggestionsUrl;
        const meaningsUrl = dictionaryRoot.dataset.meaningsUrl;
        const storeUrl = dictionaryRoot.dataset.storeUrl;
        const destroyUrl = dictionaryRoot.dataset.destroyUrl;

        let debounceId = null;
        let currentQuery = '';
        let currentOffset = 0;
        let isLoading = false;
        let hasMore = false;

        // 古い検索結果を表示しないための番号
        let fetchGen = 0;
        let meaningsGen = 0;
        let meaningsAbort = null;

        const observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting && hasMore && !isLoading) {
                loadPage(currentQuery, currentOffset);
            }
        }, {
            root: resultsScroll,
            rootMargin: '150px'
        });

        observer.observe(sentinel);

        input.addEventListener('input', function() {
            clearTimeout(debounceId);

            debounceId = setTimeout(function() {
                const q = input.value.trim();
                startSearch(q);
            }, 300);
        });

        function showPlaceholder() {
            detail.innerHTML = '';

            const placeholder = document.createElement('p');
            placeholder.className = 'dictionary-placeholder';
            placeholder.textContent = '候補を選ぶと、ここに意味が表示されます。';

            detail.appendChild(placeholder);
        }

        function startSearch(q) {
            currentQuery = q;
            currentOffset = 0;
            hasMore = false;
            isLoading = false;
            fetchGen++;

            results.innerHTML = '';
            message.textContent = '';

            showPlaceholder();

            if (q === '') {
                return;
            }

            // 英字とアポストロフィのみ許可
            if (!/^[a-zA-Z']+$/.test(q) || !/[a-zA-Z]/.test(q)) {
                message.textContent = "英字とアポストロフィ（'）のみ入力できます。";
                return;
            }

            loadPage(q, 0);
        }

        function loadPage(q, offset) {
            if (isLoading) {
                return;
            }

            isLoading = true;

            const gen = fetchGen;

            if (offset === 0) {
                message.textContent = '検索中...';
            }

            const url = new URL(suggestionsUrl, window.location.origin);

            url.searchParams.set('q', q);
            url.searchParams.set('offset', offset);

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (gen !== fetchGen) {
                        return;
                    }

                    message.textContent = data.message || '';

                    const words = data.words || [];

                    words.forEach(function(word) {
                        const item = document.createElement('li');
                        item.className = 'dictionary-result-item';

                        const button = document.createElement('button');

                        button.type = 'button';
                        button.className = 'dictionary-result-button';
                        button.textContent = word;

                        button.addEventListener('click', function() {
                            results
                                .querySelectorAll('.dictionary-result-button')
                                .forEach(function(resultButton) {
                                    resultButton.classList.remove('is-selected');
                                });

                            button.classList.add('is-selected');

                            loadMeanings(word);
                        });

                        item.appendChild(button);
                        results.appendChild(item);
                    });

                    currentOffset = offset + words.length;
                    hasMore = data.has_more || false;

                    if (
                        offset === 0 &&
                        words.length === 0 &&
                        !data.message
                    ) {
                        message.textContent = '候補が見つかりませんでした。';
                    }
                })
                .catch(function() {
                    if (gen !== fetchGen) {
                        return;
                    }

                    message.textContent = '単語を取得できませんでした';
                    hasMore = false;
                })
                .finally(function() {
                    if (gen === fetchGen) {
                        isLoading = false;
                    }
                });
        }

        function loadMeanings(word) {
            if (meaningsAbort) {
                meaningsAbort.abort();
            }

            meaningsAbort = new AbortController();
            const gen = ++meaningsGen;

            detail.innerHTML = '';
            detail.setAttribute('aria-busy', 'true');

            const loading = document.createElement('p');
            loading.className = 'dictionary-placeholder dictionary-placeholder--loading';
            loading.textContent = '意味を取得しています...';
            detail.appendChild(loading);

            const url = new URL(meaningsUrl, window.location.origin);

            url.searchParams.set('word', word);

            fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: meaningsAbort.signal
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (gen !== meaningsGen) {
                        return;
                    }

                    detail.removeAttribute('aria-busy');
                    renderDetail(data);
                })
                .catch(function(error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    if (gen !== meaningsGen) {
                        return;
                    }

                    detail.removeAttribute('aria-busy');
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

                error.className =
                    'dictionary-detail-message dictionary-detail-message--error';

                error.textContent = data.message;

                detail.appendChild(error);

                return;
            }

            const candidates = data.candidates || [];

            if (candidates.length === 0) {
                const noMeaning = document.createElement('p');

                noMeaning.className =
                    'dictionary-detail-message dictionary-detail-message--error';

                noMeaning.textContent = '意味を取得できませんでした';

                detail.appendChild(noMeaning);

                return;
            }

            candidates.forEach(function(cand) {
                const candidate = document.createElement('div');

                candidate.className = 'dictionary-candidate';

                const candLabel = document.createElement('p');

                candLabel.className = 'dictionary-candidate-label';

                candLabel.textContent = (data.english || '') + '（' + (cand.japanese || '') + '）';

                candidate.appendChild(candLabel);

                if (cand.registered) {
                    const status = document.createElement('p');

                    status.className = 'dictionary-registered';
                    status.textContent = '登録済み';

                    candidate.appendChild(status);

                    const actions = document.createElement('div');

                    actions.className = 'dictionary-candidate-actions';

                    const unregister = document.createElement('button');

                    unregister.type = 'button';
                    unregister.className =
                        'dictionary-action dictionary-action--remove';

                    unregister.textContent = '登録を解除';

                    unregister.addEventListener('click', function() {
                        unregisterWord(data.english, cand.japanese);
                    });

                    actions.appendChild(unregister);
                    candidate.appendChild(actions);
                    detail.appendChild(candidate);

                    return;
                }

                const actions = document.createElement('div');

                actions.className = 'dictionary-candidate-actions';

                const add = document.createElement('button');

                add.type = 'button';
                add.className =
                    'dictionary-action dictionary-action--add';

                add.textContent = '追加';

                add.addEventListener('click', function() {
                    saveWord(data.english, cand.japanese, false);
                });

                actions.appendChild(add);

                const addHard = document.createElement('button');

                addHard.type = 'button';
                addHard.className =
                    'dictionary-action dictionary-action--hard';

                addHard.textContent = '難しいに追加';

                addHard.addEventListener('click', function() {
                    saveWord(data.english, cand.japanese, true);
                });

                actions.appendChild(addHard);

                candidate.appendChild(actions);
                detail.appendChild(candidate);
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

                    body: JSON.stringify({
                        english: english,
                        japanese: japanese,
                        is_hard: isHard
                    })
                })
                .then(function(r) {
                    return r.json().then(function(data) {
                        return {
                            ok: r.ok,
                            data: data
                        };
                    });
                })
                .then(function(result) {
                    if (result.ok) {
                        loadMeanings(english);
                        return;
                    }

                    const error = document.createElement('p');

                    error.className =
                        'dictionary-detail-message dictionary-detail-message--error';

                    error.textContent =
                        result.data.message || '登録できませんでした';

                    detail.appendChild(error);
                })
                .catch(function() {
                    const error = document.createElement('p');

                    error.className =
                        'dictionary-detail-message dictionary-detail-message--error';

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

                    body: JSON.stringify({
                        english: english,
                        japanese: japanese
                    })
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function() {
                    loadMeanings(english);
                })
                .catch(function() {
                    const error = document.createElement('p');

                    error.className =
                        'dictionary-detail-message dictionary-detail-message--error';

                    error.textContent = '登録を解除できませんでした';

                    detail.appendChild(error);
                });
        }
    </script>
    @endsection
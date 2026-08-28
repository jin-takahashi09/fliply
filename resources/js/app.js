import '../css/app.css';
/*
|--------------------------------------------------------------------------
| Delete confirm
|--------------------------------------------------------------------------
*/
document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirm || '削除しますか？';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
/*
|--------------------------------------------------------------------------
| Search shortcut
|--------------------------------------------------------------------------
*/
document.addEventListener('keydown', (event) => {
    const searchInput = document.querySelector('[data-search-input]');
    if (
        (event.metaKey || event.ctrlKey) &&
        event.key.toLowerCase() === 'k' &&
        searchInput
    ) {
        event.preventDefault();
        searchInput.focus();
    }
});
/*
|--------------------------------------------------------------------------
| Word live search
|--------------------------------------------------------------------------
*/
const wordSearchInput = document.querySelector('[data-search-input]');
const wordRows = document.querySelectorAll('.word-row');
if (wordSearchInput && wordRows.length > 0) {
    wordSearchInput.addEventListener('input', () => {
        const keyword = wordSearchInput.value
            .trim()
            .toLowerCase();
        wordRows.forEach((row) => {
            const english = row
                .querySelector('.word-copy h2')
                ?.textContent
                .toLowerCase() || '';
            const japanese = row
                .querySelector('.word-copy p')
                ?.textContent
                .toLowerCase() || '';
            const matches =
                english.startsWith(keyword) ||
                japanese.startsWith(keyword);
            row.style.display = matches ? '' : 'none';
        });
    });
}
/*
|--------------------------------------------------------------------------
| Toast
|--------------------------------------------------------------------------
*/
document.querySelectorAll('[data-auto-dismiss]').forEach((toast) => {
    window.setTimeout(() => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => {
            toast.remove();
        }, 230);
    }, 3200);
});
/*
|--------------------------------------------------------------------------
| Word hard toggle (no reload)
|--------------------------------------------------------------------------
*/
const wordsPageRoot = document.querySelector('[data-words-filter]');
const wordsListFilter = wordsPageRoot?.dataset.wordsFilter ?? '';
const wordsCountEl = document.querySelector('[data-words-count]');

function decrementWordsCount() {
    if (!wordsCountEl) {
        return;
    }

    const current = Number.parseInt(wordsCountEl.textContent || '0', 10);

    wordsCountEl.textContent = String(Math.max(0, current - 1));
}

function applyHardToggleUi(row, button, isHard) {
    row?.classList.toggle('is-hard', isHard);
    button.classList.toggle('is-active', isHard);
    button.setAttribute(
        'aria-label',
        isHard ? '難しいから外す' : '難しい単語にする',
    );
}

function removeWordRow(row) {
    if (!row) {
        return;
    }

    row.style.transition = 'opacity 160ms ease';
    row.style.opacity = '0';

    window.setTimeout(() => {
        row.remove();
    }, 160);
}

document.querySelectorAll('[data-toggle-hard-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const row = form.closest('.word-row');
        const csrfInput = form.querySelector('input[name="_token"]');

        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;

        fetch(form.action, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfInput?.value ?? '',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('toggle failed');
                }

                return response.json();
            })
            .then((data) => {
                const isHard = Boolean(data.is_hard);

                if (
                    (wordsListFilter === 'hard' && !isHard) ||
                    (wordsListFilter === 'normal' && isHard)
                ) {
                    removeWordRow(row);
                    decrementWordsCount();
                    return;
                }

                applyHardToggleUi(row, button, isHard);
            })
            .catch(() => {
                window.alert('難しいの切り替えに失敗しました');
            })
            .finally(() => {
                button.disabled = false;
            });
    });
});

/*
|--------------------------------------------------------------------------
| Study session — stacked page deck
|--------------------------------------------------------------------------
*/
const studyRoot = document.querySelector('[data-study]');
if (studyRoot) {
    const dataElement = document.querySelector('#study-words');
    const words = JSON.parse(dataElement?.textContent || '[]');
    const direction = studyRoot.dataset.direction || 'en-ja';
    const deck = studyRoot.querySelector('[data-study-deck]');
    const studyStack = studyRoot.querySelector('[data-study-stack]');
    const stackLayers = studyRoot.querySelectorAll('[data-stack-layer]');
    const cardNumber = studyRoot.querySelector('[data-card-number]');
    const actions = studyRoot.querySelector('[data-study-actions]');
    const progressLabel = document.querySelector('[data-progress-label]');
    const progressBar = studyRoot.querySelector('[data-progress-bar]');
    const completeScreen = document.querySelector('[data-complete]');
    const correctCountElement =
        completeScreen?.querySelector('[data-correct-count]');
    const incorrectCountElement =
        completeScreen?.querySelector('[data-incorrect-count]');
    const completeSummaryElement =
        completeScreen?.querySelector('[data-complete-summary]');
    const perfectMessageElement =
        completeScreen?.querySelector('[data-perfect-message]');
    const incorrectListElement =
        completeScreen?.querySelector('[data-incorrect-list]');
    const incorrectSectionElement =
        completeScreen?.querySelector('[data-incorrect-section]');
    const incorrectButton =
        actions?.querySelector('[data-answer="incorrect"]');
    const correctButton =
        actions?.querySelector('[data-answer="correct"]');

    const PAGE_TURN_MS = 700;
    const ANSWER_ACTIONS_REVEAL_MS = Math.round(PAGE_TURN_MS * 0.72);

    let pageElements = [];
    let correctCount = 0;
    let incorrectCount = 0;
    let incorrectWords = [];
    let isAnimating = false;
    let answerActionsRevealed = false;
    let pendingAdvanceAfterPeel = false;
    let revealActionsTimerId = null;

    const wordSides = (word) =>
        direction === 'en-ja'
            ? { question: word.english, answer: word.japanese }
            : { question: word.japanese, answer: word.english };

    const questionFor = (word) => wordSides(word).question;

    const answerFor = (word) => wordSides(word).answer;

    const getTopPage = () => pageElements[0] ?? null;

    const isAnswerPageVisible = () =>
        getTopPage()?.dataset.pageType === 'answer';

    const currentWordIndex = () => {
        const top = getTopPage();
        return top ? Number(top.dataset.wordIndex) : 0;
    };

    const setActionButtonsEnabled = (enabled) => {
        [incorrectButton, correctButton].forEach((button) => {
            if (button) {
                button.disabled = !enabled;
            }
        });
    };

    const shouldShowAnswerActions = () =>
        answerActionsRevealed ||
        (isAnswerPageVisible() && !isAnimating);

    const clearRevealActionsTimer = () => {
        if (revealActionsTimerId !== null) {
            window.clearTimeout(revealActionsTimerId);
            revealActionsTimerId = null;
        }
    };

    const hideAnswerActions = () => {
        answerActionsRevealed = false;
        clearRevealActionsTimer();
    };

    const scheduleAnswerActionsReveal = () => {
        clearRevealActionsTimer();

        revealActionsTimerId = window.setTimeout(() => {
            revealActionsTimerId = null;
            answerActionsRevealed = true;
            updateStudyChrome();
        }, ANSWER_ACTIONS_REVEAL_MS);
    };

    const updateStudyChrome = () => {
        const top = getTopPage();

        if (!top) {
            actions.classList.remove('is-revealed');
            actions.setAttribute('aria-hidden', 'true');
            setActionButtonsEnabled(false);
            return;
        }

        if (shouldShowAnswerActions()) {
            actions.classList.add('is-revealed');
            actions.setAttribute('aria-hidden', 'false');
            setActionButtonsEnabled(true);
            setCardTapEnabled(false);
            return;
        }

        actions.classList.remove('is-revealed');
        actions.setAttribute('aria-hidden', 'true');
        setActionButtonsEnabled(false);

        if (top.dataset.pageType === 'question') {
            setCardTapEnabled(!isAnimating);
        } else {
            setCardTapEnabled(false);
        }
    };

    const setCardTapEnabled = (enabled) => {
        const top = getTopPage();
        if (!top || top.dataset.pageType !== 'question') {
            return;
        }

        if (enabled) {
            top.setAttribute('tabindex', '0');
            top.removeAttribute('aria-disabled');
        } else {
            top.setAttribute('tabindex', '-1');
            top.setAttribute('aria-disabled', 'true');
        }
    };

    const updateProgress = () => {
        const index = currentWordIndex();
        const word = words[index];
        if (!word) {
            return;
        }

        cardNumber.textContent =
            `CARD ${String(index + 1).padStart(2, '0')}`;
        progressLabel.textContent =
            `${index + 1} / ${words.length}`;
        progressBar.style.width =
            `${((index + 1) / words.length) * 100}%`;
    };

    const renderStackPreview = () => {
        const index = currentWordIndex();

        stackLayers.forEach((layer, offset) => {
            const word = words[index + 1 + offset];
            const textElement = layer.querySelector('[data-stack-text]');

            if (!word || !textElement) {
                layer.hidden = true;
                layer.setAttribute('aria-hidden', 'true');
                return;
            }

            layer.hidden = false;
            layer.setAttribute('aria-hidden', 'true');
            textElement.textContent = questionFor(word);
        });
    };

    const createPaperBack = () => {
        const back = document.createElement('div');
        back.className = 'study-page__sheet study-page__sheet--back';
        back.setAttribute('aria-hidden', 'true');

        const line = document.createElement('span');
        line.className = 'study-page__paper-line';
        line.setAttribute('aria-hidden', 'true');
        back.append(line);

        return back;
    };

    const createPageElement = (pageData, zIndex) => {
        const { type, word, wordIndex } = pageData;
        const isQuestion = type === 'question';
        const page = document.createElement('div');
        page.className = 'study-page';
        page.dataset.pageType = type;
        page.dataset.wordIndex = String(wordIndex);
        page.style.zIndex = String(zIndex);

        const front = document.createElement('div');
        front.className = 'study-page__sheet study-page__sheet--front';

        const card = document.createElement('article');
        card.className =
            `study-card ${isQuestion ? 'study-card--question' : 'study-card--answer'}`;

        const label = document.createElement('span');
        label.className = 'study-card__label';
        label.textContent = isQuestion ? 'QUESTION' : 'ANSWER';

        const text = document.createElement('strong');
        text.textContent = isQuestion
            ? questionFor(word)
            : answerFor(word);

        card.append(label, text);

        if (isQuestion) {
            const hint = document.createElement('span');
            hint.className = 'turn-hint';

            const gesture = document.createElement('span');
            gesture.className = 'turn-hint__gesture';
            gesture.textContent = '↗';

            hint.append(gesture, document.createTextNode(' タップしてめくる'));
            card.append(hint);

            page.setAttribute('role', 'button');
            page.setAttribute('tabindex', '0');
            page.setAttribute('aria-label', 'カードをめくって答えを見る');
        }

        front.append(card);
        page.append(front, createPaperBack());

        return page;
    };

    const markActivePage = () => {
        pageElements.forEach((page, index) => {
            page.classList.toggle('is-active-page', index === 0);
        });
    };

    const buildDeck = () => {
        if (!deck) {
            return;
        }

        deck.replaceChildren();
        pageElements = [];

        const allPages = [];
        words.forEach((word, wordIndex) => {
            allPages.push({ type: 'question', word, wordIndex });
            allPages.push({ type: 'answer', word, wordIndex });
        });

        const totalPages = allPages.length;

        allPages.forEach((pageData, index) => {
            const page = createPageElement(pageData, totalPages - index);
            deck.append(page);
            pageElements.push(page);
        });

        markActivePage();
    };

    const peelTopPage = (onComplete, options = {}) => {
        const { revealAnswerActions = false } = options;
        const top = getTopPage();
        if (!top || isAnimating) {
            return;
        }

        hideAnswerActions();
        isAnimating = true;
        updateStudyChrome();
        setCardTapEnabled(false);

        if (revealAnswerActions) {
            scheduleAnswerActionsReveal();
        }

        studyStack?.classList.add('is-advancing-stack');
        top.classList.add('is-turning');

        window.setTimeout(() => {
            top.remove();
            pageElements.shift();
            markActivePage();
            studyStack?.classList.remove('is-advancing-stack');
            hideAnswerActions();

            onComplete?.();

            if (pendingAdvanceAfterPeel) {
                pendingAdvanceAfterPeel = false;
                isAnimating = false;

                peelTopPage(() => {
                    if (pageElements.length === 0) {
                        finishStudy();
                        return;
                    }

                    syncChromeToTopPage();
                });

                return;
            }

            isAnimating = false;
            updateStudyChrome();
        }, PAGE_TURN_MS);
    };

    const renderIncorrectList = () => {
        if (!incorrectListElement || !incorrectSectionElement) {
            return;
        }

        incorrectListElement.replaceChildren();

        if (incorrectWords.length === 0) {
            incorrectSectionElement.hidden = true;

            if (perfectMessageElement) {
                perfectMessageElement.hidden = false;
            }

            return;
        }

        incorrectSectionElement.hidden = false;

        if (perfectMessageElement) {
            perfectMessageElement.hidden = true;
        }

        incorrectWords.forEach((word) => {
            const item = document.createElement('li');
            const question = document.createElement('strong');
            const answer = document.createElement('span');

            question.textContent = questionFor(word);
            answer.textContent = answerFor(word);
            item.append(question, answer);
            incorrectListElement.append(item);
        });
    };

    const finishStudy = () => {
        studyRoot.hidden = true;

        if (completeScreen) {
            completeScreen.hidden = false;
        }

        if (correctCountElement) {
            correctCountElement.textContent = String(correctCount);
        }

        if (incorrectCountElement) {
            incorrectCountElement.textContent = String(incorrectCount);
        }

        if (completeSummaryElement) {
            completeSummaryElement.textContent =
                `${words.length}問中 ${correctCount}問正解`;
        }

        renderIncorrectList();

        if (progressLabel) {
            progressLabel.textContent = '完了';
        }
    };

    const syncChromeToTopPage = () => {
        if (pageElements.length === 0) {
            return;
        }

        if (!isAnswerPageVisible()) {
            updateProgress();
        }

        renderStackPreview();
        updateStudyChrome();
    };

    const revealAnswer = () => {
        const top = getTopPage();
        if (
            isAnimating ||
            !top ||
            top.dataset.pageType !== 'question'
        ) {
            return;
        }

        peelTopPage(() => {
            if (pageElements.length === 0) {
                finishStudy();
                return;
            }

            renderStackPreview();
        }, { revealAnswerActions: true });
    };

    const answerCard = (isCorrect) => {
        if (!shouldShowAnswerActions()) {
            return;
        }

        hideAnswerActions();
        updateStudyChrome();

        if (isCorrect) {
            correctCount += 1;
        } else {
            incorrectCount += 1;
            incorrectWords.push(words[currentWordIndex()]);
        }

        if (isAnimating) {
            pendingAdvanceAfterPeel = true;
            return;
        }

        peelTopPage(() => {
            if (pageElements.length === 0) {
                finishStudy();
                return;
            }

            syncChromeToTopPage();
        });
    };

    deck?.addEventListener('click', (event) => {
        const page = event.target.closest('.study-page');
        if (page !== getTopPage()) {
            return;
        }

        revealAnswer();
    });

    deck?.addEventListener('keydown', (event) => {
        const page = event.target.closest('.study-page');
        if (page !== getTopPage()) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            revealAnswer();
        }
    });

    incorrectButton?.addEventListener('click', () => answerCard(false));
    correctButton?.addEventListener('click', () => answerCard(true));

    buildDeck();
    syncChromeToTopPage();
}

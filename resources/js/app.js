import '../css/app.css';

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirm || '削除しますか？';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.addEventListener('keydown', (event) => {
    const searchInput = document.querySelector('[data-search-input]');

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' && searchInput) {
        event.preventDefault();
        searchInput.focus();
    }
});

document.querySelectorAll('[data-auto-dismiss]').forEach((toast) => {
    window.setTimeout(() => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 230);
    }, 3200);
});

const studyRoot = document.querySelector('[data-study]');

if (studyRoot) {
    const dataElement = document.querySelector('#study-words');
    const words = JSON.parse(dataElement?.textContent || '[]');
    const direction = studyRoot.dataset.direction || 'en-ja';
    const card = studyRoot.querySelector('[data-card]');
    const cardWrap = studyRoot.querySelector('[data-card-wrap]');
    const questionText = studyRoot.querySelector('[data-question-text]');
    const answerText = studyRoot.querySelector('[data-answer-text]');
    const cardNumber = studyRoot.querySelector('[data-card-number]');
    const actions = studyRoot.querySelector('[data-study-actions]');
    const tip = studyRoot.querySelector('[data-study-tip]');
    const progressLabel = document.querySelector('[data-progress-label]');
    const progressBar = studyRoot.querySelector('[data-progress-bar]');
    const completeScreen = document.querySelector('[data-complete]');
    const hardCountElement = completeScreen?.querySelector('[data-hard-count]');

    let currentIndex = 0;
    let difficultCount = 0;
    let isTurned = false;
    let isSaving = false;

    const currentWord = () => words[currentIndex];

    const renderCard = () => {
        const word = currentWord();
        const question = direction === 'en-ja' ? word.english : word.japanese;
        const answer = direction === 'en-ja' ? word.japanese : word.english;

        questionText.textContent = question;
        answerText.textContent = answer;
        cardNumber.textContent = `CARD ${String(currentIndex + 1).padStart(2, '0')}`;
        progressLabel.textContent = `${currentIndex + 1} / ${words.length}`;
        progressBar.style.width = `${((currentIndex + 1) / words.length) * 100}%`;

        card.classList.remove('is-turned');
        actions.classList.remove('is-revealed');
        actions.setAttribute('aria-hidden', 'true');
        tip.classList.remove('is-hidden');
        tip.textContent = 'カードの右側をめくるようにタップ';
        isTurned = false;
    };

    const turnCard = () => {
        if (isTurned || isSaving) {
            return;
        }

        isTurned = true;
        card.classList.add('is-turned');
        actions.classList.add('is-revealed');
        actions.setAttribute('aria-hidden', 'false');
        tip.classList.add('is-hidden');
    };

    const finishStudy = () => {
        studyRoot.hidden = true;
        completeScreen.hidden = false;
        hardCountElement.textContent = difficultCount;
        progressLabel.textContent = '完了';
    };

const answerCard = (isHard) => {
    if (!isTurned || isSaving) {
        return;
    }

    isSaving = true;

    actions.querySelectorAll('button').forEach((button) => {
        button.disabled = true;
    });

    if (isHard) {
        difficultCount += 1;
    }

    cardWrap.classList.add('is-advancing');

    window.setTimeout(() => {
        currentIndex += 1;

        if (currentIndex >= words.length) {
            finishStudy();
            return;
        }

        renderCard();
        cardWrap.classList.remove('is-advancing');

        actions.querySelectorAll('button').forEach((button) => {
            button.disabled = false;
        });

        isSaving = false;
    }, 230);
};

    card.addEventListener('click', turnCard);
    card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            turnCard();
        }
    });

    actions.querySelector('[data-answer="hard"]').addEventListener('click', () => answerCard(true));
    actions.querySelector('[data-answer="known"]').addEventListener('click', () => answerCard(false));

    renderCard();
}

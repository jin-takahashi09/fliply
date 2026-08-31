import { getReadingHiragana } from './japanese-reading';

const JAPANESE_SPLIT_PATTERN = /[、,，/／\r\n]+/;

const KANJI_PATTERN = /[\u3400-\u4DBF\u4E00-\u9FFF]/;

const KATAKANA_START = 0x30a1;
const KATAKANA_END = 0x30f6;
const HIRAGANA_OFFSET = 0x3041 - 0x30a1;

export function splitJapaneseCandidates(japanese) {
    return String(japanese)
        .split(JAPANESE_SPLIT_PATTERN)
        .map((candidate) => candidate.trim())
        .filter((candidate) => candidate.length > 0);
}

export function containsKanji(value) {
    return KANJI_PATTERN.test(value);
}

export function normalizeJapaneseText(value) {
    return String(value).trim().normalize('NFKC');
}

export function toHiragana(value) {
    return [...normalizeJapaneseText(value)].map((char) => {
        const code = char.charCodeAt(0);

        if (code >= KATAKANA_START && code <= KATAKANA_END) {
            return String.fromCharCode(code + HIRAGANA_OFFSET);
        }

        return char;
    }).join('');
}

export function normalizeKana(value) {
    return toHiragana(value);
}

function isExactMatch(userAnswer, candidate) {
    return (
        normalizeJapaneseText(userAnswer) === normalizeJapaneseText(candidate)
    );
}

function isKanaMatch(userAnswer, candidate) {
    return normalizeKana(userAnswer) === normalizeKana(candidate);
}

async function isKanjiAndKanaMatch(userAnswer, candidate) {
    const user = normalizeJapaneseText(userAnswer);
    const expected = normalizeJapaneseText(candidate);
    const userHasKanji = containsKanji(user);
    const candidateHasKanji = containsKanji(expected);

    if (userHasKanji && candidateHasKanji) {
        return false;
    }

    if (userHasKanji === candidateHasKanji) {
        return false;
    }

    const kanjiSide = userHasKanji ? user : expected;
    const kanaSide = userHasKanji ? expected : user;

    try {
        const reading = await getReadingHiragana(kanjiSide);

        return normalizeKana(kanaSide) === reading;
    } catch {
        return false;
    }
}

export async function japaneseStringsMatch(userAnswer, candidate) {
    if (isExactMatch(userAnswer, candidate)) {
        return true;
    }

    if (isKanaMatch(userAnswer, candidate)) {
        return true;
    }

    return isKanjiAndKanaMatch(userAnswer, candidate);
}

export async function isJapaneseAnswerCorrect(userAnswer, japaneseField) {
    const candidates = splitJapaneseCandidates(japaneseField);

    if (candidates.length === 0) {
        return false;
    }

    for (const candidate of candidates) {
        if (isExactMatch(userAnswer, candidate)) {
            return true;
        }
    }

    for (const candidate of candidates) {
        if (isKanaMatch(userAnswer, candidate)) {
            return true;
        }
    }

    for (const candidate of candidates) {
        if (await isKanjiAndKanaMatch(userAnswer, candidate)) {
            return true;
        }
    }

    return false;
}

export function isEnglishAnswerCorrect(userAnswer, correctAnswer) {
    return (
        userAnswer.trim().toLowerCase() ===
        correctAnswer.trim().toLowerCase()
    );
}

export async function isStudyAnswerCorrect(userAnswer, correctAnswer, direction) {
    if (direction === 'ja-en') {
        return isEnglishAnswerCorrect(userAnswer, correctAnswer);
    }

    return isJapaneseAnswerCorrect(userAnswer, correctAnswer);
}

export { initJapaneseReading } from './japanese-reading';

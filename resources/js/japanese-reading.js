const KATAKANA_START = 0x30a1;
const KATAKANA_END = 0x30f6;
const HIRAGANA_OFFSET = 0x3041 - 0x30a1;

const KUROMOJI_DIC_PATH = '/kuromoji/dict/';

/** @type {Promise<import('kuromoji').Tokenizer> | null} */
let tokenizerPromise = null;

/** @type {boolean} */
let tokenizerReady = false;

/** @type {Map<string, string>} */
const readingCache = new Map();

function katakanaToHiragana(value) {
    return [...value].map((char) => {
        const code = char.charCodeAt(0);

        if (code >= KATAKANA_START && code <= KATAKANA_END) {
            return String.fromCharCode(code + HIRAGANA_OFFSET);
        }

        return char;
    }).join('');
}

export function isJapaneseReadingReady() {
    return tokenizerReady;
}

async function loadKuromoji() {
    const module = await import('kuromoji');

    return module.default ?? globalThis.kuromoji;
}

export function initJapaneseReading(dicPath = KUROMOJI_DIC_PATH) {
    if (!tokenizerPromise) {
        tokenizerPromise = loadKuromoji()
            .then((kuromoji) =>
                new Promise((resolve, reject) => {
                    kuromoji.builder({ dicPath }).build((error, tokenizer) => {
                        if (error) {
                            reject(error);
                            return;
                        }

                        resolve(tokenizer);
                    });
                }),
            )
            .then((tokenizer) => {
                tokenizerReady = true;

                return tokenizer;
            })
            .catch((error) => {
                tokenizerPromise = null;
                tokenizerReady = false;

                throw error;
            });
    }

    return tokenizerPromise;
}

/**
 * @param {import('kuromoji').Tokenizer} tokenizer
 * @param {string} text
 * @returns {string}
 */
function tokenizeToHiraganaReading(tokenizer, text) {
    const tokens = tokenizer.tokenize(text);

    return katakanaToHiragana(
        tokens.map((token) => token.reading ?? token.surface_form).join(''),
    );
}

/**
 * @param {string} text
 * @returns {Promise<string>}
 */
export async function getReadingHiragana(text) {
    const trimmed = text.trim();

    if (readingCache.has(trimmed)) {
        return readingCache.get(trimmed);
    }

    const tokenizer = await initJapaneseReading();
    const reading = tokenizeToHiraganaReading(tokenizer, trimmed);

    readingCache.set(trimmed, reading);

    return reading;
}

import { computed, unref } from "vue";
import { trans } from "laravel-vue-i18n";

const MAX_SCORE = 5;

/** Il tetto che uno schema prevedibile impone: qualunque sia la varieta', resta «debole». */
const PREDICTABLE_MAX_SCORE = 2;

/** Punti per lunghezza, **cumulativi**: 16 caratteri prendono 1 + 2 + 3. */
const LENGTH_POINTS = [
    { from: 8, points: 1 },
    { from: 12, points: 2 },
    { from: 16, points: 3 },
];

/** Le famiglie di caratteri che contano per la varieta'. */
const CHARACTER_FAMILIES = [/[A-Z]/, /[a-z]/, /[0-9]/, /[^A-Za-z0-9]/];

/** Punti per varieta', **non** cumulativi: tre famiglie valgono 1, quattro valgono 2. */
const VARIETY_POINTS = { 3: 1, 4: 2 };

/** Gli schemi che rendono una password indovinabile a prescindere da lunghezza e varieta'. */
const PREDICTABLE_PATTERNS = [
    // Tre o piu' caratteri identici consecutivi (AAA, 111, !!!)
    /(.)\1{2,}/,
    // Sequenze da tastiera, numeriche o parole ovvie (qwerty, 1234, admin...)
    /(1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|qwer|wert|erty|asdf|sdfg|dfgh|zxcv|xcvb|pass|admin|login)/i,
    // Sequenze alfabetiche ovvie (abcd, bcde...)
    /(abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i,
    // Blocchi ripetuti (abcabcabc, 121212)
    /(.{2,})\1{2,}/,
];

/** Sopra questa quota, un solo carattere ripetuto rende la password monotona. */
const MONOTONY_THRESHOLD = 0.3;

/** La monotonia si valuta solo da questa lunghezza in su: sotto, la percentuale non dice niente. */
const MONOTONY_MIN_LENGTH = 8;

function lengthPoints(password) {
    return LENGTH_POINTS.reduce((total, { from, points }) => (password.length >= from ? total + points : total), 0);
}

function varietyPoints(password) {
    const families = CHARACTER_FAMILIES.filter((family) => family.test(password)).length;

    return VARIETY_POINTS[families] ?? 0;
}

function isMonotonous(password) {
    if (password.length < MONOTONY_MIN_LENGTH) {
        return false;
    }

    const counts = {};
    let highest = 0;

    for (const character of password) {
        counts[character] = (counts[character] || 0) + 1;
        highest = Math.max(highest, counts[character]);
    }

    return highest / password.length > MONOTONY_THRESHOLD;
}

function isPredictable(password) {
    return PREDICTABLE_PATTERNS.some((pattern) => pattern.test(password)) || isMonotonous(password);
}

export function passwordStrength(password) {
    if (!password) {
        return 0;
    }

    const earned = Math.min(MAX_SCORE, lengthPoints(password) + varietyPoints(password));
    const ceiling = isPredictable(password) ? PREDICTABLE_MAX_SCORE : MAX_SCORE;

    return Math.max(1, Math.min(earned, ceiling));
}

export function usePassword(passwordRef, confirmPasswordRef, currentPasswordRef = null) {
    const getPwd = () => unref(passwordRef) || "";
    const getConfirm = () => unref(confirmPasswordRef) || "";
    const getCurrent = () => unref(currentPasswordRef) || "";

    const requirements = computed(() => ({
        minLength: getPwd().length >= 12,
        hasUpperCase: /[A-Z]/.test(getPwd()),
        hasLowerCase: /[a-z]/.test(getPwd()),
        hasNumber: /[0-9]/.test(getPwd()),
        hasSpecialChar: /[!@#$%^&*(),.?":{}|<>\-_]/.test(getPwd()),
        passwordsMatch: !!getPwd() && getPwd() === getConfirm(),
        differentFromCurrent: !currentPasswordRef ? true : getPwd().length > 0 && getPwd() !== getCurrent(),
    }));

    const strength = computed(() => passwordStrength(getPwd()));

    // Colori e Testi
    const strengthColorClass = computed(() => {
        if (strength.value <= 2) return "bg-red-500";
        if (strength.value === 3) return "bg-orange-500";
        if (strength.value === 4) return "bg-blue-500";
        return "bg-green-500";
    });

    const strengthTextColorClass = computed(() => {
        if (strength.value <= 2) return "text-red-500";
        if (strength.value === 3) return "text-orange-500";
        if (strength.value === 4) return "text-blue-500";
        return "text-green-500";
    });

    const strengthText = computed(() => {
        if (strength.value === 0) return "";
        if (strength.value <= 2) return trans("auth.strength_weak", "Debole");
        if (strength.value === 3) return trans("auth.strength_medium", "Media");
        if (strength.value === 4) return trans("auth.strength_good", "Buona");
        return trans("auth.strength_strong", "Forte");
    });

    // Validazione Globale
    const isValid = computed(() => {
        return (
            requirements.value.minLength &&
            requirements.value.hasUpperCase &&
            requirements.value.hasLowerCase &&
            requirements.value.hasNumber &&
            requirements.value.hasSpecialChar &&
            requirements.value.passwordsMatch &&
            requirements.value.differentFromCurrent
        );
    });

    const generatePassword = () => {
        const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lower = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const specials = "!@#$%^&*()_-+=<>?";
        const allChars = upper + lower + numbers + specials;

        let pwd = "";
        pwd += upper[Math.floor(Math.random() * upper.length)];
        pwd += lower[Math.floor(Math.random() * lower.length)];
        pwd += numbers[Math.floor(Math.random() * numbers.length)];
        pwd += specials[Math.floor(Math.random() * specials.length)];

        for (let i = 4; i < 14; i++) {
            pwd += allChars[Math.floor(Math.random() * allChars.length)];
        }

        return pwd
            .split("")
            .sort(() => 0.5 - Math.random())
            .join("");
    };

    return {
        requirements,
        strength,
        strengthColorClass,
        strengthTextColorClass,
        strengthText,
        isValid,
        generatePassword,
    };
}

import { computed, unref } from "vue";
import { trans } from "laravel-vue-i18n";

export function usePassword(passwordRef, confirmPasswordRef) {
    // Usiamo unref() per estrarre il valore, sia che tu passi un toRef, un getter, o una stringa
    const getPwd = () => unref(passwordRef) || "";
    const getConfirm = () => unref(confirmPasswordRef) || "";

    const requirements = computed(() => ({
        minLength: getPwd().length >= 12,
        hasUpperCase: /[A-Z]/.test(getPwd()),
        hasLowerCase: /[a-z]/.test(getPwd()),
        hasNumber: /[0-9]/.test(getPwd()),
        hasSpecialChar: /[!@#$%^&*(),.?":{}|<>\-_]/.test(getPwd()),
        passwordsMatch: !!getPwd() && getPwd() === getConfirm(),
    }));

    const strength = computed(() => {
        const pwd = getPwd();
        if (!pwd) return 0;

        let score = 0;

        // Punti per lunghezza
        if (pwd.length >= 8) score += 1;
        if (pwd.length >= 12) score += 2;
        if (pwd.length >= 16) score += 3;

        // Punti per varietà
        let variety = 0;
        if (/[A-Z]/.test(pwd)) variety++;
        if (/[a-z]/.test(pwd)) variety++;
        if (/[0-9]/.test(pwd)) variety++;
        if (/[^A-Za-z0-9]/.test(pwd)) variety++;

        if (variety === 3) score += 1;
        if (variety === 4) score += 2;

        // Punteggio massimo teorico di base
        score = Math.min(5, score);

        // Se si verifica una di queste condizioni, il punteggio MASSIMO non può superare 2 (Debole)
        let maxAllowedScore = 5;

        // Tre o più caratteri identici consecutivi (es. AAA, 111, !!!)
        if (/(.)\1{2,}/.test(pwd)) {
            maxAllowedScore = Math.min(maxAllowedScore, 2);
        }

        // Sequenze da tastiera, numeriche o parole ovvie (qwerty, 1234, admin...)
        const commonSequences =
            /(1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|qwer|wert|erty|asdf|sdfg|dfgh|zxcv|xcvb|pass|admin|login)/i;
        if (commonSequences.test(pwd)) {
            maxAllowedScore = Math.min(maxAllowedScore, 2);
        }

        // Sequenze alfabetiche ovvie (abcd, bcde...)
        const alphaSequences =
            /(abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i;
        if (alphaSequences.test(pwd)) {
            maxAllowedScore = Math.min(maxAllowedScore, 2);
        }

        // Pattern ripetuti a blocchi (es. abcabcabc, 121212)
        if (/(.{2,})\1{2,}/.test(pwd)) {
            maxAllowedScore = Math.min(maxAllowedScore, 2);
        }

        // Monotonia: se un singolo carattere compone più del 30% dell'intera password
        if (pwd.length >= 8) {
            const charCounts = {};
            let maxCharCount = 0;
            for (const char of pwd) {
                charCounts[char] = (charCounts[char] || 0) + 1;
                if (charCounts[char] > maxCharCount) {
                    maxCharCount = charCounts[char];
                }
            }
            if (maxCharCount / pwd.length > 0.3) {
                maxAllowedScore = Math.min(maxAllowedScore, 2);
            }
        }

        score = Math.min(score, maxAllowedScore);

        return Math.max(1, score);
    });

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
            requirements.value.passwordsMatch
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

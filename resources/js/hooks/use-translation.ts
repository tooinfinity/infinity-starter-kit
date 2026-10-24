import { usePage } from '@inertiajs/react';

type TranslationReplacements = Record<string, string | number>;

export type TranslationHelpers = {
    __: (key: string, replaces?: TranslationReplacements | string) => string;
    trans: (key: string, replaces?: TranslationReplacements | string) => string;
    transChoice: (
        key: string,
        count: number,
        replaces?: TranslationReplacements,
    ) => string;
    trans_choice: (
        key: string,
        count: number,
        replaces?: TranslationReplacements,
    ) => string;
};

function formatString(
    template: string,
    replaces?: TranslationReplacements | string,
): string {
    if (typeof replaces === 'string') {
        return `${template} ${replaces}`;
    }
    if (!replaces || typeof replaces !== 'object') {
        return template;
    }
    return template.replace(
        /(?::([a-zA-Z0-9_]+)|\{([a-zA-Z0-9_]+)\})/g,
        (match, p1, p2) => {
            const key = p1 || p2;
            return Object.prototype.hasOwnProperty.call(replaces, key)
                ? String(replaces[key])
                : match;
        },
    );
}

function choosePluralLine(line: string, count: number, locale: string): string {
    const parts = line.split('|');
    if (parts.length === 1) {
        return parts[0];
    }
    for (const part of parts) {
        const matchExact = part.match(/^\{(\d+)\}\s*(.*)$/);
        if (matchExact && Number(matchExact[1]) === count) {
            return matchExact[2];
        }
        const matchRange = part.match(/^\[(\*|\d+),(\*|\d+)\]\s*(.*)$/);
        if (matchRange) {
            const min =
                matchRange[1] === '*' ? -Infinity : Number(matchRange[1]);
            const max =
                matchRange[2] === '*' ? Infinity : Number(matchRange[2]);
            if (count >= min && count <= max) {
                return matchRange[3];
            }
        }
    }
    const category = new Intl.PluralRules(locale).select(count);
    const categoryLine =
        parts.find((part) => part.startsWith(`{${category}}`)) ??
        parts.find((part) => part.startsWith('{other}'));
    if (categoryLine) {
        return categoryLine.replace(/^\{\w+\}\s*/, '');
    }

    return count === 1 ? parts[0] : parts[parts.length - 1];
}

export function useTranslation(): TranslationHelpers {
    const page = usePage();
    const lang = (page.props as { lang?: Record<string, unknown> }).lang;

    function __(
        key: string,
        replaces?: TranslationReplacements | string,
    ): string {
        if (!lang || typeof lang !== 'object') {
            return formatString(key, replaces);
        }

        let translation: unknown = lang[key];

        if (translation === undefined && key.includes('.')) {
            const segments = key.split('.');
            let current: unknown = lang;
            for (const segment of segments) {
                if (
                    current &&
                    typeof current === 'object' &&
                    segment in (current as Record<string, unknown>)
                ) {
                    current = (current as Record<string, unknown>)[segment];
                } else {
                    current = undefined;
                    break;
                }
            }
            if (typeof current === 'string') {
                translation = current;
            }
        }

        const resolved = typeof translation === 'string' ? translation : key;
        return formatString(resolved, replaces);
    }

    function trans(
        key: string,
        replaces?: TranslationReplacements | string,
    ): string {
        return __(key, replaces);
    }

    function transChoice(
        key: string,
        count: number,
        replaces?: TranslationReplacements,
    ): string {
        const line = __(key);
        const pageLocale = page.props.locale;
        const locale =
            typeof pageLocale === 'string' && pageLocale ? pageLocale : 'en';
        const chosen = choosePluralLine(line, count, locale);
        return formatString(chosen, { ...replaces, count });
    }

    return {
        __,
        trans,
        transChoice,
        trans_choice: transChoice,
    };
}

export const useLang = useTranslation;

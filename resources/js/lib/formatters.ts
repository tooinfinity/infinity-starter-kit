export function formatRelativeTime(
    dateString: string | null | undefined,
    locale?: unknown,
): string {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    const resolvedLocale = typeof locale === 'string' && locale ? locale : 'en';
    const rtf = new Intl.RelativeTimeFormat(resolvedLocale, {
        numeric: 'auto',
    });

    if (Math.abs(diffInSeconds) < 60) {
        return rtf.format(0, 'second');
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return rtf.format(-diffInMinutes, 'minute');
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return rtf.format(-diffInHours, 'hour');
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return rtf.format(-diffInDays, 'day');
    }

    return date.toLocaleDateString(resolvedLocale);
}

export function formatDateTime(
    dateString: string | null | undefined,
    locale?: unknown,
    timezone?: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return '';
    }

    const resolvedLocale = typeof locale === 'string' && locale ? locale : 'en';

    return date.toLocaleString(resolvedLocale, {
        ...(timezone ? { timeZone: timezone } : {}),
        ...options,
    });
}

export function formatDate(
    dateString: string | null | undefined,
    locale?: unknown,
    timezone?: string,
    options?: Intl.DateTimeFormatOptions,
): string {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return '';
    }

    const resolvedLocale = typeof locale === 'string' && locale ? locale : 'en';

    return date.toLocaleDateString(resolvedLocale, {
        ...(timezone ? { timeZone: timezone } : {}),
        ...options,
    });
}

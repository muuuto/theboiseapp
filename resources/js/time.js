import { intlLocale, t } from './i18n.js';

// "3 days ago" and full dates, shared by the pages that show comments and posts (in the page's language)
const units = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

export function ago(iso) {
    const seconds = (new Date(iso) - new Date()) / 1000;
    const relative = new Intl.RelativeTimeFormat(intlLocale(), { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
    }
    return t('just now');
}

export function fullDate(iso) {
    return new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
}

// Any date format in the page's language: formatDate('2026-08-01', { day: 'numeric', month: 'short' })
export function formatDate(value, options) {
    return new Intl.DateTimeFormat(intlLocale(), options).format(typeof value === 'string' && value.length === 10 ? new Date(`${value}T00:00:00`) : new Date(value));
}

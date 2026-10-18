// "3 days ago" and full dates, shared by the pages that show comments and posts

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
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
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
    }
    return 'just now';
}

const full = new Intl.DateTimeFormat('en-GB', { dateStyle: 'long', timeStyle: 'short' });

export function fullDate(iso) {
    return full.format(new Date(iso));
}

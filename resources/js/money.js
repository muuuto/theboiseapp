// Money of shared expenses: always cents (integers) in the data, in euro (an expense in another
// currency keeps its own amount too: hundredths of that currency)
import { locale } from './i18n.js';

// "€12.50" in English, "12,50 €" in Italian; moneyIn(4500, 'GBP') → "£45.00"
export const moneyIn = (cents, currency = 'EUR') => new Intl.NumberFormat(locale.value === 'it' ? 'it-IT' : 'en-IE', { style: 'currency', currency }).format(cents / 100);
export const money = (cents) => moneyIn(cents, 'EUR');

// "£", "$", "CHF"
export const currencySymbol = (currency) => new Intl.NumberFormat(locale.value === 'it' ? 'it-IT' : 'en-IE', { style: 'currency', currency }).formatToParts(0).find((part) => part.type === 'currency')?.value ?? currency;

// "12,50" or "12.5" → 1250; null if it isn't an amount (same rules as the server)
export function parseEuros(text) {
    const match = String(text ?? '').replace(/[\s€]/g, '').match(/^(\d{1,7})(?:[.,](\d{1,2}))?$/);
    return match ? Number(match[1]) * 100 + Number((match[2] ?? '0').padEnd(2, '0')) : null;
}

// Same split as the server (ExpenseMath::split), to preview each share while typing
export function split(total, type, values) {
    const ids = Object.keys(values).map(Number).sort((a, b) => a - b);
    if (type === 'exact') return Object.fromEntries(ids.map((id) => [id, values[id]]));

    const weights = Object.fromEntries(ids.map((id) => [id, type === 'equal' ? 1 : values[id]]));
    const sum = ids.reduce((total, id) => total + weights[id], 0);
    if (!sum) return {};
    const result = {};
    const remainders = [];
    for (const id of ids) {
        result[id] = Math.floor((total * weights[id]) / sum);
        remainders.push([id, (total * weights[id]) % sum]);
    }
    let left = total - Object.values(result).reduce((a, b) => a + b, 0);
    remainders.sort((a, b) => b[1] - a[1] || a[0] - b[0]);
    for (const [id] of remainders) {
        if (left-- <= 0) break;
        result[id]++;
    }
    return result;
}

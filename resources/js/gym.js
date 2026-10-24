import { intlLocale, t, tc } from './i18n.js';

// Gym tracker: muscle groups and focus (written out here so the translation check sees them),
// kg and rows of kg × sets × reps
const groups = {
    Chest: () => t('Chest'),
    Back: () => t('Back'),
    Shoulders: () => t('Shoulders'),
    Legs: () => t('Legs'),
    Arms: () => t('Arms'),
    Core: () => t('Core'),
};
export const groupNames = Object.keys(groups);
export const groupLabel = (group) => groups[group]?.() ?? t('Other');

// What a workout trains: chosen by hand, or from its exercises' muscle groups (core and other
// don't count), like the server does
const focuses = {
    upper: () => t('Upper body'),
    lower: () => t('Lower body'),
    mixed: () => t('Mixed'),
};
export const focusNames = Object.keys(focuses);
export const focusLabel = (focus) => focuses[focus]?.() ?? '';
const areas = { Chest: 'upper', Back: 'upper', Shoulders: 'upper', Arms: 'upper', Legs: 'lower' };
export function autoFocus(groupList) {
    const found = new Set(groupList.map((group) => areas[group]).filter(Boolean));
    return found.size > 1 ? 'mixed' : (found.values().next().value ?? null);
}

export const kg = (value) => `${new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 2 }).format(value)} kg`;

// Weight × reps added up: kg, or tonnes from 1000 kg
export const total = (value) => (value >= 1000 ? `${new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 1 }).format(value / 1000)} t` : kg(value));

// "3 × 10 · 60 kg", "3 × 12" for bodyweight, "3 sets · 60 kg" when the reps aren't written
export function rowText(row) {
    const count = row.reps ? `${row.sets} × ${row.reps}` : tc(':count set|:count sets', row.sets);
    return row.weight ? `${count} · ${kg(row.weight)}` : count;
}

// Italians write 62,5: both work
export function number(value) {
    const text = String(value ?? '').trim().replace(',', '.');
    return text === '' || Number.isNaN(Number(text)) ? null : Number(text);
}

import { intlLocale, t } from './i18n.js';

// Gym tracker: muscle groups (written out here so the translation check sees them), kg and sets
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

export const kg = (value) => `${new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 2 }).format(value)} kg`;

// Weight × reps added up: kg, or tonnes from 1000 kg
export const total = (value) => (value >= 1000 ? `${new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 1 }).format(value / 1000)} t` : kg(value));

// "60 kg × 8", or "× 12" for bodyweight
export const setText = (set) => (set.weight ? `${kg(set.weight)} × ${set.reps}` : `× ${set.reps}`);

// Italians write 62,5: both work
export function number(value) {
    const text = String(value ?? '').trim().replace(',', '.');
    return text === '' || Number.isNaN(Number(text)) ? null : Number(text);
}

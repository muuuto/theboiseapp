import { t } from './i18n.js';

// The badges, in both languages (the server only says which, and how far each one is):
// people pages and Wrapped
export const badgeInfo = {
    explorer: { emoji: '🌍', name: () => t('Explorer'), goal: (n) => t('Albums in :count countries', { count: n }) },
    globetrotter: { emoji: '✈️', name: () => t('Globetrotter'), goal: (n) => t('A place :count km from home', { count: n.toLocaleString() }) },
    mountaineer: { emoji: '🏔️', name: () => t('Mountaineer'), goal: (n) => t('A place above :count m', { count: n.toLocaleString() }) },
    veteran: { emoji: '📸', name: () => t('Veteran'), goal: (n) => t('In :count albums', { count: n }) },
    pioneer: { emoji: '🗓️', name: () => t('Since the beginning'), goal: (n) => t('First album :count years ago', { count: n }) },
    photographer: { emoji: '🎞️', name: () => t('Photographer'), goal: (n) => t('Posted :count albums', { count: n }) },
    social: { emoji: '🤝', name: () => t('Social butterfly'), goal: (n) => t('Albums with :count different people', { count: n }) },
    chatterbox: { emoji: '💬', name: () => t('Chatterbox'), goal: (n) => t(':count comments', { count: n }) },
    beloved: { emoji: '❤️', name: () => t('Beloved'), goal: (n) => t(':count likes on their comments', { count: n }) },
    organiser: { emoji: '🧭', name: () => t('Organiser'), goal: (n) => t('Organised :count trips or events', { count: n }) },
    party: { emoji: '🎉', name: () => t('Party animal'), goal: (n) => t('Went to :count events', { count: n }) },
    legday: { emoji: '🏋️', name: () => t('Never skips leg day'), goal: (n) => t(':count workouts', { count: n }) },
};

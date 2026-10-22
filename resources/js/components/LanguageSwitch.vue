<script setup>
import { router } from '@inertiajs/vue3';
import { locale } from '../i18n.js';

// English | Italiano: saved on the account (or, before logging in, for this visit)
const languages = [
    { code: 'en', label: 'English' },
    { code: 'it', label: 'Italiano' },
];
function choose(code) {
    if (code !== locale.value) router.put('/locale', { locale: code }, { preserveScroll: true });
}
</script>

<template>
    <div role="radiogroup" :aria-label="t('Language')" class="inline-flex rounded-full bg-slate-200/70 p-0.5 text-sm font-semibold dark:bg-slate-800">
        <button
            v-for="language in languages"
            :key="language.code"
            type="button"
            role="radio"
            :lang="language.code"
            :aria-checked="locale === language.code"
            class="rounded-full px-3 py-1 transition"
            :class="locale === language.code
                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
            @click="choose(language.code)"
        >
            {{ language.label }}
        </button>
    </div>
</template>

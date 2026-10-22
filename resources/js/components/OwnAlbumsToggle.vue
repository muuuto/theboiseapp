<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// "All albums | My albums" (My albums: only the ones you posted).
// On the account page (saves) it's the default, saved on the account. On the album list and the map
// it only changes what you see now: the address doesn't change, so a reload goes back to the default
const props = defineProps({
    on: Boolean,
    default: Boolean, // the account's choice
    saves: { type: Boolean, default: false },
});
const saving = ref(false);
const page = usePage();

function choose(mine) {
    if (mine === props.on || saving.value) return;
    saving.value = true;
    const done = { preserveScroll: true, onFinish: () => (saving.value = false) };
    if (props.saves) {
        router.put('/account/album-filter', { on: mine }, done);
        return;
    }
    // Same page with the search and tag kept, from the first page; "mine" only when it differs
    // from the default. A clean address stays as it is (a reload goes back to the default); one
    // that already has a page or a choice (?page=2&mine=0) is updated, so it never says otherwise
    const url = new URL(page.url, window.location.origin);
    const query = Object.fromEntries([...url.searchParams].filter(([key]) => !['page', 'mine'].includes(key)));
    const keepAddress = !url.searchParams.has('page') && !url.searchParams.has('mine');
    router.get(url.pathname, mine === props.default ? query : { ...query, mine: mine ? 1 : 0 }, { ...done, preserveState: true, preserveUrl: keepAddress });
}

const options = [
    { mine: false, label: 'All albums', title: 'Every album you\'re in' },
    { mine: true, label: 'My albums', title: 'Only the albums you posted' },
];
</script>

<template>
    <div role="radiogroup" :aria-label="t('Which albums')" class="inline-flex shrink-0 rounded-full bg-slate-200/70 p-0.5 text-sm font-semibold dark:bg-slate-800" :class="saving && 'opacity-60'">
        <button
            v-for="option in options"
            :key="option.label"
            type="button"
            role="radio"
            :aria-checked="on === option.mine"
            :title="t(option.title)"
            class="rounded-full px-2.5 py-1 text-xs whitespace-nowrap transition sm:px-3 sm:text-sm"
            :class="on === option.mine
                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'"
            @click="choose(option.mine)"
        >
            {{ t(option.label) }}
        </button>
    </div>
</template>

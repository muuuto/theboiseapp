<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// "All albums | My albums": My albums hides the albums others posted (where you're tagged).
// Saved on the account, the album list and the map follow it
const props = defineProps({ on: Boolean });
const saving = ref(false);

function choose(mine) {
    if (mine === props.on || saving.value) return;
    saving.value = true;
    router.put('/account/album-filter', { on: mine }, { preserveScroll: true, onFinish: () => (saving.value = false) });
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

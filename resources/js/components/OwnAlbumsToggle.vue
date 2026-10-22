<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// "Only albums I posted": on = the albums others posted (where you're tagged) are hidden.
// Saved on the account, the album list and the map follow it
const props = defineProps({
    on: Boolean,
    // Small, without a box: next to a title
    compact: { type: Boolean, default: false },
});
const saving = ref(false);

function toggle() {
    saving.value = true;
    router.put('/account/album-filter', { on: !props.on }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="on"
        aria-label="Only albums I posted"
        title="Only albums I posted"
        :disabled="saving"
        class="inline-flex shrink-0 items-center gap-2 text-sm font-medium transition disabled:opacity-60"
        :class="compact
            ? 'rounded-full py-1 pr-1 pl-2.5 text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800'
            : 'rounded-xl bg-white px-3 py-2 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-900 dark:ring-slate-700'"
        @click="toggle"
    >
        <template v-if="compact">Only mine</template>
        <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition" :class="on ? 'bg-accent' : 'bg-slate-300 dark:bg-slate-600'">
            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="on ? 'left-4.5' : 'left-0.5'" />
        </span>
        <template v-if="!compact">Only albums I posted</template>
    </button>
</template>

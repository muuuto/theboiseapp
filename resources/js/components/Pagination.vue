<script setup>
import { Link } from '@inertiajs/vue3';

// Laravel paginator links: [{ url, label, active }], first and last are "previous" and "next"
defineProps({ links: Array });

function label(text) {
    return text.replace('&laquo; Previous', '‹').replace('Next &raquo;', '›');
}
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="!link.url"
                class="flex h-10 min-w-10 items-center justify-center rounded-xl px-3 text-sm text-slate-400"
                v-html="label(link.label)"
            />
            <Link
                v-else
                :href="link.url"
                class="flex h-10 min-w-10 items-center justify-center rounded-xl px-3 text-sm font-medium transition"
                :class="link.active
                    ? 'bg-accent text-white'
                    : 'bg-white ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-900 dark:ring-slate-700'"
                v-html="label(link.label)"
            />
        </template>
    </nav>
</template>

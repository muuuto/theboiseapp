<script setup>
import { Link } from '@inertiajs/vue3';
import { ago, fullDate } from '../time.js';

// What happened to albums (admins only): who posted, edited, deleted, and who can post
defineProps({
    events: Array,
    showLinks: { type: Boolean, default: false }, // link to the album (the maintenance page list)
});
</script>

<template>
    <p v-if="!events.length" class="text-sm text-slate-500">Nothing yet.</p>
    <ol class="space-y-3">
        <li v-for="event in events" :key="event.id" class="border-l-2 border-slate-200 pl-3 text-sm dark:border-slate-700">
            <p>
                <Link v-if="showLinks && event.albumId" :href="`/listings/${event.albumId}`" class="font-medium hover:text-accent hover:underline">{{ event.summary }}</Link>
                <span v-else class="font-medium">{{ event.summary }}</span>
            </p>
            <ul v-if="event.lines.length" class="mt-0.5 space-y-0.5 text-xs break-words text-slate-600 dark:text-slate-400">
                <li v-for="line in event.lines" :key="line">{{ line }}</li>
            </ul>
            <time :datetime="event.at" :title="fullDate(event.at)" class="text-xs text-slate-500">{{ ago(event.at) }}</time>
        </li>
    </ol>
</template>

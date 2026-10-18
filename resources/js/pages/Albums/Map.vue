<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';

const props = defineProps({
    albums: Array,
    withoutCoordinates: Number,
});

const isAdmin = computed(() => usePage().props.auth.user.isAdmin);
const suggestedCount = computed(() => props.albums.filter((album) => album.suggested).length);
</script>

<template>
    <Head title="Map" />

    <div class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <h1 class="text-2xl font-bold">Your albums on the map</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ albums.length }} {{ albums.length === 1 ? 'album' : 'albums' }}
                <template v-if="withoutCoordinates"> · {{ withoutCoordinates }} without a place yet</template>
            </p>
        </div>
        <a v-if="isAdmin && suggestedCount" href="/admin/locations" class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-300">
            <Info class="size-4" /> {{ suggestedCount }} guessed locations to review
        </a>
    </div>

    <LeafletMap :markers="albums" class="h-[calc(100vh-13rem)] min-h-96" />
    <p v-if="suggestedCount" class="mt-2 text-xs text-slate-500">Grey pins are approximate: guessed from the location text.</p>
</template>

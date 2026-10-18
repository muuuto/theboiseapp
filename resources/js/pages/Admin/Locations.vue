<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, CheckCheck, MapPinOff, Pencil, Trash2 } from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';

// places: the guessed pins to review; missing: albums without any place
const props = defineProps({ places: Array, missing: Array });

// Farthest from Bolzano first: the wrong guesses (a "Funes" in Spain...) are usually far away
const home = [46.4983, 11.3548];
const distance = (album) => Math.hypot(album.latitude - home[0], (album.longitude - home[1]) * Math.cos((home[0] * Math.PI) / 180));
const suggested = computed(() => [...props.places].sort((a, b) => distance(b) - distance(a)));
const map = ref(null);
const selected = ref(null);

function show(album) {
    selected.value = album.id;
    map.value.focus(album.id);
}

const options = { preserveScroll: true, preserveState: true };
const confirm = (place) => router.post(`/admin/locations/${place.id}/confirm`, {}, options);
const clear = (place) => router.delete(`/admin/locations/${place.id}`, options);
function confirmAll() {
    if (window.confirm(`Confirm all the ${suggested.value.length} locations still in the list?`)) {
        router.post('/admin/locations/confirm-all', {}, options);
    }
}
</script>

<template>
    <Head title="Album locations" />

    <h1 class="text-2xl font-bold">Album locations</h1>
    <p class="mt-1 mb-6 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        These places were guessed from each album's location text. Click an album to see it on the map, then
        <strong>confirm</strong> it, <strong>fix</strong> it in the album form, or <strong>remove</strong> it if it's wrong.
    </p>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <section>
                <div class="mb-2 flex items-center gap-2">
                    <h2 class="font-bold">To review <span class="text-sm font-medium text-slate-500">{{ suggested.length }}</span></h2>
                    <button
                        v-if="suggested.length"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold text-green-700 shadow-sm ring-1 ring-slate-200 hover:bg-green-50 dark:bg-slate-900 dark:text-green-400 dark:ring-slate-800"
                        @click="confirmAll"
                    >
                        <CheckCheck class="size-4" /> Confirm all remaining
                    </button>
                </div>
                <p class="mb-3 text-xs text-slate-500">Sorted by distance from Bolzano, farthest first: wrong guesses are usually far away.</p>
                <p v-if="!suggested.length" class="text-sm text-slate-500">All done 🎉</p>
                <ul class="space-y-2">
                    <li
                        v-for="album in suggested"
                        :key="album.id"
                        class="flex cursor-pointer items-center gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 transition dark:bg-slate-900"
                        :class="selected === album.id ? 'ring-2 ring-accent' : 'ring-slate-200 hover:ring-slate-300 dark:ring-slate-800'"
                        @click="show(album)"
                    >
                        <img :src="album.cover" alt="" class="size-14 shrink-0 rounded-xl object-cover" />
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="truncate font-semibold">{{ album.title }}</p>
                            <p class="truncate">"{{ album.location }}"</p>
                            <p class="truncate text-xs text-slate-500">→ {{ album.placeName }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1" @click.stop>
                            <button class="rounded-lg p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-950/40" title="Confirm" @click="confirm(album)">
                                <Check class="size-4" /><span class="sr-only">Confirm</span>
                            </button>
                            <Link :href="`/listings/${album.albumId}/edit`" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" title="Fix in the album form">
                                <Pencil class="size-4" /><span class="sr-only">Fix</span>
                            </Link>
                            <button class="rounded-lg p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40" title="Remove (wrong place)" @click="clear(album)">
                                <Trash2 class="size-4" /><span class="sr-only">Remove</span>
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="missing.length">
                <h2 class="mb-2 font-bold">Without a place <span class="text-sm font-medium text-slate-500">{{ missing.length }}</span></h2>
                <ul class="space-y-1">
                    <li v-for="album in missing" :key="album.id">
                        <Link :href="`/listings/${album.id}/edit`" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-white dark:hover:bg-slate-900">
                            <MapPinOff class="size-4 shrink-0 text-slate-400" />
                            <span class="truncate"><strong>{{ album.title }}</strong> · "{{ album.location }}"</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <div class="lg:sticky lg:top-22 lg:self-start">
            <LeafletMap ref="map" :markers="suggested" class="h-96 lg:h-[calc(100vh-8rem)]" />
        </div>
    </div>
</template>

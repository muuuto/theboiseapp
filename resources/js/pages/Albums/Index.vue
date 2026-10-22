<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Quote, Search, X } from 'lucide-vue-next';
import AlbumCard from '../../components/AlbumCard.vue';
import Pagination from '../../components/Pagination.vue';
import OwnAlbumsToggle from '../../components/OwnAlbumsToggle.vue';
import Decorations from '../../components/Decorations.vue';
import { heroPatterns, randomHeroPattern } from '../../heroPatterns.js';

const props = defineProps({
    albums: Object,
    slogan: Object,
    filters: Object,
    ownFilter: { type: Object, default: () => ({ available: false, on: false, default: false }) },
    wrappedBanner: { type: Number, default: null }, // December/January: the year to open
    decoration: { type: Object, default: null }, // holiday or birthday: things falling, and a badge
    pinned: { type: Object, default: null }, // a forum post the admins put at the top (news, changelog)
});

// The pinned post can be closed: it stays closed in this browser until another one is pinned
const pinnedHidden = ref(readPinnedHidden());
function readPinnedHidden() {
    try {
        return Number(localStorage.getItem('pinned-hidden')) === props.pinned?.id;
    } catch {
        return false;
    }
}
function hidePinned() {
    pinnedHidden.value = true;
    try {
        localStorage.setItem('pinned-hidden', String(props.pinned.id));
    } catch {}
}

const user = computed(() => usePage().props.auth.user);

// A different banner pattern on each visit (?pattern=waves shows a specific one, to preview them)
const requested = new URLSearchParams(window.location.search).get('pattern');
const pattern = heroPatterns.find((candidate) => candidate.name === requested) ?? randomHeroPattern();
const search = ref(props.filters.search);
const hasFilters = computed(() => props.filters.search || props.filters.tag);

// "All albums | My albums" changed from the default just for now: searching keeps it
const view = () => (props.ownFilter.on !== props.ownFilter.default ? { mine: props.ownFilter.on ? 1 : 0 } : {});

function submitSearch() {
    router.get('/', { ...(search.value ? { search: search.value } : {}), ...view() }, { preserveState: true });
}

function filterByTag(tag) {
    search.value = '';
    router.get('/', { tag, ...view() });
}

function resetFilters() {
    search.value = '';
    router.get('/', view());
}
</script>

<template>
    <Head :title="t('Albums')" />

    <!-- Pinned post: a slim link above everything -->
    <div v-if="pinned && !pinnedHidden" class="mb-4 flex items-center gap-2 rounded-2xl bg-white py-1.5 pr-1.5 pl-3 text-sm shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <span aria-hidden="true">📌</span>
        <Link :href="pinned.url" class="min-w-0 flex-1 truncate font-semibold hover:text-accent">
            {{ pinned.title }} <span class="text-accent">→</span>
        </Link>
        <button type="button" class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" :title="t('Close')" @click="hidePinned">
            <X class="size-4" /><span class="sr-only">{{ t('Close') }}</span>
        </button>
    </div>

    <!-- Hero: the familiar teal banner with a random slogan from the friends -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-teal-600 to-teal-700 px-6 py-10 text-center text-white shadow-lg sm:py-14">
        <div
            class="absolute inset-0 bg-repeat"
            :style="{ backgroundImage: pattern.image, backgroundSize: pattern.size, opacity: pattern.opacity }"
        />
        <div class="relative">
            <div v-if="decoration" class="mb-4"><Decorations :key="decoration.season" :decoration="decoration" /></div>
            <h1 class="text-5xl font-extrabold uppercase tracking-tight sm:text-6xl">
                The<span class="text-slate-900">Boise</span>
            </h1>

            <p v-if="slogan" class="mx-auto mt-5 max-w-2xl text-lg font-semibold text-teal-50 sm:text-xl">
                <Quote class="mr-1 inline size-5 -translate-y-1 opacity-70" />
                <span class="[&_a]:underline" v-html="slogan.html" />
                <span class="block text-sm font-medium text-teal-100/80">{{ t('by :name', { name: slogan.author }) }}</span>
            </p>
            <p v-else class="mt-5 text-xl font-semibold text-teal-50">{{ t('Welcome to THEBOISE.IT') }}</p>

            <div v-if="!user.isDemo" class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="/zeccaGay" class="rounded-xl border-2 border-white/80 px-4 py-2 text-sm font-bold uppercase transition hover:bg-white hover:text-teal-700">
                    {{ t('Is Zecca gay?') }}
                </a>
                <a href="https://paypal.me/zeccasupergay" class="rounded-xl border-2 border-white/80 px-4 py-2 text-sm font-bold uppercase transition hover:bg-white hover:text-teal-700">
                    {{ t('Offer me a sprissino') }}
                </a>
            </div>
        </div>
    </section>

    <!-- December and January: this year's (or last year's) Wrapped -->
    <a
        v-if="wrappedBanner"
        :href="`/wrapped/${wrappedBanner}`"
        class="mt-6 flex items-center gap-4 rounded-3xl bg-gradient-to-r from-rose-500 via-fuchsia-600 to-indigo-600 p-5 text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
    >
        <span class="text-4xl">🎁</span>
        <span class="min-w-0 flex-1">
            <span class="block text-xl font-extrabold">{{ t('Your :year Wrapped is ready', { year: wrappedBanner }) }}</span>
            <span class="text-sm text-white/85">{{ t('Albums, places, kilometres, your crew. Tap to see it.') }}</span>
        </span>
    </a>

    <!-- Title, search and actions -->
    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
        <!-- Title, with "All albums | My albums" on its right (no row of its own) -->
        <div class="flex items-center gap-3">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold whitespace-nowrap">{{ t('Your albums') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ tc(':count album|:count albums', albums.total) }}
                    <template v-if="filters.tag"> {{ t('tagged') }} <strong>{{ filters.tag }}</strong></template>
                    <template v-if="filters.search"> {{ t('matching') }} <strong>"{{ filters.search }}"</strong></template>
                    <template v-if="ownFilter.on"> {{ t('posted by you') }}</template>
                </p>
            </div>
            <OwnAlbumsToggle v-if="ownFilter.available" :on="ownFilter.on" :default="ownFilter.default" class="ml-auto sm:ml-2" />
        </div>

        <!-- Phones: a full-width row above the title. Larger screens: next to the title, on the right -->
        <div v-if="!user.isDemo || user.isAdmin" class="order-first flex gap-2 sm:order-none sm:ml-auto">
            <a
                v-if="!user.isDemo"
                href="/slogan/create"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 sm:flex-none sm:py-2 text-sm font-semibold shadow-sm ring-1 ring-slate-200 transition hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
            >
                <Quote class="size-4" /> {{ t('Post slogan') }}
            </a>
            <a
                v-if="user.canPostAlbums"
                href="/listings/create"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 sm:flex-none sm:py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark"
            >
                <Plus class="size-4" /> {{ t('Post album') }}
            </a>
        </div>
    </div>

    <form class="relative mt-4" @submit.prevent="submitSearch">
        <Search class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
        <input
            v-model="search"
            type="search"
            :placeholder="t('Search albums by title, description or tag...')"
            class="h-12 w-full rounded-2xl border-0 bg-white pr-28 pl-12 shadow-sm ring-1 ring-slate-200 transition placeholder:text-slate-400 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700"
        />
        <button type="submit" class="absolute top-1.5 right-1.5 h-9 rounded-xl bg-accent px-4 text-sm font-semibold text-white transition hover:bg-teal-700">
            {{ t('Search') }}
        </button>
    </form>

    <div v-if="hasFilters" class="mt-3">
        <button class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-3 py-1 text-sm font-medium hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700" @click="resetFilters">
            <X class="size-4" /> {{ t('Clear filters') }}
        </button>
    </div>

    <!-- Albums -->
    <div v-if="albums.data.length" class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <AlbumCard v-for="(album, index) in albums.data" :key="album.id" :album="album" :eager="index < 6" @tag="filterByTag" />
    </div>
    <p v-else class="mt-10 text-center text-slate-500">{{ t('No albums found.') }}</p>

    <Pagination class="mt-8" :links="albums.links" />
</template>

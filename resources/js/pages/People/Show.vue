<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Award, Images } from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';
import { t, tc } from '../../i18n.js';
import { formatDate } from '../../time.js';
import { badgeInfo } from '../../badges.js';

// A person: their numbers, their badges, and what you share (only the albums you're both in)
const props = defineProps({ person: Object, stats: Object, together: Object, badges: Array });

const shownBadges = computed(() => props.badges.filter((badge) => badgeInfo[badge.key]));
const earnedCount = computed(() => props.badges.filter((badge) => badge.earned).length);
const year = (date) => formatDate(date, { month: 'short', year: 'numeric' });
const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
</script>

<template>
    <Head :title="person.name" />

    <Link href="/people" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> {{ t('People') }}
    </Link>

    <!-- Who, and their numbers -->
    <section class="mb-6 flex flex-col gap-5 rounded-3xl bg-gradient-to-br from-teal-500 via-teal-700 to-slate-900 p-6 text-white shadow-lg sm:flex-row sm:items-center sm:p-8">
        <img v-if="person.avatar" :src="person.avatar" alt="" class="size-24 shrink-0 rounded-full object-cover ring-4 ring-white/30" />
        <span v-else class="flex size-24 shrink-0 items-center justify-center rounded-full bg-white/20 text-4xl font-black ring-4 ring-white/30">{{ person.name.charAt(0).toUpperCase() }}</span>
        <div class="min-w-0">
            <h1 class="text-4xl font-black tracking-tight">{{ person.name }}<span v-if="person.isMe" class="ml-2 text-lg font-semibold text-white/70">{{ t('(you)') }}</span></h1>
            <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-white/90">
                <span><strong class="text-2xl">{{ stats.albums }}</strong> {{ tc('album|albums', stats.albums) }}</span>
                <span><strong class="text-2xl">{{ stats.countries.length }}</strong> {{ tc('country|countries', stats.countries.length) }}</span>
                <span v-if="stats.since">{{ t('since :year', { year: stats.since }) }}</span>
                <span><strong class="text-2xl">{{ earnedCount }}</strong> {{ tc('badge|badges', earnedCount) }}</span>
            </p>
            <p v-if="stats.countries.length" class="mt-2 text-2xl leading-snug">{{ stats.countries.map((country) => country.flag ?? '🏳️').join(' ') }}</p>
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <!-- You and them -->
        <section v-if="together" :class="cardClass" class="space-y-4">
            <h2 class="text-lg font-bold">{{ t('You and :name', { name: person.name }) }}</h2>
            <p v-if="!together.albums" class="text-sm text-slate-500">{{ t('No albums together yet. Time to plan something!') }}</p>
            <template v-else>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-teal-50 p-3 dark:bg-teal-950/40">
                        <p class="text-3xl font-black text-teal-700 dark:text-teal-300">{{ together.albums }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-300">{{ tc('album together|albums together', together.albums) }}</p>
                    </div>
                    <div class="rounded-2xl bg-sky-50 p-3 dark:bg-sky-950/40">
                        <p class="text-3xl font-black text-sky-700 dark:text-sky-300">{{ together.countries.length }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-300">{{ tc('country|countries', together.countries.length) }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-3 dark:bg-amber-950/40">
                        <p class="text-3xl font-black text-amber-700 dark:text-amber-300">{{ together.since }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-300">{{ t('together since') }}</p>
                    </div>
                </div>
                <p v-if="together.first" class="text-sm text-slate-600 dark:text-slate-300">
                    {{ t('Your first album together:') }} <Link :href="`/listings/${together.first.id}`" class="font-semibold text-accent hover:underline">{{ together.first.title }}</Link>
                </p>
                <LeafletMap v-if="together.pins.length" :markers="together.pins" :zoom="4" class="h-56 overflow-hidden rounded-2xl" />
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <Link v-for="album in together.list" :key="album.id" :href="`/listings/${album.id}`" class="group overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                        <img :src="album.cover" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover transition group-hover:scale-105" />
                        <span class="block truncate px-2 pt-1.5 text-sm font-semibold">{{ album.title }}</span>
                        <span class="block px-2 pb-1.5 text-xs text-slate-500">{{ year(album.dateFrom) }}</span>
                    </Link>
                </div>
                <p v-if="together.albums > together.list.length" class="flex items-center gap-1.5 text-xs text-slate-500"><Images class="size-4" /> {{ t('The latest :count.', { count: together.list.length }) }}</p>
            </template>
        </section>

        <!-- Badges -->
        <section :class="cardClass" class="space-y-3" :style="!together ? 'grid-column: 1 / -1' : ''">
            <h2 class="flex items-center gap-2 text-lg font-bold"><Award class="size-5 text-amber-500" /> {{ t('Badges') }} <span class="text-sm font-medium text-slate-500">{{ earnedCount }}/{{ shownBadges.length }}</span></h2>
            <ul class="grid gap-2" :class="!together && 'sm:grid-cols-2 lg:grid-cols-3'">
                <li v-for="badge in shownBadges" :key="badge.key" class="flex items-center gap-3 rounded-2xl p-2.5 ring-1" :class="badge.earned ? 'bg-amber-50 ring-amber-200 dark:bg-amber-950/30 dark:ring-amber-900' : 'ring-slate-200 dark:ring-slate-800'">
                    <span class="text-3xl" :class="!badge.earned && 'opacity-30 grayscale'">{{ badgeInfo[badge.key].emoji }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold" :class="!badge.earned && 'text-slate-500'">{{ badgeInfo[badge.key].name() }}</span>
                        <span class="block text-xs text-slate-500">{{ badgeInfo[badge.key].goal(badge.target) }}</span>
                        <span v-if="!badge.earned" class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <span class="block h-full rounded-full bg-amber-400" :style="{ width: `${Math.min(100, (badge.value / badge.target) * 100)}%` }" />
                        </span>
                    </span>
                    <span v-if="!badge.earned" class="shrink-0 text-xs text-slate-500 tabular-nums">{{ badge.value.toLocaleString() }}/{{ badge.target.toLocaleString() }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>

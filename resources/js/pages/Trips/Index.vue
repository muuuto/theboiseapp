<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, Check, Clock, Images, ListChecks, MapPin, PartyPopper, Plane, Plus, Users, Vote } from 'lucide-vue-next';
import Countdown from '../../components/Countdown.vue';
import { formatDate } from '../../time.js';

// Trips first (the big plans), then events (a dinner, a party) in their own, lighter list
defineProps({ trips: Array, done: Array, events: { type: Array, default: () => [] }, pastEvents: { type: Array, default: () => [] } });

const rsvpLabel = { yes: "I'm in", maybe: 'Maybe', no: "Can't come" };
const rsvpClass = {
    yes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    maybe: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    no: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const statusLabel = { planning: 'Voting the dates', upcoming: 'Upcoming', ongoing: 'Happening now', finished: 'Back home: make it an album' };
const statusClass = {
    planning: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    upcoming: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    ongoing: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    finished: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
};
</script>

<template>
    <Head :title="t('Trips & events')" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('Trips') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('Plan the next one together: dates, who brings what, shared expenses.') }}</p>
        </div>
        <div class="flex gap-2 sm:ml-auto">
            <Link href="/trips/create?kind=event" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 transition hover:bg-white sm:flex-none sm:py-2 dark:ring-slate-700 dark:hover:bg-slate-900">
                <PartyPopper class="size-4" /> {{ t('New event') }}
            </Link>
            <Link href="/trips/create" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark sm:flex-none sm:py-2">
                <Plus class="size-4" /> {{ t('Plan a trip') }}
            </Link>
        </div>
    </div>

    <div v-if="trips.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="trip in trips"
            :key="trip.id"
            class="group relative flex flex-col gap-3 overflow-hidden rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800"
        >
            <span class="w-fit rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass[trip.status]">{{ t(statusLabel[trip.status]) }}</span>
            <div>
                <h2 class="text-xl font-extrabold leading-tight group-hover:text-accent">
                    <Link :href="`/trips/${trip.id}`" class="after:absolute after:inset-0">{{ trip.title }}</Link>
                </h2>
                <p v-if="trip.destination" class="mt-0.5 flex items-center gap-1 text-sm text-slate-500"><MapPin class="size-4 shrink-0" /> {{ trip.destination }}</p>
            </div>

            <p class="flex items-center gap-1.5 text-sm font-medium">
                <template v-if="trip.dates">
                    <CalendarClock class="size-4 shrink-0 text-accent" /> {{ trip.dates }}
                    <span v-if="trip.status === 'upcoming'" class="font-bold text-accent">· <Countdown :to="trip.dateFrom" size="small" /></span>
                </template>
                <template v-else><Vote class="size-4 shrink-0 text-violet-600" /> {{ t(':count date options to vote', { count: trip.optionCount }) }}</template>
            </p>

            <div class="mt-auto flex items-center justify-between gap-2 pt-1">
                <div class="flex -space-x-2">
                    <template v-for="member in trip.members.slice(0, 6)" :key="member.id">
                        <img v-if="member.avatar" :src="member.avatar" :alt="member.name" :title="member.name" class="size-7 rounded-full object-cover ring-2 ring-white dark:ring-slate-900" />
                        <span v-else :title="member.name" class="flex size-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold ring-2 ring-white dark:bg-slate-700 dark:ring-slate-900">{{ member.name.charAt(0).toUpperCase() }}</span>
                    </template>
                    <span v-if="trip.members.length > 6" class="flex size-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold ring-2 ring-white dark:bg-slate-800 dark:ring-slate-900">+{{ trip.members.length - 6 }}</span>
                </div>
                <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                    <component :is="trip.itemsLeft ? ListChecks : Check" class="size-4" />
                    {{ trip.itemsLeft ? t(':count to do', { count: trip.itemsLeft }) : t('list done') }}
                </span>
            </div>
        </article>
    </div>

    <div v-else class="mx-auto mt-10 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <Plane class="mx-auto size-10 text-accent" />
        <h2 class="mt-3 text-lg font-bold">{{ t('No trips planned') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t('Start one: invite the others, vote the dates, split who brings what. After the trip it becomes the album.') }}</p>
        <Link href="/trips/create" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
            <Plus class="size-4" /> {{ t('Plan a trip') }}
        </Link>
    </div>

    <!-- Events: lighter, below the trips -->
    <section class="mt-10">
        <h2 class="mb-1 flex items-center gap-2 text-xl font-extrabold"><PartyPopper class="size-5 text-fuchsia-600" /> {{ t('Events') }}</h2>
        <p class="mb-3 text-sm text-slate-500">{{ t('Dinners, parties, nights out: who\'s in, a vote if needed, a reminder the day before.') }}</p>
        <ul v-if="events.length" class="space-y-2">
            <li v-for="event in events" :key="event.id">
                <Link :href="`/trips/${event.id}`" class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:ring-fuchsia-300 dark:bg-slate-900 dark:ring-slate-800 dark:hover:ring-fuchsia-800">
                    <span v-if="event.dateFrom" class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-fuchsia-50 py-1.5 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-300">
                        <span class="text-[10px] font-bold uppercase">{{ formatDate(event.dateFrom, { month: 'short' }) }}</span>
                        <span class="text-xl leading-none font-extrabold">{{ formatDate(event.dateFrom, { day: 'numeric' }) }}</span>
                    </span>
                    <span v-else class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300"><Vote class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold">{{ event.title }}</span>
                        <span class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                            <span v-if="event.status === 'today'" class="font-bold text-fuchsia-600">{{ t('Today') }}</span>
                            <span v-else-if="event.dateFrom" class="capitalize">{{ formatDate(event.dateFrom, { weekday: 'long' }) }}</span>
                            <span v-else>{{ t(':count date options to vote', { count: event.optionCount }) }}</span>
                            <span v-if="event.time" class="inline-flex items-center gap-0.5"><Clock class="size-3" /> {{ event.time }}</span>
                            <span v-if="event.destination" class="inline-flex min-w-0 items-center gap-0.5 truncate"><MapPin class="size-3 shrink-0" /> {{ event.destination }}</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-col items-end gap-1 text-xs">
                        <span v-if="event.myRsvp" class="rounded-full px-2 py-0.5 font-semibold" :class="rsvpClass[event.myRsvp]">{{ t(rsvpLabel[event.myRsvp]) }}</span>
                        <span v-else class="rounded-full bg-fuchsia-100 px-2 py-0.5 font-semibold text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-300">{{ t('Answer') }}</span>
                        <span class="inline-flex items-center gap-1 text-slate-500"><Users class="size-3" /> {{ event.goingCount }}/{{ event.members.length }}</span>
                    </span>
                </Link>
            </li>
        </ul>
        <p v-else class="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-800">
            {{ t('No events coming up.') }}
            <Link href="/trips/create?kind=event" class="font-semibold text-accent hover:underline">{{ t('Organize one') }}</Link>
        </p>
        <details v-if="pastEvents.length" class="mt-3 text-sm">
            <summary class="cursor-pointer text-slate-500">{{ t('Past events') }}</summary>
            <ul class="mt-2 space-y-1">
                <li v-for="event in pastEvents" :key="event.id">
                    <Link :href="`/trips/${event.id}`" class="flex items-center gap-2 rounded-xl px-3 py-1.5 hover:bg-white dark:hover:bg-slate-900">
                        <span class="text-slate-500">{{ formatDate(event.dateFrom, { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                        <span class="truncate font-medium">{{ event.title }}</span>
                    </Link>
                </li>
            </ul>
        </details>
    </section>

    <section v-if="done.length" class="mt-10">
        <h2 class="mb-3 text-lg font-bold">{{ t('Done, now albums') }}</h2>
        <ul class="space-y-1">
            <li v-for="trip in done" :key="trip.id">
                <Link :href="`/listings/${trip.albumId}`" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-white dark:hover:bg-slate-900">
                    <Images class="size-4 shrink-0 text-accent" />
                    <span class="truncate"><strong>{{ trip.title }}</strong><span v-if="trip.dates" class="text-slate-500"> · {{ trip.dates }}</span></span>
                </Link>
            </li>
        </ul>
    </section>
</template>

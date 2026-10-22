<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, Check, Images, ListChecks, MapPin, Plane, Plus, Vote } from 'lucide-vue-next';
import Countdown from '../../components/Countdown.vue';

defineProps({ trips: Array, done: Array });

const statusLabel = { planning: 'Voting the dates', upcoming: 'Upcoming', ongoing: 'Happening now', finished: 'Back home: make it an album' };
const statusClass = {
    planning: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    upcoming: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    ongoing: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    finished: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
};
</script>

<template>
    <Head title="Trips" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">Trips</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Plan the next one together: dates, who brings what, shared expenses.</p>
        </div>
        <Link href="/trips/create" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark sm:ml-auto sm:py-2">
            <Plus class="size-4" /> Plan a trip
        </Link>
    </div>

    <div v-if="trips.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="trip in trips"
            :key="trip.id"
            class="group relative flex flex-col gap-3 overflow-hidden rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800"
        >
            <span class="w-fit rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass[trip.status]">{{ statusLabel[trip.status] }}</span>
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
                <template v-else><Vote class="size-4 shrink-0 text-violet-600" /> {{ trip.optionCount }} date options to vote</template>
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
                    {{ trip.itemsLeft ? `${trip.itemsLeft} to do` : 'list done' }}
                </span>
            </div>
        </article>
    </div>

    <div v-else class="mx-auto mt-10 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <Plane class="mx-auto size-10 text-accent" />
        <h2 class="mt-3 text-lg font-bold">No trips planned</h2>
        <p class="mt-1 text-sm text-slate-500">Start one: invite the others, vote the dates, split who brings what. After the trip it becomes the album.</p>
        <Link href="/trips/create" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
            <Plus class="size-4" /> Plan a trip
        </Link>
    </div>

    <section v-if="done.length" class="mt-10">
        <h2 class="mb-3 text-lg font-bold">Done, now albums</h2>
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

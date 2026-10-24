<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Dumbbell, ListChecks, Plus, Repeat, Trophy } from 'lucide-vue-next';
import { formatDate } from '../../time.js';
import { focusLabel, total } from '../../gym.js';
import Pagination from '../../components/Pagination.vue';

defineProps({ workouts: Object, done: Array, stats: Object, today: String });
const isAdmin = usePage().props.auth.user?.isAdmin;

// A new workout for today: empty, or with the exercises, sets and reps of another one
const starting = ref(false);
function newWorkout(from = null) {
    starting.value = true;
    router.post('/gym/workouts', from ? { from } : {}, { onFinish: () => (starting.value = false) });
}

const cardClass = 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
</script>

<template>
    <Head :title="t('Gym')" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('Gym') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('Your workouts, the weights you lift and your records.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2 sm:ml-auto">
            <Link v-if="isAdmin" href="/gym/exercises" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 transition hover:bg-slate-100 sm:flex-none sm:py-2 dark:ring-slate-700 dark:hover:bg-slate-800">
                <ListChecks class="size-4" /> {{ t('Exercises') }}
            </Link>
            <Link href="/gym/records" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 transition hover:bg-slate-100 sm:flex-none sm:py-2 dark:ring-slate-700 dark:hover:bg-slate-800">
                <Trophy class="size-4" /> {{ t('Records') }}
            </Link>
            <button
                type="button"
                :disabled="starting"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-60 sm:flex-none sm:py-2"
                @click="newWorkout()"
            >
                <Plus class="size-4" /> {{ t('New workout') }}
            </button>
        </div>
    </div>

    <template v-if="workouts.data.length">
        <div class="mb-6 grid grid-cols-3 gap-3 sm:max-w-xl">
            <div :class="cardClass" class="p-4">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('This month') }}</p>
                <p class="mt-1 text-2xl font-extrabold">{{ stats.month }}</p>
                <p class="text-xs text-slate-500">{{ tc('workout|workouts', stats.month) }}</p>
            </div>
            <div :class="cardClass" class="p-4">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('This year') }}</p>
                <p class="mt-1 text-2xl font-extrabold">{{ stats.year }}</p>
                <p class="text-xs text-slate-500">{{ tc('workout|workouts', stats.year) }}</p>
            </div>
            <div :class="cardClass" class="p-4" :title="t('Weight × reps of every set this month')">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('Lifted') }}</p>
                <p class="mt-1 truncate text-2xl font-extrabold">{{ total(stats.monthVolume) }}</p>
                <p class="text-xs text-slate-500">{{ t('this month') }}</p>
            </div>
        </div>

        <!-- Progress: the exercises done most -->
        <section v-if="done.length" class="mb-6">
            <h2 class="mb-2 text-sm font-semibold text-slate-500">{{ t('Your progress') }}</h2>
            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
                <Link
                    v-for="exercise in done"
                    :key="exercise.id"
                    :href="`/gym/exercises/${exercise.id}`"
                    class="flex shrink-0 items-center gap-2 rounded-xl bg-white py-1.5 pr-3 pl-1.5 text-sm font-medium shadow-sm ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-900 dark:ring-slate-800"
                >
                    <img v-if="exercise.photos.length" :src="exercise.photos[0]" alt="" class="size-8 rounded-lg bg-white object-cover" />
                    <Dumbbell v-else class="m-1.5 size-5 text-accent" />
                    {{ exercise.label }}
                </Link>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="workout in workouts.data" :key="workout.id" :class="cardClass" class="group relative flex flex-col gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-start gap-3">
                    <span class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-teal-50 py-1.5 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                        <span class="text-[10px] font-bold uppercase">{{ formatDate(workout.date, { month: 'short' }) }}</span>
                        <span class="text-xl leading-none font-extrabold">{{ formatDate(workout.date, { day: 'numeric' }) }}</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold capitalize group-hover:text-accent">
                            <!-- The whole card is the link -->
                            <Link :href="`/gym/workouts/${workout.id}`" class="after:absolute after:inset-0">{{ formatDate(workout.date, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</Link>
                        </h2>
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ workout.exercises.length ? workout.exercises.join(' · ') : t('No exercises yet') }}
                        </p>
                    </div>
                </div>
                <p v-if="workout.notes" class="line-clamp-2 text-xs text-slate-500 italic">{{ workout.notes }}</p>
                <p class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span v-if="workout.focus" class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ focusLabel(workout.focus) }}</span>
                    <span>{{ tc(':count set|:count sets', workout.sets) }}</span>
                    <span v-if="workout.volume" :title="t('Weight × reps of every set')">{{ total(workout.volume) }}</span>
                    <span v-if="workout.records" class="font-semibold text-amber-600 dark:text-amber-400">🏆 {{ tc(':count record|:count records', workout.records) }}</span>
                    <!-- Above the card's link -->
                    <button
                        v-if="workout.date !== today && workout.exercises.length"
                        type="button"
                        :disabled="starting"
                        :title="t('A new workout for today with these exercises, sets and reps')"
                        class="relative z-10 ml-auto inline-flex items-center gap-1 rounded-lg px-2 py-1 font-semibold text-accent hover:bg-teal-50 disabled:opacity-60 dark:hover:bg-slate-800"
                        @click="newWorkout(workout.id)"
                    >
                        <Repeat class="size-3.5" /> {{ t('Repeat today') }}
                    </button>
                </p>
            </article>
        </div>

        <div class="mt-6"><Pagination :links="workouts.links" /></div>
    </template>

    <div v-else class="mx-auto mt-10 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <Dumbbell class="mx-auto size-10 text-teal-600" />
        <h2 class="mt-3 text-lg font-bold">{{ t('No workouts yet') }}</h2>
        <p class="mt-1 text-sm text-slate-500">
            {{ t('At the gym, start a workout, pick the exercises and write the weight and reps of each set. TheBoise remembers them, shows your progress and celebrates your records.') }}
        </p>
        <button type="button" :disabled="starting" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-60" @click="newWorkout">
            <Plus class="size-4" /> {{ t('Start the first workout') }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Camera, Dumbbell, Pencil } from 'lucide-vue-next';
import { formatDate } from '../../time.js';
import { groupLabel, kg, rowText } from '../../gym.js';
import GymChart from '../../components/GymChart.vue';

// One exercise: how it's done, records, progress over time and every workout with it
const props = defineProps({ exercise: Object, days: Array, chart: Array, records: Object });
const isAdmin = usePage().props.auth.user?.isAdmin;

const longDate = (date) => formatDate(date, { day: 'numeric', month: 'long', year: 'numeric' });

// Admins: a photo of how it's done
const uploading = ref(false);
function uploadPhoto(event) {
    const photo = event.target.files[0];
    if (!photo) return;
    uploading.value = true;
    router.post(`/gym/exercises/${props.exercise.id}/photo`, { photo }, { forceFormData: true, preserveScroll: true, onFinish: () => (uploading.value = false) });
}

const cardClass = 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
</script>

<template>
    <Head :title="exercise.label" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-4 flex items-center gap-2">
            <Link href="/gym" class="-ml-2 rounded-full p-2 hover:bg-slate-200/70 dark:hover:bg-slate-800" :aria-label="t('Back to the gym')"><ArrowLeft class="size-5" /></Link>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-2xl font-extrabold tracking-tight">{{ exercise.label }}</h1>
                <p class="text-sm text-slate-500">{{ groupLabel(exercise.group) }}<template v-if="exercise.defaultReps"> · {{ exercise.defaultSets ?? 3 }} × {{ exercise.defaultReps }}</template></p>
            </div>
            <Link v-if="isAdmin" :href="`/gym/exercises?edit=${exercise.id}`" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800">
                <Pencil class="size-4" /> {{ t('Edit') }}
            </Link>
        </div>

        <!-- How it's done -->
        <section v-if="exercise.photos.length" class="mb-6 grid gap-3" :class="exercise.photos.length > 1 && 'grid-cols-2'">
            <figure v-for="(photo, index) in exercise.photos" :key="photo" :class="cardClass" class="overflow-hidden">
                <img :src="photo" alt="" class="aspect-[4/3] w-full bg-white object-contain" />
                <figcaption v-if="exercise.photos.length > 1" class="py-1.5 text-center text-xs font-semibold text-slate-500">{{ index === 0 ? t('Start') : t('End') }}</figcaption>
            </figure>
        </section>
        <label v-if="isAdmin" class="mb-6 inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800" :class="uploading && 'opacity-60'">
            <Camera class="size-4" /> {{ exercise.photos.length ? t('Change photo') : t('Add a photo of how it\'s done') }}
            <input type="file" accept="image/*" class="sr-only" :disabled="uploading" @change="uploadPhoto" />
        </label>

        <template v-if="records">
            <!-- Personal records -->
            <section class="mb-6 grid grid-cols-3 gap-2 sm:gap-3">
                <div v-if="records.heaviest" :class="cardClass" class="p-3 sm:p-4">
                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase sm:text-xs">🏆 {{ t('Heaviest') }}</p>
                    <p class="mt-1 text-lg font-extrabold sm:text-2xl">{{ kg(records.heaviest.weight) }}</p>
                    <p class="text-xs text-slate-500"><template v-if="records.heaviest.reps">× {{ tc(':count rep|:count reps', records.heaviest.reps) }} · </template>{{ longDate(records.heaviest.date) }}</p>
                </div>
                <div v-if="records.oneRepMax" :class="cardClass" class="p-3 sm:p-4" :title="t('The weight you could lift once, estimated from your sets (Epley formula)')">
                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase sm:text-xs">{{ t('Estimated 1-rep max') }}</p>
                    <p class="mt-1 text-lg font-extrabold sm:text-2xl">{{ kg(records.oneRepMax.value) }}</p>
                    <p class="text-xs text-slate-500">{{ longDate(records.oneRepMax.date) }}</p>
                </div>
                <div v-if="records.mostReps" :class="cardClass" class="p-3 sm:p-4">
                    <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase sm:text-xs">{{ t('Most reps') }}</p>
                    <p class="mt-1 text-lg font-extrabold sm:text-2xl">{{ records.mostReps.reps }}</p>
                    <p class="text-xs text-slate-500"><template v-if="records.mostReps.weight">{{ kg(records.mostReps.weight) }} · </template>{{ longDate(records.mostReps.date) }}</p>
                </div>
            </section>

            <!-- Progress -->
            <section :class="cardClass" class="mb-6 p-4">
                <h2 class="mb-3 font-bold">{{ t('Progress') }}</h2>
                <GymChart :points="chart" />
                <p v-if="chart.length === 1" class="mt-2 text-xs text-slate-500">{{ t('One workout so far: the line starts with the next one.') }}</p>
            </section>

            <!-- Every workout with it (the chart's values, as a list) -->
            <section :class="cardClass" class="p-3 sm:p-4">
                <h2 class="mb-2 font-bold">{{ t('History') }}</h2>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="day in days" :key="day.workoutId" class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4">
                        <Link :href="`/gym/workouts/${day.workoutId}`" class="w-40 shrink-0 text-sm font-semibold hover:text-accent">{{ longDate(day.date) }}</Link>
                        <span class="flex flex-wrap gap-1.5">
                            <span v-for="(row, index) in day.rows" :key="index" class="rounded-lg bg-slate-100 px-2 py-0.5 text-sm tabular-nums dark:bg-slate-800">{{ rowText(row) }}</span>
                        </span>
                    </li>
                </ul>
            </section>
        </template>

        <div v-else class="mx-auto mt-6 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <Dumbbell class="mx-auto size-10 text-teal-600" />
            <p class="mt-3 text-sm text-slate-500">{{ t('Not done yet. Add it to a workout and your progress shows up here.') }}</p>
        </div>
    </div>
</template>

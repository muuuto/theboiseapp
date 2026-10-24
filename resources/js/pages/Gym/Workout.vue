<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, CircleAlert, Copy, LoaderCircle, Plus, Repeat, Search, Trash2, X } from 'lucide-vue-next';
import { locale, t } from '../../i18n.js';
import { formatDate } from '../../time.js';
import { request } from '../../http.js';
import { autoFocus, focusLabel, focusNames, groupLabel, groupNames, kg, number, rowText } from '../../gym.js';
import ExercisePhoto from '../../components/ExercisePhoto.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import WorkoutShare from '../../components/WorkoutShare.vue';

// One workout: exercises with their rows (kg × sets × reps). Saved by itself as you type
const props = defineProps({
    workout: Object,
    today: String,
    exercises: Array, // every exercise, the same for everyone
    previousBest: Object, // exercise id → heaviest weight before this workout
    lastTime: Object, // exercise id → { date, rows } of the last workout with it (or of the one this was repeated from)
});
const page = usePage();

const list = ref([...props.exercises]);
const exerciseById = computed(() => Object.fromEntries(list.value.map((exercise) => [exercise.id, exercise])));

let nextKey = 0;
const weightText = (weight) => (weight === null || weight === undefined ? '' : locale.value === 'it' ? String(weight).replace('.', ',') : String(weight));
const newRow = (row = {}) => ({ key: nextKey++, weight: weightText(row.weight), sets: row.sets ? String(row.sets) : '', reps: row.reps ? String(row.reps) : '' });

const form = reactive({
    date: props.workout.date,
    focus: props.workout.focus, // null: from the exercises
    notes: props.workout.notes,
    exercises: props.workout.exercises.map((exercise) => ({ key: nextKey++, exerciseId: exercise.exerciseId, rows: exercise.rows.map(newRow) })),
});

const payload = () => ({
    date: form.date,
    focus: form.focus,
    notes: form.notes,
    exercises: form.exercises.map((exercise) => ({
        exerciseId: exercise.exerciseId,
        rows: exercise.rows.map((row) => ({ weight: number(row.weight), sets: parseInt(row.sets, 10) || null, reps: parseInt(row.reps, 10) || null })),
    })),
});

// Upper body, lower body, mixed: from the exercises until you pick one (tap it again: automatic)
const shownFocus = computed(() => form.focus ?? autoFocus(form.exercises.map((exercise) => exerciseById.value[exercise.exerciseId]?.group)));
const chooseFocus = (focus) => (form.focus = form.focus === focus ? null : focus);

// --- Saving: a moment after the last change, one save at a time ---
const status = ref('saved'); // saved | waiting | saving | error
const errorText = ref('');
let timer = null;
let queue = Promise.resolve();

function save({ keepalive = false } = {}) {
    clearTimeout(timer);
    timer = null;
    queue = queue.then(async () => {
        const body = payload();
        const sent = JSON.stringify(body);
        status.value = 'saving';
        try {
            await request('PUT', `/gym/workouts/${props.workout.id}`, body, { keepalive });
            if (JSON.stringify(payload()) === sent) status.value = 'saved';
            else if (!timer) save();
        } catch (error) {
            status.value = 'error';
            errorText.value = error.status === 422 ? t('Not saved: check the numbers') : t('Not saved: no connection?');
        }
    });
    return queue;
}

watch(() => JSON.stringify(payload()), () => {
    status.value = 'waiting';
    clearTimeout(timer);
    timer = setTimeout(save, 700);
});

// Leaving with something not saved yet: saved on the way out
const unsaved = () => timer || status.value === 'error';
const saveOnLeave = () => unsaved() && save({ keepalive: true });
onMounted(() => window.addEventListener('pagehide', saveOnLeave));
onBeforeUnmount(() => {
    window.removeEventListener('pagehide', saveOnLeave);
    saveOnLeave();
});

async function finish() {
    if (unsaved() || status.value === 'saving') await save();
    if (status.value !== 'error') router.visit('/gym');
}

function destroy() {
    if (!window.confirm(t('Delete this workout and all its sets?'))) return;
    clearTimeout(timer);
    timer = null;
    status.value = 'saved';
    router.delete(`/gym/workouts/${props.workout.id}`);
}

// --- Rows ---
// A new exercise starts with its default sets (3 when it has none) and reps; they change only
// when you change them
const firstRow = (exercise) => newRow({ sets: exercise.defaultSets ?? 3, reps: exercise.defaultReps });

function addRow(exercise) {
    const last = exercise.rows.at(-1);
    exercise.rows.push(last ? newRow({ weight: number(last.weight), sets: last.sets, reps: last.reps }) : firstRow(exerciseById.value[exercise.exerciseId] ?? {}));
    nextTick(() => document.getElementById(`weight-${exercise.rows.at(-1).key}`)?.focus());
}

function removeExercise(index) {
    const exercise = form.exercises[index];
    if (exercise.rows.some((row) => row.weight || row.reps) && !window.confirm(t('Remove :name and its sets from this workout?', { name: exerciseById.value[exercise.exerciseId]?.label }))) return;
    form.exercises.splice(index, 1);
}

// What last time looked like, as a hint in the empty boxes
const hint = (exercise, index) => {
    const rows = props.lastTime[exercise.exerciseId]?.rows ?? [];
    return rows[index] ?? rows.at(-1) ?? null;
};

// 🏆 on the row that beat the record: the heaviest one (the first if tied), when it's heavier
// than ever before this workout
const recordRow = (exercise) => {
    const best = props.previousBest[exercise.exerciseId];
    if (best === undefined || best === null) return null;
    let record = null;
    for (const row of exercise.rows) {
        const weight = number(row.weight);
        if (weight !== null && weight > best && (!record || weight > number(record.weight))) record = row;
    }
    return record;
};
const isRecord = (exercise, row) => recordRow(exercise) === row;

// --- Repeat, share ---
const canRepeat = computed(() => form.date !== props.today && form.exercises.length > 0);
const repeating = ref(false);
async function repeatToday() {
    repeating.value = true;
    if (unsaved() || status.value === 'saving') await save();
    router.post('/gym/workouts', { from: props.workout.id }, { onFinish: () => (repeating.value = false) });
}

const shareExercises = computed(() => form.exercises.map((exercise) => ({
    label: exerciseById.value[exercise.exerciseId]?.label ?? '',
    rows: payload().exercises.find((sent) => sent.exerciseId === exercise.exerciseId)?.rows ?? [],
    record: !!recordRow(exercise),
})));

// --- Picking an exercise ---
const picking = ref(false);
const search = ref('');
const group = ref(null);
const searchInput = ref(null);

const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const shown = computed(() => {
    const query = plain(search.value.trim());
    return list.value.filter((exercise) => (!group.value || exercise.group === group.value) && (!query || plain(exercise.label).includes(query)));
});
const shownGroups = computed(() => {
    const byGroup = {};
    for (const exercise of shown.value) (byGroup[exercise.group ?? ''] ??= []).push(exercise);
    return Object.entries(byGroup).map(([name, exercises]) => ({ name, exercises }));
});
const inWorkout = computed(() => new Set(form.exercises.map((exercise) => exercise.exerciseId)));

function openPicker() {
    search.value = '';
    group.value = null;
    picking.value = true;
    nextTick(() => searchInput.value?.focus());
}

function pick(exercise) {
    form.exercises.push({ key: nextKey++, exerciseId: exercise.id, rows: [firstRow(exercise)] });
    picking.value = false;
    nextTick(() => document.getElementById(`exercise-${form.exercises.at(-1).key}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

// A new exercise (everyone sees it)
const own = reactive({ open: false, name: '', group: '', photo: null, sending: false, error: '' });
function openOwn() {
    Object.assign(own, { open: true, name: search.value.trim(), group: group.value ?? '', photo: null, error: '' });
}
async function addOwn() {
    const data = new FormData();
    data.append('name', own.name);
    if (own.group) data.append('group', own.group);
    if (own.photo) data.append('photo', own.photo);
    own.sending = true;
    try {
        const exercise = await request('POST', '/gym/exercises', data);
        if (!exerciseById.value[exercise.id]) list.value.push(exercise);
        own.open = false;
        pick(exercise);
    } catch (error) {
        own.error = Object.values(error.errors ?? {})[0]?.[0] ?? t('Something went wrong, try again.');
    } finally {
        own.sending = false;
    }
}

const closeOnEscape = (event) => event.key === 'Escape' && (picking.value = false);
watch(picking, (on) => (on ? window.addEventListener('keydown', closeOnEscape) : window.removeEventListener('keydown', closeOnEscape)));
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));

const cardClass = 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-12 w-full rounded-xl border-0 bg-slate-50 px-2 text-center text-lg font-semibold ring-1 ring-slate-200 placeholder:font-normal placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700 dark:placeholder:text-slate-600';
</script>

<template>
    <Head :title="t('Workout')" />

    <div class="mx-auto max-w-2xl">
        <!-- Top: back, the day, saving -->
        <div class="mb-4 flex items-center gap-2">
            <button type="button" class="-ml-2 rounded-full p-2 hover:bg-slate-200/70 dark:hover:bg-slate-800" :aria-label="t('Back to the gym')" @click="finish">
                <ArrowLeft class="size-5" />
            </button>
            <h1 class="min-w-0 flex-1 text-xl leading-tight font-extrabold tracking-tight capitalize sm:text-2xl">{{ formatDate(form.date, { weekday: 'long', day: 'numeric', month: 'long' }) }}</h1>
            <span class="flex shrink-0 items-center gap-1 text-xs font-medium" :class="status === 'error' ? 'text-red-600' : 'text-slate-500'" aria-live="polite">
                <template v-if="status === 'saved'"><Check class="size-4 text-teal-600" /> {{ t('Saved') }}</template>
                <template v-else-if="status === 'error'">
                    <CircleAlert class="size-4" /> {{ errorText }}
                    <button type="button" class="ml-1 rounded-lg px-2 py-1 font-semibold ring-1 ring-red-200 hover:bg-red-50 dark:ring-red-900 dark:hover:bg-red-950" @click="save()">{{ t('Try again') }}</button>
                </template>
                <template v-else><LoaderCircle class="size-4 animate-spin" /> {{ t('Saving…') }}</template>
            </span>
        </div>

        <label class="mb-4 flex items-center gap-3 text-sm">
            <span class="font-semibold">{{ t('Date') }}<RequiredMark /></span>
            <input v-model="form.date" type="date" required class="h-10 rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
        </label>

        <div class="mb-4 flex flex-wrap items-center gap-1.5" role="group" :aria-label="t('Focus')">
            <button
                v-for="focus in focusNames"
                :key="focus"
                type="button"
                :aria-pressed="shownFocus === focus"
                class="rounded-full px-3 py-1.5 text-sm font-semibold ring-1 transition"
                :class="shownFocus === focus ? 'bg-accent text-white ring-accent' : 'ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800'"
                @click="chooseFocus(focus)"
            >
                {{ focusLabel(focus) }}
            </button>
            <span v-if="!form.focus && shownFocus" class="text-xs text-slate-500">{{ t('from the exercises') }}</span>
        </div>

        <!-- The exercises -->
        <div class="space-y-3">
            <section v-for="(exercise, index) in form.exercises" :id="`exercise-${exercise.key}`" :key="exercise.key" :class="cardClass" class="p-3 sm:p-4">
                <div class="mb-3 flex items-start gap-3">
                    <ExercisePhoto v-if="exerciseById[exercise.exerciseId]" :exercise="exerciseById[exercise.exerciseId]" size="size-14" />
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold leading-snug">
                            <Link :href="`/gym/exercises/${exercise.exerciseId}`" class="hover:text-accent">{{ exerciseById[exercise.exerciseId]?.label }}</Link>
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ groupLabel(exerciseById[exercise.exerciseId]?.group) }}
                            <template v-if="previousBest[exercise.exerciseId]"> · {{ t('Best: :weight', { weight: kg(previousBest[exercise.exerciseId]) }) }}</template>
                        </p>
                        <p v-if="lastTime[exercise.exerciseId]" class="mt-0.5 text-xs text-slate-500">
                            {{ t('Last time (:date):', { date: formatDate(lastTime[exercise.exerciseId].date, { day: 'numeric', month: 'short' }) }) }}
                            {{ lastTime[exercise.exerciseId].rows.map(rowText).join(', ') }}
                        </p>
                    </div>
                    <button type="button" class="-mt-1 -mr-1 rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-red-600 dark:hover:bg-slate-800" :aria-label="t('Remove :name', { name: exerciseById[exercise.exerciseId]?.label })" @click="removeExercise(index)">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="grid grid-cols-[1.5rem_1fr_1fr_1fr_2.5rem] items-center gap-x-2 gap-y-2">
                    <span />
                    <span class="text-center text-xs font-semibold text-slate-500">kg</span>
                    <span class="text-center text-xs font-semibold text-slate-500">{{ t('Sets') }}</span>
                    <span class="text-center text-xs font-semibold text-slate-500">{{ t('Reps') }}</span>
                    <span />
                    <template v-for="(row, rowIndex) in exercise.rows" :key="row.key">
                        <span class="text-center text-sm font-bold text-slate-400">{{ isRecord(exercise, row) ? '🏆' : rowIndex + 1 }}</span>
                        <input
                            :id="`weight-${row.key}`"
                            v-model="row.weight"
                            type="text"
                            inputmode="decimal"
                            enterkeyhint="next"
                            :aria-label="t('Row :number, kg', { number: rowIndex + 1 })"
                            :placeholder="hint(exercise, rowIndex)?.weight ? weightText(hint(exercise, rowIndex).weight) : '–'"
                            :class="[inputClass, isRecord(exercise, row) && 'ring-2 ring-amber-400 dark:ring-amber-500']"
                        />
                        <input
                            v-model="row.sets"
                            type="text"
                            inputmode="numeric"
                            enterkeyhint="next"
                            :aria-label="t('Row :number, sets', { number: rowIndex + 1 })"
                            placeholder="1"
                            :class="inputClass"
                        />
                        <input
                            v-model="row.reps"
                            type="text"
                            inputmode="numeric"
                            enterkeyhint="done"
                            :aria-label="t('Row :number, reps per set', { number: rowIndex + 1 })"
                            :placeholder="hint(exercise, rowIndex)?.reps ? String(hint(exercise, rowIndex).reps) : ''"
                            :class="inputClass"
                        />
                        <button type="button" class="flex size-10 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-red-600 dark:hover:bg-slate-800" :aria-label="t('Remove row :number', { number: rowIndex + 1 })" @click="exercise.rows.splice(rowIndex, 1)">
                            <Trash2 class="size-4" />
                        </button>
                    </template>
                </div>
                <p v-if="recordRow(exercise)" class="mt-2 text-sm font-semibold text-amber-600 dark:text-amber-400">🏆 {{ t('New record!') }}</p>

                <button type="button" class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="addRow(exercise)">
                    <component :is="exercise.rows.length ? Copy : Plus" class="size-4" /> {{ exercise.rows.length ? t('Add a row like the last one') : t('Add a row') }}
                </button>
            </section>
        </div>

        <p v-if="!form.exercises.length" class="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800">
            {{ t('Add the first exercise, then the weight, the sets and the reps of each set.') }}
        </p>

        <button type="button" class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark" @click="openPicker">
            <Plus class="size-4" /> {{ t('Add exercise') }}
        </button>

        <label class="mt-6 block">
            <span class="mb-1.5 block text-sm font-semibold">{{ t('Notes') }}</span>
            <textarea
                v-model="form.notes"
                rows="3"
                maxlength="2000"
                :placeholder="t('How did it go? Pain, energy, what to try next time…')"
                class="w-full rounded-xl border-0 bg-white px-3 py-2 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700"
            />
        </label>
        <p class="mt-1 text-xs text-slate-500">{{ t('Leave kg empty for bodyweight exercises. A different weight or number of reps: add a row.') }}</p>

        <!-- Phones: Done first and full width, Repeat and Share side by side, Delete last.
             Wider: one row, Delete on the left and Done on the right -->
        <div class="mt-6 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
            <button type="button" class="order-last col-span-2 inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 ring-1 ring-slate-200 hover:bg-red-50 sm:order-none dark:ring-slate-700 dark:hover:bg-red-950/40" @click="destroy">
                <Trash2 class="size-4" /> {{ t('Delete workout') }}
            </button>
            <button
                v-if="canRepeat"
                type="button"
                :disabled="repeating"
                :title="t('A new workout for today with these exercises, sets and reps')"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-60 dark:ring-slate-700 dark:hover:bg-slate-800"
                @click="repeatToday"
            >
                <Repeat class="size-4" /> {{ t('Repeat today') }}
            </button>
            <div :class="!canRepeat && 'col-span-2'">
                <WorkoutShare :date="form.date" :name="page.props.auth.user?.name" :focus="shownFocus" :exercises="shareExercises" />
            </div>
            <button type="button" class="order-first col-span-2 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700 sm:order-none sm:ml-auto sm:py-2.5 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200" @click="finish">
                <Check class="size-4" /> {{ t('Done') }}
            </button>
        </div>
    </div>

    <!-- Exercise picker: full screen on phones -->
    <Teleport to="body">
        <div v-if="picking" class="fixed inset-0 z-40 flex items-end justify-center bg-slate-950/60 sm:items-center sm:p-4" @click.self="picking = false">
            <div role="dialog" aria-modal="true" :aria-label="t('Add exercise')" class="flex h-[92dvh] w-full max-w-2xl flex-col rounded-t-3xl bg-white shadow-2xl sm:h-auto sm:max-h-[85vh] sm:rounded-3xl dark:bg-slate-900">
                <div class="space-y-3 border-b border-slate-100 p-4 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <h2 class="flex-1 text-lg font-bold">{{ t('Add exercise') }}</h2>
                        <button type="button" class="rounded-full p-2 hover:bg-slate-100 dark:hover:bg-slate-800" :aria-label="t('Close')" @click="picking = false"><X class="size-5" /></button>
                    </div>
                    <label class="relative block">
                        <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input ref="searchInput" v-model="search" type="search" :placeholder="t('Search exercises')" class="h-11 w-full rounded-xl border-0 bg-slate-50 pr-3 pl-9 ring-1 ring-slate-200 focus:bg-white focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                    </label>
                    <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-0.5">
                        <button
                            v-for="choice in [null, ...groupNames]"
                            :key="choice ?? 'all'"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ring-1 transition"
                            :class="group === choice ? 'bg-accent text-white ring-accent' : 'ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800'"
                            @click="group = choice"
                        >
                            {{ choice ? groupLabel(choice) : t('All') }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <div v-for="section in shownGroups" :key="section.name" class="mb-4">
                        <h3 class="mb-1 text-xs font-bold tracking-wide text-slate-500 uppercase">{{ groupLabel(section.name || null) }}</h3>
                        <ul>
                            <li v-for="exercise in section.exercises" :key="exercise.id" class="flex items-center gap-3 rounded-xl px-1 py-1 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <ExercisePhoto :exercise="exercise" size="size-14" />
                                <button type="button" class="flex min-h-14 min-w-0 flex-1 items-center gap-2 text-left disabled:opacity-50" :disabled="inWorkout.has(exercise.id)" @click="pick(exercise)">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium">{{ exercise.label }}</span>
                                        <span v-if="exercise.defaultReps" class="text-xs text-slate-500">{{ exercise.defaultSets ?? 3 }} × {{ exercise.defaultReps }}</span>
                                    </span>
                                    <Check v-if="inWorkout.has(exercise.id)" class="size-5 shrink-0 text-teal-600" />
                                    <Plus v-else class="size-5 shrink-0 text-accent" />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <p v-if="!shown.length" class="py-6 text-center text-sm text-slate-500">{{ t('No exercise called ":name".', { name: search.trim() }) }}</p>

                    <!-- A new one -->
                    <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-950">
                        <button v-if="!own.open" type="button" class="inline-flex w-full items-center justify-center gap-1.5 py-1.5 text-sm font-semibold text-accent" @click="openOwn">
                            <Plus class="size-4" /> {{ search.trim() && !shown.length ? t('Add ":name" as a new exercise', { name: search.trim() }) : t('Add a new exercise') }}
                        </button>
                        <form v-else class="space-y-3" @submit.prevent="addOwn">
                            <p class="text-xs text-slate-500">{{ t('Everyone in the gym will find it in the list.') }}</p>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Name') }}<RequiredMark /></span>
                                <input v-model="own.name" required maxlength="100" aria-required="true" class="h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700" />
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold">{{ t('Muscle group') }}</span>
                                    <select v-model="own.group" class="h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700">
                                        <option value="">{{ t('Other') }}</option>
                                        <option v-for="name in groupNames" :key="name" :value="name">{{ groupLabel(name) }}</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold">{{ t('Photo') }}</span>
                                    <input type="file" accept="image/*" class="block w-full text-sm file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold file:ring-1 file:ring-slate-200 dark:file:bg-slate-900 dark:file:ring-slate-700" @change="own.photo = $event.target.files[0] ?? null" />
                                </label>
                            </div>
                            <p v-if="own.error" class="text-sm text-red-600">{{ own.error }}</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="own.open = false">{{ t('Cancel') }}</button>
                                <button type="submit" :disabled="own.sending || !own.name.trim()" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-60">{{ t('Add') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

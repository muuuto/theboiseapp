<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Camera, ImageOff, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { t, tc } from '../../i18n.js';
import { groupLabel, groupNames } from '../../gym.js';
import ExercisePhoto from '../../components/ExercisePhoto.vue';
import RequiredMark from '../../components/RequiredMark.vue';

// Admins: the exercises everyone picks from. Names in English and Italian, muscle group, photo,
// and the sets and reps a new workout starts with
const props = defineProps({ exercises: Array });

const search = ref('');
const group = ref(null);
const plain = (text) => (text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const shownGroups = computed(() => {
    const query = plain(search.value.trim());
    const byGroup = {};
    for (const exercise of props.exercises) {
        if (group.value && exercise.group !== group.value) continue;
        if (query && !plain(exercise.name).includes(query) && !plain(exercise.nameIt).includes(query)) continue;
        (byGroup[exercise.group ?? ''] ??= []).push(exercise);
    }
    return Object.entries(byGroup).map(([name, exercises]) => ({ name, exercises }));
});

// --- Editing one (or a new one: id null) ---
const editing = ref(undefined); // undefined: none, null: a new one, otherwise its id
const form = useForm({ name: '', nameIt: '', group: '', defaultSets: '', defaultReps: '', photo: null });

function edit(exercise) {
    form.clearErrors();
    form.defaults({
        name: exercise?.name ?? search.value.trim(),
        nameIt: exercise?.nameIt ?? '',
        group: exercise?.group ?? group.value ?? '',
        defaultSets: exercise?.defaultSets ?? '',
        defaultReps: exercise?.defaultReps ?? '',
        photo: null,
    });
    form.reset();
    editing.value = exercise ? exercise.id : null;
    nextTick(() => document.getElementById(`edit-${exercise?.id ?? 'new'}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}
const close = () => (editing.value = undefined);

function submit() {
    const data = (values) => ({ ...values, group: values.group || null, defaultSets: values.defaultSets || null, defaultReps: values.defaultReps || null });
    if (editing.value === null) {
        form.transform((values) => data(values)).post('/gym/exercises', { forceFormData: true, preserveScroll: true, onSuccess: close });
    } else {
        form.transform(({ photo, ...values }) => data(values)).put(`/gym/exercises/${editing.value}`, { preserveScroll: true, onSuccess: close });
    }
}

const busy = ref(false);
const done = { preserveScroll: true, onFinish: () => (busy.value = false) };
function uploadPhoto(exercise, event) {
    const photo = event.target.files[0];
    if (!photo) return;
    busy.value = true;
    router.post(`/gym/exercises/${exercise.id}/photo`, { photo }, { ...done, forceFormData: true });
}
function removePhoto(exercise) {
    busy.value = true;
    router.delete(`/gym/exercises/${exercise.id}/photo`, done);
}
function destroy(exercise) {
    if (!window.confirm(t('Delete ":name"?', { name: exercise.name }))) return;
    busy.value = true;
    router.delete(`/gym/exercises/${exercise.id}`, { ...done, onSuccess: close });
}

// From an exercise's page (?edit=12): that one open
onMounted(() => {
    const id = Number(new URLSearchParams(window.location.search).get('edit'));
    const exercise = props.exercises.find((one) => one.id === id);
    if (exercise) edit(exercise);
});

const cardClass = 'rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
</script>

<template>
    <Head :title="t('Exercises')" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-4 flex items-center gap-2">
            <Link href="/gym" class="-ml-2 rounded-full p-2 hover:bg-slate-200/70 dark:hover:bg-slate-800" :aria-label="t('Back to the gym')"><ArrowLeft class="size-5" /></Link>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ t('Exercises') }}</h1>
                <p class="text-sm text-slate-500">{{ t('The same for everyone in the gym. The default sets and reps fill in a new row; empty: 3 sets.') }}</p>
            </div>
        </div>

        <div class="mb-4 space-y-3">
            <div class="flex gap-2">
                <label class="relative block flex-1">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" :placeholder="t('Search exercises')" :class="inputClass" class="pl-9" />
                </label>
                <button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark" @click="edit(null)">
                    <Plus class="size-4" /> {{ t('Add') }}
                </button>
            </div>
            <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-0.5 sm:mx-0 sm:flex-wrap sm:px-0">
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

        <!-- The form: for a new one at the top, otherwise under its exercise -->
        <template v-if="editing === null">
            <form id="edit-new" :class="cardClass" class="mb-4 space-y-3 p-4" @submit.prevent="submit">
                <h2 class="font-bold">{{ t('New exercise') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Name in English') }}<RequiredMark /></span>
                        <input v-model="form.name" required maxlength="100" aria-required="true" :class="inputClass" />
                        <span v-if="form.errors.name" class="mt-1 block text-sm text-red-600">{{ form.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Name in Italian') }}</span>
                        <input v-model="form.nameIt" maxlength="100" :placeholder="form.name" :class="inputClass" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Muscle group') }}</span>
                        <select v-model="form.group" :class="inputClass">
                            <option value="">{{ t('Other') }}</option>
                            <option v-for="name in groupNames" :key="name" :value="name">{{ groupLabel(name) }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Photo') }}</span>
                        <input type="file" accept="image/*" class="block w-full text-sm file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold file:ring-1 file:ring-slate-200 dark:file:bg-slate-900 dark:file:ring-slate-700" @change="form.photo = $event.target.files[0] ?? null" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Default sets') }}</span>
                        <input v-model="form.defaultSets" type="number" min="1" max="20" inputmode="numeric" placeholder="3" :class="inputClass" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Default reps per set') }}</span>
                        <input v-model="form.defaultReps" type="number" min="1" max="100" inputmode="numeric" :class="inputClass" />
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800" @click="close">{{ t('Cancel') }}</button>
                    <button type="submit" :disabled="form.processing || !form.name.trim()" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-60">{{ t('Add') }}</button>
                </div>
            </form>
        </template>

        <div v-for="section in shownGroups" :key="section.name" class="mb-5">
            <h2 class="mb-2 text-xs font-bold tracking-wide text-slate-500 uppercase">{{ groupLabel(section.name || null) }}</h2>
            <ul :class="cardClass" class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="exercise in section.exercises" :key="exercise.id" class="p-3">
                    <div class="flex items-center gap-3">
                        <ExercisePhoto :exercise="exercise" size="size-12" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold leading-snug">
                                <Link :href="`/gym/exercises/${exercise.id}`" class="hover:text-accent">{{ exercise.name }}</Link>
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                <span :class="!exercise.nameIt && 'text-amber-600 dark:text-amber-400'">🇮🇹 {{ exercise.nameIt || t('no Italian name') }}</span>
                                · {{ exercise.defaultReps ? `${exercise.defaultSets ?? 3} × ${exercise.defaultReps}` : tc(':count set|:count sets', exercise.defaultSets ?? 3) }}
                                · {{ tc('in :count workout|in :count workouts', exercise.uses) }}
                                <template v-if="exercise.addedBy"> · {{ t('added by :name', { name: exercise.addedBy }) }}</template>
                            </p>
                        </div>
                        <button v-if="editing !== exercise.id" type="button" class="shrink-0 rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-accent dark:hover:bg-slate-800" :aria-label="t('Edit :name', { name: exercise.name })" @click="edit(exercise)">
                            <Pencil class="size-4" />
                        </button>
                    </div>

                    <form v-if="editing === exercise.id" :id="`edit-${exercise.id}`" class="mt-3 space-y-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-950" @submit.prevent="submit">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Name in English') }}<RequiredMark /></span>
                                <input v-model="form.name" required maxlength="100" aria-required="true" :class="inputClass" />
                                <span v-if="form.errors.name" class="mt-1 block text-sm text-red-600">{{ form.errors.name }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Name in Italian') }}</span>
                                <input v-model="form.nameIt" maxlength="100" :placeholder="form.name" :class="inputClass" />
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Muscle group') }}</span>
                                <select v-model="form.group" :class="inputClass">
                                    <option value="">{{ t('Other') }}</option>
                                    <option v-for="name in groupNames" :key="name" :value="name">{{ groupLabel(name) }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Default sets') }}</span>
                                <input v-model="form.defaultSets" type="number" min="1" max="20" inputmode="numeric" placeholder="3" :class="inputClass" />
                                <span v-if="form.errors.defaultSets" class="mt-1 block text-sm text-red-600">{{ form.errors.defaultSets }}</span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold">{{ t('Default reps per set') }}</span>
                                <input v-model="form.defaultReps" type="number" min="1" max="100" inputmode="numeric" :class="inputClass" />
                                <span v-if="form.errors.defaultReps" class="mt-1 block text-sm text-red-600">{{ form.errors.defaultReps }}</span>
                            </label>
                        </div>

                        <!-- The photo is saved right away, apart from the rest -->
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 font-semibold ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" :class="busy && 'opacity-60'">
                                <Camera class="size-4" /> {{ exercise.photos.length ? t('Change photo') : t('Add a photo of how it\'s done') }}
                                <input type="file" accept="image/*" class="sr-only" :disabled="busy" @change="uploadPhoto(exercise, $event)" />
                            </label>
                            <button v-if="exercise.uploadedPhoto" type="button" :disabled="busy" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 font-semibold ring-1 ring-slate-200 hover:bg-white disabled:opacity-60 dark:ring-slate-700 dark:hover:bg-slate-900" @click="removePhoto(exercise)">
                                <ImageOff class="size-4" /> {{ exercise.readyPhotos ? t('Back to the ready-made photos') : t('Remove photo') }}
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                :disabled="busy || exercise.uses > 0"
                                :title="exercise.uses > 0 ? t('It\'s in some workouts: it can\'t be deleted') : ''"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 ring-1 ring-slate-200 hover:bg-red-50 disabled:opacity-40 dark:ring-slate-700 dark:hover:bg-red-950/40"
                                @click="destroy(exercise)"
                            >
                                <Trash2 class="size-4" /> {{ t('Delete') }}
                            </button>
                            <button type="button" class="ml-auto rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="close">{{ t('Cancel') }}</button>
                            <button type="submit" :disabled="form.processing || !form.name.trim()" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-60">{{ t('Save') }}</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
        <p v-if="!shownGroups.length" class="py-6 text-center text-sm text-slate-500">{{ t('No exercise called ":name".', { name: search.trim() }) }}</p>
    </div>
</template>

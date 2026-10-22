<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Trash2, UserPlus } from 'lucide-vue-next';
import PeoplePicker from '../../components/PeoplePicker.vue';
import { t } from '../../i18n.js';

const props = defineProps({
    group: Object, // null when creating
    me: Number,
    users: Array,
    albums: Array,
});

const editing = computed(() => !!props.group);

const form = useForm({
    name: props.group?.name ?? '',
    listing_id: props.group?.listing_id ?? null,
    // You're always in your own group
    members: props.group?.members ?? [props.me],
});

const album = computed(() => props.albums.find((item) => item.id === form.listing_id));
const missingFromAlbum = computed(() => (album.value?.people ?? []).filter((id) => !form.members.includes(id)));

function chooseAlbum() {
    // A new group for a trip: its name and people come from the album
    if (!album.value) return;
    if (!form.name.trim()) form.name = album.value.title;
    if (!editing.value) addAlbumPeople();
}
function addAlbumPeople() {
    form.members = [...new Set([...form.members, ...missingFromAlbum.value])];
}

function submit() {
    const data = (values) => ({ ...values, members: [...new Set([...values.members, props.me])] });
    if (editing.value) {
        form.transform(data).put(`/expenses/${props.group.id}`, { preserveScroll: true });
    } else {
        form.transform(data).post('/expenses', { preserveScroll: true });
    }
}

function destroy() {
    if (window.confirm(t('Delete the group ":name" with all its expenses? This can\'t be undone.', { name: props.group.name }))) {
        router.delete(`/expenses/${props.group.id}`);
    }
}

const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
</script>

<template>
    <Head :title="editing ? t('Edit :title', { title: group.name }) : t('New group')" />

    <Link
        :href="editing ? `/expenses/${group.id}` : '/expenses'"
        class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800"
    >
        <ArrowLeft class="size-4" /> {{ editing ? group.name : t('Expenses') }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? t('Edit group') : t('New group') }}</h1>

    <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
        <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <label class="block">
                <span :class="labelClass">{{ t('Album (optional)') }}</span>
                <select v-model="form.listing_id" :class="inputClass" @change="chooseAlbum">
                    <option :value="null">{{ t('No album') }}</option>
                    <option v-for="item in albums" :key="item.id" :value="item.id">{{ item.title }} ({{ item.year }})</option>
                </select>
                <span v-if="form.errors.listing_id" class="mt-1 block text-sm text-red-600">{{ form.errors.listing_id }}</span>
                <span v-else class="mt-1 block text-xs text-slate-500">{{ t('The trip\'s album: the group shows a link to it, and the album to the group.') }}</span>
            </label>

            <label class="block">
                <span :class="labelClass">{{ t('Name') }}</span>
                <input v-model="form.name" maxlength="100" required :placeholder="t('Example: Sicilia 2026, Casa')" :class="[inputClass, form.errors.name && 'ring-2 ring-red-500']" />
                <span v-if="form.errors.name" class="mt-1 block text-sm text-red-600">{{ form.errors.name }}</span>
            </label>
        </section>

        <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-bold">{{ t('Members') }}</h2>
                <button
                    v-if="album && missingFromAlbum.length"
                    type="button"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-medium text-accent ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800"
                    @click="addAlbumPeople"
                >
                    <UserPlus class="size-4" /> {{ t("Add the album's people (:count)", { count: missingFromAlbum.length }) }}
                </button>
            </div>
            <p class="text-xs text-slate-500">{{ t('You\'re always in. People with expenses or payments in the group can\'t be removed.') }}</p>
            <PeoplePicker v-model="form.members" :users="users" />
            <p v-if="form.errors.members" class="text-sm text-red-600">{{ form.errors.members }}</p>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="rounded-2xl bg-brand px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ editing ? t('Save group') : t('Create group') }}
            </button>
            <template v-if="editing">
                <button
                    v-if="group.settled"
                    type="button"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                    @click="destroy"
                >
                    <Trash2 class="size-4" /> {{ t('Delete group') }}
                </button>
                <p v-else class="ml-auto text-xs text-slate-500">{{ t('The group can be deleted once everyone is settled.') }}</p>
                <p v-if="$page.props.errors.group" class="w-full text-sm text-red-600">{{ $page.props.errors.group }}</p>
            </template>
        </div>
    </form>
</template>

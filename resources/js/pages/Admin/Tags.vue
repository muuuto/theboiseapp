<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, GitMerge, Pencil, Plus, Search, Sparkles, Tags, Trash2, User, X } from 'lucide-vue-next';
import { t, tc } from '../../i18n.js';

// Admins: the album tags library. Everyone tags with the same spelling: the form offers these
// tags, other spellings ("Matte") are turned into theirs ("Matteo"), and a name that isn't here
// is asked about ("did you mean…?") before becoming a new tag, marked new here
const props = defineProps({
    ready: Boolean, // false until its migration runs
    tags: Array, // [{ id, name, aliases, userId, albums, isNew }], most used first
    lookAlikes: Array, // [[id, id]]
    users: Array,
});

const keep = { preserveScroll: true, preserveState: true };
const byId = computed(() => Object.fromEntries(props.tags.map((tag) => [tag.id, tag])));
const userName = (id) => props.users.find((user) => user.id === id)?.name;
const newTags = computed(() => props.tags.filter((tag) => tag.isNew));

const search = ref('');
const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const shown = computed(() => {
    const query = plain(search.value.trim());
    return query ? props.tags.filter((tag) => [tag.name, ...tag.aliases].some((spelling) => plain(spelling).includes(query))) : props.tags;
});

// Add
const adding = useForm({ name: '' });
const add = () => adding.post('/admin/tags', { ...keep, onSuccess: () => adding.reset() });

// Edit: name, other spellings (comma separated), account
const editing = ref(null);
const editForm = useForm({ name: '', aliases: '', userId: null });
function edit(tag) {
    editing.value = tag.id;
    editForm.clearErrors();
    Object.assign(editForm, { name: tag.name, aliases: tag.aliases.join(', '), userId: tag.userId });
}
function save(tag) {
    editForm
        .transform((data) => ({ ...data, aliases: data.aliases.split(',').map((alias) => alias.trim()).filter(Boolean) }))
        .put(`/admin/tags/${tag.id}`, { ...keep, onSuccess: () => (editing.value = null) });
}
const looksFine = (tag) => router.put(`/admin/tags/${tag.id}`, { name: tag.name, aliases: tag.aliases, userId: tag.userId }, keep);

// Merge: the albums of one get the other, its spelling becomes an alias
function merge(from, into) {
    if (window.confirm(t('Merge ":from" into ":into"? Its :count albums get ":into", and ":from" becomes another spelling of it.', { from: from.name, into: into.name, count: from.albums }))) {
        router.post(`/admin/tags/${from.id}/merge`, { into: into.id }, keep);
    }
}
function pickMerge(tag, event) {
    const into = byId.value[event.target.value];
    event.target.value = ''; // back to "Merge into…" (also when the merge is cancelled)
    if (into) merge(tag, into);
}
const distinct = (pair) => router.post('/admin/tags/distinct', { ids: pair }, keep);
function destroy(tag) {
    if (window.confirm(t('Delete the tag ":name"?', { name: tag.name }))) router.delete(`/admin/tags/${tag.id}`, keep);
}

const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
const smallButton = 'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800';
</script>

<template>
    <Head :title="t('Album tags')" />

    <div class="mb-6">
        <h1 class="flex items-center gap-2 text-3xl font-extrabold tracking-tight"><Tags class="size-7 text-accent" /> {{ t('Album tags') }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            {{ t('Every tag once, always spelled the same. Whoever posts an album picks from these; another spelling (e.g. "Matte") becomes its tag, and a new name is checked against similar ones first. Renaming or merging changes every album.') }}
        </p>
    </div>

    <p v-if="!ready" :class="cardClass" class="text-sm text-slate-500">{{ t('Run the pending migrations to enable it.') }}</p>

    <div v-else class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section :class="cardClass" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-bold">{{ tc(':count tag|:count tags', tags.length) }}</h2>
                <label class="relative ml-auto block w-full sm:w-56">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="search" type="search" :placeholder="t('Search')" :class="inputClass" class="pl-9" />
                </label>
            </div>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="tag in shown" :key="tag.id" class="py-3">
                    <!-- Editing -->
                    <form v-if="editing === tag.id" class="space-y-2" @submit.prevent="save(tag)">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label class="block text-xs font-semibold">{{ t('Name') }}
                                <input v-model="editForm.name" maxlength="50" required :class="inputClass" class="mt-1" />
                            </label>
                            <label class="block text-xs font-semibold">{{ t('Account (optional)') }}
                                <select v-model="editForm.userId" :class="inputClass" class="mt-1">
                                    <option :value="null">{{ t('None (not on the site)') }}</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </label>
                        </div>
                        <label class="block text-xs font-semibold">{{ t('Other spellings, comma separated') }}
                            <input v-model="editForm.aliases" :placeholder="t('e.g. Matte, Mat')" :class="inputClass" class="mt-1" />
                        </label>
                        <p v-for="(message, key) in editForm.errors" :key="key" class="text-sm text-red-600">{{ message }}</p>
                        <p v-if="editForm.name.trim() !== tag.name && tag.albums" class="text-xs text-amber-700 dark:text-amber-400">{{ tc('Renaming changes :count album|Renaming changes :count albums', tag.albums) }}</p>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="editForm.processing" class="inline-flex items-center gap-1 rounded-xl bg-brand px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50"><Check class="size-4" /> {{ t('Save') }}</button>
                            <button type="button" class="rounded-xl px-3 py-1.5 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800" @click="editing = null">{{ t('Cancel') }}</button>
                        </div>
                    </form>

                    <!-- Showing -->
                    <div v-else class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-1.5 font-semibold">
                                {{ tag.name }}
                                <span v-if="tag.isNew" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"><Sparkles class="size-3" /> {{ t('new') }}</span>
                                <span v-if="tag.userId" class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-800 dark:bg-teal-950 dark:text-teal-300"><User class="size-3" /> {{ userName(tag.userId) }}</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                <Link v-if="tag.albums" :href="`/?tag=${encodeURIComponent(tag.name)}`" class="hover:text-accent hover:underline">{{ tc(':count album|:count albums', tag.albums) }}</Link>
                                <template v-else>{{ t('no albums') }}</template>
                                <template v-if="tag.aliases.length"> · {{ t('also: :spellings', { spellings: tag.aliases.join(', ') }) }}</template>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" :class="smallButton" @click="edit(tag)"><Pencil class="size-3.5" /> {{ t('Edit') }}</button>
                            <select value="" :aria-label="t('Merge into')" class="h-7 rounded-lg border-0 bg-transparent py-0 pr-7 pl-2 text-xs font-semibold ring-1 ring-slate-200 dark:ring-slate-700" @change="pickMerge(tag, $event)">
                                <option value="">{{ t('Merge into…') }}</option>
                                <option v-for="other in tags.filter((item) => item.id !== tag.id)" :key="other.id" :value="other.id">{{ other.name }}</option>
                            </select>
                            <button v-if="!tag.albums" type="button" :class="smallButton" class="text-red-600" :aria-label="t('Delete')" @click="destroy(tag)"><Trash2 class="size-3.5" /></button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <aside class="space-y-6">
            <form :class="cardClass" class="space-y-2" @submit.prevent="add">
                <h2 class="font-bold">{{ t('Add a tag') }}</h2>
                <div class="flex gap-2">
                    <input v-model="adding.name" maxlength="50" required :placeholder="t('Name')" :class="inputClass" />
                    <button type="submit" :disabled="adding.processing || !adding.name.trim()" class="inline-flex shrink-0 items-center gap-1 rounded-xl bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50"><Plus class="size-4" /> {{ t('Add') }}</button>
                </div>
                <p v-if="adding.errors.name" class="text-sm text-red-600">{{ adding.errors.name }}</p>
            </form>

            <!-- Added by members: check them -->
            <section v-if="newTags.length" :class="cardClass" class="space-y-3">
                <h2 class="flex items-center gap-2 font-bold"><Sparkles class="size-5 text-amber-500" /> {{ t('New tags to check') }}</h2>
                <p class="text-xs text-slate-500">{{ t('Added while posting an album. Keep them, or merge them into the right one.') }}</p>
                <ul class="space-y-2">
                    <li v-for="tag in newTags" :key="tag.id" class="flex flex-wrap items-center gap-2">
                        <span class="flex-1 font-semibold">{{ tag.name }} <span class="text-xs font-normal text-slate-500">· {{ tc(':count album|:count albums', tag.albums) }}</span></span>
                        <button type="button" :class="smallButton" @click="looksFine(tag)"><Check class="size-3.5" /> {{ t('Looks fine') }}</button>
                    </li>
                </ul>
            </section>

            <!-- Look alike: the same person? -->
            <section v-if="lookAlikes.length" :class="cardClass" class="space-y-3">
                <h2 class="flex items-center gap-2 font-bold"><GitMerge class="size-5 text-accent" /> {{ t('The same person?') }}</h2>
                <ul class="space-y-3">
                    <li v-for="pair in lookAlikes" :key="pair.join('-')" class="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-950">
                        <p class="mb-2 font-semibold">{{ byId[pair[0]].name }} <span class="font-normal text-slate-500">({{ byId[pair[0]].albums }})</span> · {{ byId[pair[1]].name }} <span class="font-normal text-slate-500">({{ byId[pair[1]].albums }})</span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" :class="smallButton" @click="merge(byId[pair[1]], byId[pair[0]])">{{ t('Keep :name', { name: byId[pair[0]].name }) }}</button>
                            <button type="button" :class="smallButton" @click="merge(byId[pair[0]], byId[pair[1]])">{{ t('Keep :name', { name: byId[pair[1]].name }) }}</button>
                            <button type="button" :class="smallButton" class="text-slate-500" @click="distinct(pair)"><X class="size-3.5" /> {{ t('Not the same') }}</button>
                        </div>
                    </li>
                </ul>
            </section>
        </aside>
    </div>
</template>

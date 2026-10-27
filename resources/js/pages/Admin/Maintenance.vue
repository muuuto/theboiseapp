<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Brush, Check, CheckCircle2, ChevronRight, Copy, Crop, Database, Download, Eye, Dumbbell, History, ImagePlus, Sparkles, Mail, MapPin, MapPinned, Mountain, Pencil, Play, Plus, Trash2, UserPlus, XCircle,
} from 'lucide-vue-next';
import { formatDate, fullDate } from '../../time.js';
import AlbumHistory from '../../components/AlbumHistory.vue';
import EmojiPicker from '../../components/EmojiPicker.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import { t, tc } from '../../i18n.js';

const props = defineProps({
    invites: Array, // null until the invites migration runs
    pendingMigrations: Array,
    backups: Array,
    phpVersion: String,
    phpBinary: String,
    laravelVersion: String,
    checks: Object,
    mailTemplates: Object,
    albumPosting: Object, // null until its migration runs
    gym: Array, // who can use the gym tracker, null until its migration runs
    decorations: Object,
});

const page = usePage();
const output = computed(() => page.props.flash?.output);
const inviteLink = computed(() => page.props.flash?.inviteLink);

const running = ref(null);
function run(action, question = null) {
    if (question && !window.confirm(t(question))) return;
    running.value = action;
    router.post(`/admin/maintenance/${action}`, {}, { preserveScroll: true, onFinish: () => (running.value = null) });
}

// Invites
const invite = useForm({ note: '', days: 7 });
function createInvite() {
    invite.post('/admin/invites', { preserveScroll: true, onSuccess: () => invite.reset('note') });
}
function revoke(id) {
    router.delete(`/admin/invites/${id}`, { preserveScroll: true });
}
const copied = ref(false);
async function copyInvite() {
    await navigator.clipboard.writeText(inviteLink.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

// Test emails: one sample email sent to the admin
const testEmail = useForm({ template: 'new_album' });
function sendTestEmail() {
    testEmail.post('/admin/maintenance/test-email', { preserveScroll: true });
}

// Members posting albums
function setPostingMode(enabled) {
    const question = enabled
        ? t('Let the people switched on below post albums?')
        : t('Only admins will post albums. Members keep their albums but can no longer edit them. Continue?');
    if (window.confirm(question)) {
        router.post('/admin/maintenance/album-posting', { enabled }, { preserveScroll: true });
    }
}
function setEveryone(canPost) {
    const question = canPost
        ? t('Allow everyone to post albums? (Admins always can; the demo account never.)')
        : t('Stop everyone from posting albums? Only admins will. Their albums stay.');
    if (window.confirm(question)) {
        router.put('/admin/maintenance/album-posting/everyone', { canPost }, { preserveScroll: true });
    }
}
function setCanPost(person, canPost) {
    router.put(`/admin/maintenance/album-posting/${person.id}`, { canPost }, { preserveScroll: true });
}

// Gym tracker: only for the people switched on
function setGymEveryone(on) {
    if (window.confirm(on ? t('Let everyone use the gym tracker? (Not the demo account.)') : t('Stop everyone from using the gym tracker? Their workouts are kept.'))) {
        router.put('/admin/maintenance/gym/everyone', { on }, { preserveScroll: true });
    }
}
function setGym(person, on) {
    router.put(`/admin/maintenance/gym/${person.id}`, { on }, { preserveScroll: true });
}

// Home page decorations (holidays and birthdays)
const decorationsForm = useForm({
    enabled: props.decorations.enabled,
    birthdays: props.decorations.birthdays,
    holidays: Object.fromEntries(props.decorations.list.map((h) => [h.key, { on: h.on, weeks: h.weeks }])),
});
// Only-once decorations show the year too
const dayMonth = (date, year = false) => formatDate(date, year ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });
const holidayDates = (h) => {
    const year = h.custom && !h.yearly;
    return h.first === h.last ? dayMonth(h.first, year) : `${dayMonth(h.first, year)} – ${dayMonth(h.last, year)}`;
};
function saveDecorations() {
    decorationsForm.put('/admin/maintenance/decorations', { preserveScroll: true });
}

// The admins' own decorations: added and edited here, then switched on/off and timed like the others.
// One added or deleted (the page stays, its list changes): its switch joins or leaves the form,
// the changes not saved yet stay
watch(() => props.decorations.list, (list) => {
    const saved = (h) => ({ on: h.on, weeks: h.weeks });
    decorationsForm.holidays = Object.fromEntries(list.map((h) => [h.key, decorationsForm.holidays[h.key] ?? saved(h)]));
    decorationsForm.defaults('holidays', Object.fromEntries(list.map((h) => [h.key, saved(h)])));
});
const customOpen = ref(false);
const customId = ref(null); // null: a new one
const customForm = useForm({ label: '', icon: '', snow: false, falling: '', greeting: '', from: '', to: '', yearly: true });
function openCustom(h = null) {
    customId.value = h?.id ?? null;
    customForm.clearErrors();
    customForm.defaults(h
        ? { label: h.label, icon: h.icon, snow: h.snow, falling: h.snow ? '' : h.falling, greeting: h.greeting, from: h.from, to: h.to, yearly: h.yearly }
        : { label: '', icon: '', snow: false, falling: '', greeting: '', from: '', to: '', yearly: true });
    customForm.reset();
    customOpen.value = true;
    nextTick(() => document.getElementById('custom-decoration')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}
function saveCustom() {
    const done = { preserveScroll: true, onSuccess: () => (customOpen.value = false) };
    customId.value ? customForm.put(`/admin/maintenance/decorations/custom/${customId.value}`, done) : customForm.post('/admin/maintenance/decorations/custom', done);
}
function deleteCustom(h) {
    if (window.confirm(t('Delete the decoration ":name"?', { name: h.label }))) {
        router.delete(`/admin/maintenance/decorations/custom/${h.id}`, { preserveScroll: true });
    }
}
// One day: the end date follows the start until it's changed
function setFrom(value) {
    if (!customForm.to || customForm.to === customForm.from || customForm.to < value) customForm.to = value;
    customForm.from = value;
}

const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const buttonClass = 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-40';
// One-time album jobs: rows of the same width, with what each one does
const jobClass = 'flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm ring-1 ring-slate-200 transition dark:ring-slate-700';
const albumJobs = [
    { action: 'trim-album-covers', icon: Crop, label: 'Remove bars from old album previews', detail: 'Crops the black bars from the old covers' },
    { action: 'import-album-coordinates', icon: MapPin, label: 'Import suggested album locations', detail: 'Adds the missing places, never undoes the review' },
    { action: 'import-album-elevations', icon: Mountain, label: 'Look up the altitude of album places', detail: 'For Wrapped\'s highest place (also done every night)' },
];
</script>

<template>
    <Head :title="t('Maintenance')" />

    <h1 class="text-3xl font-extrabold tracking-tight">{{ t('Maintenance') }}</h1>
    <p class="mt-1 text-sm text-slate-500">PHP {{ phpVersion }} · Laravel {{ laravelVersion }} · <span class="font-mono text-xs">{{ phpBinary }}</span></p>
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <span v-for="(ok, name) in checks" :key="name" class="inline-flex items-center gap-1" :class="ok ? 'text-green-600' : 'font-bold text-red-600'">
            <component :is="ok ? CheckCircle2 : XCircle" class="size-4" /> {{ name }}
        </span>
    </div>

    <pre v-if="output" class="mt-6 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-sm text-green-400">{{ output }}</pre>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Everyday actions -->
        <section :class="cardClass" class="space-y-4">
            <h2 class="text-lg font-bold">{{ t('Actions') }}</h2>
            <div class="flex flex-wrap gap-2">
                <button :class="buttonClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" :disabled="!!running" @click="run('clear-cache')">
                    <Brush class="size-4" /> {{ t('Clear caches') }}
                </button>
                <button :class="buttonClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" :disabled="!!running" @click="run('backup')">
                    <Database class="size-4" /> {{ t('Backup database now') }}
                </button>
                <button
                    :class="buttonClass"
                    class="bg-brand text-white hover:bg-brand-dark"
                    :disabled="!!running || !pendingMigrations.length"
                    @click="run('migrate', 'Make a backup and run the pending migrations?')"
                >
                    <Play class="size-4" /> {{ t('Backup + run migrations') }}
                </button>
            </div>
            <div>
                <h3 class="text-sm font-semibold">{{ t('Pending migrations') }}</h3>
                <p v-if="!pendingMigrations.length" class="text-sm text-slate-500">{{ t('None, the database is up to date.') }}</p>
                <p v-for="migration in pendingMigrations" :key="migration" class="font-mono text-xs">{{ migration }}</p>
            </div>
        </section>

        <!-- Backups -->
        <section :class="cardClass">
            <h2 class="mb-3 text-lg font-bold">{{ t('Backups') }}</h2>
            <p v-if="!backups.length" class="text-sm text-slate-500">{{ t('No backups yet.') }}</p>
            <ul class="max-h-64 space-y-1 overflow-y-auto">
                <li v-for="backup in backups" :key="backup.name">
                    <!-- A file download: a normal link -->
                    <a :href="`/admin/maintenance/backups/${backup.name}`" class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                        <Download class="size-4 shrink-0 text-accent" />
                        <span class="truncate font-mono text-xs">{{ backup.name }}</span>
                        <span class="ml-auto shrink-0 text-xs text-slate-500">{{ backup.size }} KB</span>
                    </a>
                </li>
            </ul>
        </section>

        <!-- Invites -->
        <section :class="cardClass" class="space-y-4">
            <h2 class="text-lg font-bold">{{ t('Invites') }}</h2>
            <p v-if="invites === null" class="text-sm text-slate-500">{{ t('Run the pending migrations to enable invites.') }}</p>
            <template v-else>
                <div v-if="inviteLink" class="space-y-2 rounded-2xl bg-green-50 p-4 dark:bg-green-950/40">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">{{ t('New invite link. Copy it now: it won\'t be shown again.') }}</p>
                    <div class="flex gap-2">
                        <input :value="inviteLink" readonly class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-white px-3 font-mono text-xs ring-1 ring-green-200 dark:bg-slate-900 dark:ring-green-900" />
                        <button class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3 text-sm font-semibold text-white hover:bg-green-700" @click="copyInvite">
                            <component :is="copied ? Check : Copy" class="size-4" /> {{ copied ? t('Copied!') : t('Copy') }}
                        </button>
                    </div>
                </div>

                <form class="flex flex-wrap items-end gap-2" @submit.prevent="createInvite">
                    <label class="min-w-40 flex-1">
                        <span class="mb-1 block text-xs font-semibold">{{ t('For (optional)') }}</span>
                        <input v-model="invite.note" maxlength="255" :placeholder="t('Example: Zecca')" class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold">{{ t('Valid for') }}</span>
                        <select v-model="invite.days" class="h-10 rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700">
                            <option :value="1">{{ t('1 day') }}</option>
                            <option :value="7">{{ t('7 days') }}</option>
                            <option :value="30">{{ t('30 days') }}</option>
                        </select>
                    </label>
                    <button type="submit" :class="buttonClass" class="h-10 bg-brand py-0 text-white hover:bg-brand-dark" :disabled="invite.processing">
                        <UserPlus class="size-4" /> {{ t('Create invite link') }}
                    </button>
                </form>

                <ul class="space-y-1 text-sm">
                    <li v-for="item in invites" :key="item.id" class="flex flex-wrap items-center gap-x-2 rounded-lg px-2 py-1">
                        <span class="font-medium">{{ item.note || t('No note') }}</span>
                        <span v-if="item.usedBy" class="text-green-600">{{ t('used by :name on :date', { name: item.usedBy, date: fullDate(item.usedAt) }) }}</span>
                        <span v-else-if="item.expired" class="text-slate-500">{{ t('expired :date', { date: fullDate(item.expiresAt) }) }}</span>
                        <template v-else>
                            <span class="text-slate-500">{{ t('open until :date', { date: fullDate(item.expiresAt) }) }}</span>
                            <button class="text-red-600 hover:underline" @click="revoke(item.id)">{{ t('revoke') }}</button>
                        </template>
                    </li>
                    <li v-if="!invites.length" class="text-slate-500">{{ t('No invites yet.') }}</li>
                </ul>
            </template>
        </section>

        <!-- Test emails -->
        <section :class="cardClass" class="space-y-3">
            <h2 class="text-lg font-bold">{{ t('Test emails') }}</h2>
            <p class="text-sm text-slate-500">
                {{ t('Sends one email with sample data (your name, your albums) only to you, to see how it looks. Nothing is saved and nobody else receives it.') }}
            </p>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="sendTestEmail">
                <label class="min-w-48 flex-1">
                    <span class="mb-1 block text-xs font-semibold">{{ t('Email') }}</span>
                    <select v-model="testEmail.template" class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700">
                        <option v-for="(label, key) in mailTemplates" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <button type="submit" :class="buttonClass" class="h-10 bg-brand py-0 text-white hover:bg-brand-dark" :disabled="testEmail.processing">
                    <Mail class="size-4" /> {{ testEmail.processing ? t('Sending…') : t('Send it to me') }}
                </button>
            </form>
            <p v-if="testEmail.errors.template" class="text-sm text-red-600">{{ testEmail.errors.template }}</p>
        </section>

        <!-- Members posting albums -->
        <section :class="cardClass" class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-60 flex-1">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><ImagePlus class="size-5 text-accent" /> {{ t('Members posting albums') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('When on, the people switched on below can post albums, tag anyone, and edit the albums they posted. Only admins delete albums and give someone a different link or description. Everything is in the history.') }}
                    </p>
                </div>
                <template v-if="albumPosting">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="albumPosting.enabled"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold ring-1 transition"
                        :class="albumPosting.enabled ? 'bg-teal-50 text-teal-800 ring-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:ring-teal-900' : 'ring-slate-200 dark:ring-slate-700'"
                        @click="setPostingMode(!albumPosting.enabled)"
                    >
                        <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition" :class="albumPosting.enabled ? 'bg-accent' : 'bg-slate-300 dark:bg-slate-600'">
                            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="albumPosting.enabled ? 'left-4.5' : 'left-0.5'" />
                        </span>
                        {{ albumPosting.enabled ? t('On') : t('Off: only admins post') }}
                    </button>
                </template>
            </div>

            <p v-if="!albumPosting" class="text-sm text-slate-500">{{ t('Run the pending migrations to enable it.') }}</p>
            <div v-if="albumPosting" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold">{{ t('Who can post') }}</h3>
                        <span class="ml-auto flex gap-1.5">
                            <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setEveryone(true)">{{ t('Allow everyone') }}</button>
                            <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setEveryone(false)">{{ t('No one') }}</button>
                        </span>
                    </div>
                    <p v-if="!albumPosting.enabled" class="mb-2 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ t('Switched off: these choices are kept and apply when you switch it on.') }}
                    </p>
                    <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800">
                        <li v-for="person in albumPosting.people" :key="person.id" class="flex items-center gap-3 py-2">
                            <img v-if="person.avatar" :src="person.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                            <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ person.name.charAt(0).toUpperCase() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ person.name }}</span>
                                <span v-if="person.albumsPosted" class="text-xs text-slate-500">{{ tc(':count album posted|:count albums posted', person.albumsPosted) }}</span>
                            </span>
                            <span v-if="person.isAdmin" class="shrink-0 text-xs text-slate-500">{{ t('admin, always') }}</span>
                            <button
                                v-else
                                type="button"
                                role="switch"
                                :aria-checked="person.canPost"
                                :aria-label="t(':name can post albums', { name: person.name })"
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                :class="person.canPost ? (albumPosting.enabled ? 'bg-accent' : 'bg-teal-300 dark:bg-teal-800') : 'bg-slate-300 dark:bg-slate-600'"
                                @click="setCanPost(person, !person.canPost)"
                            >
                                <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="person.canPost ? 'left-5.5' : 'left-0.5'" />
                            </button>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-2 flex items-center gap-1.5 text-sm font-semibold"><History class="size-4" /> {{ t('Album history') }}</h3>
                    <div class="max-h-96 overflow-y-auto pr-1">
                        <AlbumHistory :events="albumPosting.history" show-links />
                    </div>
                </div>
            </div>
        </section>

        <!-- Home page decorations (a section: the form for your own decorations is inside it) -->
        <section :class="cardClass" class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-60 flex-1">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><Sparkles class="size-5 text-accent" /> {{ t('Decorations') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t('Things falling on the home page for holidays, from the weeks before you choose until the day. Birthdays come first (only that day). Two holidays at once: the shorter one shows. Preview opens the home page with it.') }}
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold">
                    <input v-model="decorationsForm.enabled" type="checkbox" class="size-5 accent-teal-600" /> {{ t('Decorations on') }}
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60" :class="!decorationsForm.enabled && 'opacity-50'">
                <label class="inline-flex items-center gap-2 font-semibold">
                    <input v-model="decorationsForm.birthdays" type="checkbox" class="size-5 accent-teal-600" /> {{ t('🎂 Birthdays') }}
                </label>
                <span class="text-slate-500">{{ t('🥳🎂🎉🎁 on someone\'s birthday, with their name') }}</span>
                <a href="/?decoration=birthday" class="ml-auto inline-flex items-center gap-1 font-semibold text-accent hover:underline"><Eye class="size-4" /> {{ t('Preview') }}</a>
            </div>

            <div class="overflow-x-auto" :class="!decorationsForm.enabled && 'opacity-50'">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500">
                            <th class="py-1 pr-2 font-medium">{{ t('Holiday') }}</th>
                            <th class="py-1 pr-2 font-medium">{{ t('Next') }}</th>
                            <th class="py-1 pr-2 font-medium">{{ t('Falling') }}</th>
                            <th class="py-1 pr-2 font-medium">{{ t('Weeks before') }}</th>
                            <th class="py-1 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="h in decorations.list" :key="h.key" :class="h.past && 'opacity-50'">
                            <td class="py-2 pr-2">
                                <label class="inline-flex items-center gap-2 font-medium whitespace-nowrap">
                                    <input v-model="decorationsForm.holidays[h.key].on" type="checkbox" class="size-5 accent-teal-600" /> {{ h.icon }} {{ h.custom ? h.label : t(h.label) }}
                                </label>
                                <span v-if="h.custom" class="ml-2 rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                    {{ h.yearly ? t('yours, every year') : h.past ? t('yours, over') : t('yours, once') }}
                                </span>
                            </td>
                            <td class="py-2 pr-2 whitespace-nowrap text-slate-500">{{ holidayDates(h) }}</td>
                            <td class="py-2 pr-2 whitespace-nowrap">{{ h.falling }}</td>
                            <td class="py-2 pr-2">
                                <select v-model.number="decorationsForm.holidays[h.key].weeks" class="h-9 rounded-lg border-0 bg-white px-2 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700" :aria-label="t(':holiday: weeks before', { holiday: h.custom ? h.label : t(h.label) })">
                                    <option :value="0">{{ t('only the day(s)') }}</option>
                                    <option v-for="n in 8" :key="n" :value="n">{{ tc(':count week|:count weeks', n) }}</option>
                                </select>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <a :href="`/?decoration=${h.key}`" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><Eye class="size-4" /> {{ t('Preview') }}</a>
                                <template v-if="h.custom">
                                    <button type="button" class="ml-1 rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-accent dark:hover:bg-slate-800" :aria-label="t('Edit :name', { name: h.label })" @click="openCustom(h)"><Pencil class="size-4" /></button>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" :aria-label="t('Delete :name', { name: h.label })" @click="deleteCustom(h)"><Trash2 class="size-4" /></button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" :class="buttonClass" class="bg-brand text-white hover:bg-brand-dark" :disabled="decorationsForm.processing || !decorationsForm.isDirty" @click="saveDecorations">
                    {{ t('Save decorations') }}
                </button>
                <button v-if="!customOpen" type="button" :class="buttonClass" class="ring-1 ring-slate-200 hover:bg-slate-100 dark:ring-slate-700 dark:hover:bg-slate-800" @click="openCustom()">
                    <Plus class="size-4" /> {{ t('Add a decoration') }}
                </button>
            </div>

            <!-- Your own decoration: a party, a trip, a day that matters to you -->
            <form v-if="customOpen" id="custom-decoration" class="space-y-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60" @submit.prevent="saveCustom">
                <h3 class="font-bold">{{ customId ? t('Edit the decoration') : t('New decoration') }}</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Name') }}<RequiredMark /></span>
                        <input v-model="customForm.label" required maxlength="40" aria-required="true" :placeholder="t('Example: TheBoise anniversary')" class="h-10 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                        <span v-if="customForm.errors.label" class="mt-1 block text-sm text-red-600">{{ customForm.errors.label }}</span>
                    </label>
                    <div>
                        <span class="mb-1 block text-sm font-semibold">{{ t('Icon') }}</span>
                        <div class="flex items-center gap-1">
                            <input v-model="customForm.icon" maxlength="20" :placeholder="t('the first one falling')" class="h-10 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" :aria-label="t('Icon')" />
                            <EmojiPicker placement="bottom" @pick="(emoji) => (customForm.icon = emoji)" />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="mb-1 block text-sm font-semibold">{{ t('What falls') }}<RequiredMark /></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <input
                                v-model="customForm.falling"
                                :disabled="customForm.snow"
                                maxlength="200"
                                placeholder="🎈 🎉 🥳"
                                class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-white px-3 text-lg ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none disabled:opacity-50 dark:bg-slate-950 dark:ring-slate-700"
                                :aria-label="t('What falls')"
                            />
                            <EmojiPicker v-if="!customForm.snow" placement="bottom" @pick="(emoji) => (customForm.falling = `${customForm.falling.trim()} ${emoji}`.trim())" />
                            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                                <input v-model="customForm.snow" type="checkbox" class="size-5 accent-teal-600" /> {{ t('❄️ Snow instead') }}
                            </label>
                        </div>
                        <span class="mt-1 block text-xs text-slate-500">{{ t('Up to 8 emojis, they fall in turn.') }}</span>
                        <span v-if="customForm.errors.falling" class="mt-1 block text-sm text-red-600">{{ customForm.errors.falling }}</span>
                    </div>
                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-sm font-semibold">{{ t('Greeting on the day') }}<RequiredMark /></span>
                        <input v-model="customForm.greeting" required maxlength="100" aria-required="true" :placeholder="t('Example: Happy anniversary, TheBoise!')" class="h-10 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                        <span class="mt-1 block text-xs text-slate-500">{{ t('Before the day the badge says ":name in 3 days".', { name: customForm.label || t('Name') }) }}</span>
                        <span v-if="customForm.errors.greeting" class="mt-1 block text-sm text-red-600">{{ customForm.errors.greeting }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('From') }}<RequiredMark /></span>
                        <input :value="customForm.from" type="date" required aria-required="true" class="h-10 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" @input="setFrom($event.target.value)" />
                        <span v-if="customForm.errors.from" class="mt-1 block text-sm text-red-600">{{ customForm.errors.from }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold">{{ t('To') }}<RequiredMark /></span>
                        <input v-model="customForm.to" type="date" required aria-required="true" :min="customForm.from" class="h-10 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                        <span v-if="customForm.errors.to" class="mt-1 block text-sm text-red-600">{{ customForm.errors.to }}</span>
                    </label>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold">
                    <input v-model="customForm.yearly" type="checkbox" class="size-5 accent-teal-600" /> {{ t('Every year on these days') }}
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" :class="buttonClass" class="ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="customOpen = false">{{ t('Cancel') }}</button>
                    <button type="submit" :class="buttonClass" class="bg-brand text-white hover:bg-brand-dark" :disabled="customForm.processing">{{ customId ? t('Save') : t('Add') }}</button>
                </div>
            </form>
        </section>

        <!-- Gym tracker -->
        <section :class="cardClass" class="space-y-4">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold"><Dumbbell class="size-5 text-accent" /> {{ t('Gym tracker') }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ t('Workouts, weights and personal records. Only the people switched on see "Gym" in the menu; each one sees only their own workouts.') }}
                </p>
            </div>
            <p v-if="!gym" class="text-sm text-slate-500">{{ t('Run the pending migrations to enable it.') }}</p>
            <template v-else>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold">{{ t('Who can use it') }}</h3>
                    <span class="text-xs text-slate-500">{{ gym.filter((person) => person.on).length }}/{{ gym.length }}</span>
                    <span class="ml-auto flex gap-1.5">
                        <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setGymEveryone(true)">{{ t('Allow everyone') }}</button>
                        <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setGymEveryone(false)">{{ t('No one') }}</button>
                    </span>
                </div>
                <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800">
                    <li v-for="person in gym" :key="person.id" class="flex items-center gap-3 py-2">
                        <img v-if="person.avatar" :src="person.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ person.name.charAt(0).toUpperCase() }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ person.name }}</span>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="person.on"
                            :aria-label="t(':name can use the gym tracker', { name: person.name })"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                            :class="person.on ? 'bg-accent' : 'bg-slate-300 dark:bg-slate-600'"
                            @click="setGym(person, !person.on)"
                        >
                            <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="person.on ? 'left-5.5' : 'left-0.5'" />
                        </button>
                    </li>
                </ul>
            </template>
        </section>

        <!-- One-time album jobs (as tall as its content, not as the card next to it) -->
        <section :class="cardClass" class="space-y-3 lg:self-start">
            <h2 class="text-lg font-bold">{{ t('One-time album jobs') }}</h2>
            <p class="text-sm text-slate-500">
                {{ t('Safe to run more than once: they skip what\'s already done. The import never undoes the review: it only adds the missing extra places (e.g. Dublin next to London) to albums that still have one of the suggested places, and never brings back a place you removed.') }}
            </p>
            <!-- One row each, all the same width -->
            <div class="grid gap-2">
                <button
                    v-for="job in albumJobs"
                    :key="job.action"
                    type="button"
                    :class="jobClass"
                    class="hover:bg-slate-50 disabled:opacity-40 dark:hover:bg-slate-800"
                    :disabled="!!running"
                    @click="run(job.action)"
                >
                    <component :is="job.icon" class="size-5 shrink-0 text-accent" />
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ t(job.label) }}</span>
                        <span class="block text-xs text-slate-500">{{ t(job.detail) }}</span>
                    </span>
                    <Play class="ml-auto size-4 shrink-0 text-slate-400" />
                </button>
                <Link href="/admin/locations" :class="jobClass" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <MapPinned class="size-5 shrink-0 text-accent" />
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ t('Review album locations') }}</span>
                        <span class="block text-xs text-slate-500">{{ t('Confirm or fix the places suggested on the map') }}</span>
                    </span>
                    <ChevronRight class="ml-auto size-4 shrink-0 text-slate-400" />
                </Link>
            </div>
        </section>
    </div>
</template>

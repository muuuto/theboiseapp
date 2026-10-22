<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Brush, Check, CheckCircle2, Copy, Crop, Database, Download, Eye, History, ImagePlus, Sparkles, Mail, MapPin, MapPinned, Play, UserPlus, XCircle,
} from 'lucide-vue-next';
import { fullDate } from '../../time.js';
import AlbumHistory from '../../components/AlbumHistory.vue';

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
    decorations: Object,
});

const page = usePage();
const output = computed(() => page.props.flash?.output);
const inviteLink = computed(() => page.props.flash?.inviteLink);

const running = ref(null);
function run(action, question = null) {
    if (question && !window.confirm(question)) return;
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
        ? 'Let the people switched on below post albums?'
        : 'Only admins will post albums. Members keep their albums but can no longer edit them. Continue?';
    if (window.confirm(question)) {
        router.post('/admin/maintenance/album-posting', { enabled }, { preserveScroll: true });
    }
}
function setOnlyOwnDefault(on) {
    router.post('/admin/maintenance/only-own-default', { on }, { preserveScroll: true });
}
function setEveryone(canPost) {
    const question = canPost
        ? 'Allow everyone to post albums? (Admins always can; the demo account never.)'
        : 'Stop everyone from posting albums? Only admins will. Their albums stay.';
    if (window.confirm(question)) {
        router.put('/admin/maintenance/album-posting/everyone', { canPost }, { preserveScroll: true });
    }
}
function setCanPost(person, canPost) {
    router.put(`/admin/maintenance/album-posting/${person.id}`, { canPost }, { preserveScroll: true });
}

// Home page decorations (holidays and birthdays)
const decorationsForm = useForm({
    enabled: props.decorations.enabled,
    birthdays: props.decorations.birthdays,
    holidays: Object.fromEntries(props.decorations.list.map((h) => [h.key, { on: h.on, weeks: h.weeks }])),
});
const dayMonth = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' });
const holidayDates = (h) => (h.first === h.last ? dayMonth.format(new Date(h.first)) : `${dayMonth.format(new Date(h.first))} – ${dayMonth.format(new Date(h.last))}`);
function saveDecorations() {
    decorationsForm.put('/admin/maintenance/decorations', { preserveScroll: true });
}

const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const buttonClass = 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-40';
</script>

<template>
    <Head title="Maintenance" />

    <h1 class="text-3xl font-extrabold tracking-tight">Maintenance</h1>
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
            <h2 class="text-lg font-bold">Actions</h2>
            <div class="flex flex-wrap gap-2">
                <button :class="buttonClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" :disabled="!!running" @click="run('clear-cache')">
                    <Brush class="size-4" /> Clear caches
                </button>
                <button :class="buttonClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" :disabled="!!running" @click="run('backup')">
                    <Database class="size-4" /> Backup database now
                </button>
                <button
                    :class="buttonClass"
                    class="bg-brand text-white hover:bg-brand-dark"
                    :disabled="!!running || !pendingMigrations.length"
                    @click="run('migrate', 'Make a backup and run the pending migrations?')"
                >
                    <Play class="size-4" /> Backup + run migrations
                </button>
            </div>
            <div>
                <h3 class="text-sm font-semibold">Pending migrations</h3>
                <p v-if="!pendingMigrations.length" class="text-sm text-slate-500">None, the database is up to date.</p>
                <p v-for="migration in pendingMigrations" :key="migration" class="font-mono text-xs">{{ migration }}</p>
            </div>
        </section>

        <!-- Backups -->
        <section :class="cardClass">
            <h2 class="mb-3 text-lg font-bold">Backups</h2>
            <p v-if="!backups.length" class="text-sm text-slate-500">No backups yet.</p>
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
            <h2 class="text-lg font-bold">Invites</h2>
            <p v-if="invites === null" class="text-sm text-slate-500">Run the pending migrations to enable invites.</p>
            <template v-else>
                <div v-if="inviteLink" class="space-y-2 rounded-2xl bg-green-50 p-4 dark:bg-green-950/40">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300">New invite link. Copy it now: it won't be shown again.</p>
                    <div class="flex gap-2">
                        <input :value="inviteLink" readonly class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-white px-3 font-mono text-xs ring-1 ring-green-200 dark:bg-slate-900 dark:ring-green-900" />
                        <button class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3 text-sm font-semibold text-white hover:bg-green-700" @click="copyInvite">
                            <component :is="copied ? Check : Copy" class="size-4" /> {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>
                </div>

                <form class="flex flex-wrap items-end gap-2" @submit.prevent="createInvite">
                    <label class="min-w-40 flex-1">
                        <span class="mb-1 block text-xs font-semibold">For (optional)</span>
                        <input v-model="invite.note" maxlength="255" placeholder="Example: Zecca" class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-semibold">Valid for</span>
                        <select v-model="invite.days" class="h-10 rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700">
                            <option :value="1">1 day</option>
                            <option :value="7">7 days</option>
                            <option :value="30">30 days</option>
                        </select>
                    </label>
                    <button type="submit" :class="buttonClass" class="h-10 bg-brand py-0 text-white hover:bg-brand-dark" :disabled="invite.processing">
                        <UserPlus class="size-4" /> Create invite link
                    </button>
                </form>

                <ul class="space-y-1 text-sm">
                    <li v-for="item in invites" :key="item.id" class="flex flex-wrap items-center gap-x-2 rounded-lg px-2 py-1">
                        <span class="font-medium">{{ item.note || 'No note' }}</span>
                        <span v-if="item.usedBy" class="text-green-600">used by {{ item.usedBy }} on {{ fullDate(item.usedAt) }}</span>
                        <span v-else-if="item.expired" class="text-slate-500">expired {{ fullDate(item.expiresAt) }}</span>
                        <template v-else>
                            <span class="text-slate-500">open until {{ fullDate(item.expiresAt) }}</span>
                            <button class="text-red-600 hover:underline" @click="revoke(item.id)">revoke</button>
                        </template>
                    </li>
                    <li v-if="!invites.length" class="text-slate-500">No invites yet.</li>
                </ul>
            </template>
        </section>

        <!-- Test emails -->
        <section :class="cardClass" class="space-y-3">
            <h2 class="text-lg font-bold">Test emails</h2>
            <p class="text-sm text-slate-500">
                Sends one email with sample data (your name, your albums) only to you, to see how it looks.
                Nothing is saved and nobody else receives it.
            </p>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="sendTestEmail">
                <label class="min-w-48 flex-1">
                    <span class="mb-1 block text-xs font-semibold">Email</span>
                    <select v-model="testEmail.template" class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700">
                        <option v-for="(label, key) in mailTemplates" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <button type="submit" :class="buttonClass" class="h-10 bg-brand py-0 text-white hover:bg-brand-dark" :disabled="testEmail.processing">
                    <Mail class="size-4" /> {{ testEmail.processing ? 'Sending…' : 'Send it to me' }}
                </button>
            </form>
            <p v-if="testEmail.errors.template" class="text-sm text-red-600">{{ testEmail.errors.template }}</p>
        </section>

        <!-- Members posting albums -->
        <section :class="cardClass" class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-60 flex-1">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><ImagePlus class="size-5 text-accent" /> Members posting albums</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        When on, the people switched on below can post albums, tag anyone, and edit the albums they posted.
                        Only admins delete albums and give someone a different link or description. Everything is in the history.
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
                        {{ albumPosting.enabled ? 'On' : 'Off: only admins post' }}
                    </button>
                </template>
            </div>

            <p v-if="!albumPosting" class="text-sm text-slate-500">Run the pending migrations to enable it.</p>
            <div v-if="albumPosting" class="flex flex-wrap items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="albumPosting.onlyOwnDefault"
                    aria-label="Only albums I posted: on by default"
                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                    :class="albumPosting.onlyOwnDefault ? 'bg-accent' : 'bg-slate-300 dark:bg-slate-600'"
                    @click="setOnlyOwnDefault(!albumPosting.onlyOwnDefault)"
                >
                    <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="albumPosting.onlyOwnDefault ? 'left-5.5' : 'left-0.5'" />
                </button>
                <span class="min-w-48 flex-1">
                    <strong>"Only albums I posted" on by default</strong>
                    <span class="block text-xs text-slate-500">For who hasn't chosen yet. Whoever flips the switch on the album list keeps their choice.</span>
                </span>
            </div>
            <div v-if="albumPosting" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold">Who can post</h3>
                        <span class="ml-auto flex gap-1.5">
                            <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setEveryone(true)">Allow everyone</button>
                            <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800" @click="setEveryone(false)">No one</button>
                        </span>
                    </div>
                    <p v-if="!albumPosting.enabled" class="mb-2 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        Switched off: these choices are kept and apply when you switch it on.
                    </p>
                    <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800">
                        <li v-for="person in albumPosting.people" :key="person.id" class="flex items-center gap-3 py-2">
                            <img v-if="person.avatar" :src="person.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                            <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ person.name.charAt(0).toUpperCase() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ person.name }}</span>
                                <span v-if="person.albumsPosted" class="text-xs text-slate-500">{{ person.albumsPosted }} {{ person.albumsPosted === 1 ? 'album' : 'albums' }} posted</span>
                            </span>
                            <span v-if="person.isAdmin" class="shrink-0 text-xs text-slate-500">admin, always</span>
                            <button
                                v-else
                                type="button"
                                role="switch"
                                :aria-checked="person.canPost"
                                :aria-label="`${person.name} can post albums`"
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
                    <h3 class="mb-2 flex items-center gap-1.5 text-sm font-semibold"><History class="size-4" /> Album history</h3>
                    <div class="max-h-96 overflow-y-auto pr-1">
                        <AlbumHistory :events="albumPosting.history" show-links />
                    </div>
                </div>
            </div>
        </section>

        <!-- Home page decorations -->
        <form :class="cardClass" class="space-y-4 lg:col-span-2" @submit.prevent="saveDecorations">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-60 flex-1">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><Sparkles class="size-5 text-accent" /> Decorations</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Things falling on the home page for holidays, from the weeks before you choose until the day.
                        Birthdays come first (only that day). Two holidays at once: the shorter one shows. Preview opens the home page with it.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold">
                    <input v-model="decorationsForm.enabled" type="checkbox" class="size-5 accent-teal-600" /> Decorations on
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60" :class="!decorationsForm.enabled && 'opacity-50'">
                <label class="inline-flex items-center gap-2 font-semibold">
                    <input v-model="decorationsForm.birthdays" type="checkbox" class="size-5 accent-teal-600" /> 🎂 Birthdays
                </label>
                <span class="text-slate-500">🥳🎂🎉🎁 on someone's birthday, with their name</span>
                <a href="/?decoration=birthday" class="ml-auto inline-flex items-center gap-1 font-semibold text-accent hover:underline"><Eye class="size-4" /> Preview</a>
            </div>

            <div class="overflow-x-auto" :class="!decorationsForm.enabled && 'opacity-50'">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500">
                            <th class="py-1 pr-2 font-medium">Holiday</th>
                            <th class="py-1 pr-2 font-medium">Next</th>
                            <th class="py-1 pr-2 font-medium">Falling</th>
                            <th class="py-1 pr-2 font-medium">Weeks before</th>
                            <th class="py-1 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="h in decorations.list" :key="h.key">
                            <td class="py-2 pr-2">
                                <label class="inline-flex items-center gap-2 font-medium whitespace-nowrap">
                                    <input v-model="decorationsForm.holidays[h.key].on" type="checkbox" class="size-5 accent-teal-600" /> {{ h.icon }} {{ h.label }}
                                </label>
                            </td>
                            <td class="py-2 pr-2 whitespace-nowrap text-slate-500">{{ holidayDates(h) }}</td>
                            <td class="py-2 pr-2 whitespace-nowrap">{{ h.falling }}</td>
                            <td class="py-2 pr-2">
                                <select v-model.number="decorationsForm.holidays[h.key].weeks" class="h-9 rounded-lg border-0 bg-white px-2 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700" :aria-label="`${h.label}: weeks before`">
                                    <option :value="0">only the day(s)</option>
                                    <option v-for="n in 8" :key="n" :value="n">{{ n }} {{ n === 1 ? 'week' : 'weeks' }}</option>
                                </select>
                            </td>
                            <td class="py-2 text-right"><a :href="`/?decoration=${h.key}`" class="inline-flex items-center gap-1 font-semibold text-accent hover:underline"><Eye class="size-4" /> Preview</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="submit" :class="buttonClass" class="bg-brand text-white hover:bg-brand-dark" :disabled="decorationsForm.processing || !decorationsForm.isDirty">
                Save decorations
            </button>
        </form>

        <!-- One-time album jobs -->
        <section :class="cardClass" class="space-y-3">
            <h2 class="text-lg font-bold">One-time album jobs</h2>
            <p class="text-sm text-slate-500">
                Safe to run more than once: they skip what's already done. The import never undoes the review:
                it only adds the missing extra places (e.g. Dublin next to London) to albums that still have
                one of the suggested places, and never brings back a place you removed.
            </p>
            <div class="flex flex-wrap gap-2">
                <button :class="buttonClass" class="ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800" :disabled="!!running" @click="run('trim-album-covers')">
                    <Crop class="size-4" /> Remove bars from old album previews
                </button>
                <button :class="buttonClass" class="ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800" :disabled="!!running" @click="run('import-album-coordinates')">
                    <MapPin class="size-4" /> Import suggested album locations
                </button>
                <Link href="/admin/locations" :class="buttonClass" class="text-accent hover:underline">
                    <MapPinned class="size-4" /> Review album locations
                </Link>
            </div>
        </section>
    </div>
</template>

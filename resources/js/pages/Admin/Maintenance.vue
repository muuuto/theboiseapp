<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Brush, Check, CheckCircle2, Copy, Crop, Database, Download, Mail, MapPin, MapPinned, Play, UserPlus, XCircle,
} from 'lucide-vue-next';
import { fullDate } from '../../time.js';

const props = defineProps({
    invites: Array, // null until the invites migration runs
    pendingMigrations: Array,
    backups: Array,
    phpVersion: String,
    phpBinary: String,
    laravelVersion: String,
    checks: Object,
    mailTemplates: Object,
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

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
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

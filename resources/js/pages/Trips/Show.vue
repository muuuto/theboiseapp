<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, BarChart3, CalendarClock, Check, Clock, CloudSun, Crown, FileText, Hand, Image as ImageIcon, Images, ListChecks, Loader2, MapPin, Paperclip, Pencil, Plus, Trash2, Upload, Users, Vote, Wallet, X,
} from 'lucide-vue-next';
import Countdown from '../../components/Countdown.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import { t, tc } from '../../i18n.js';
import { formatDate } from '../../time.js';

const props = defineProps({
    trip: Object,
    me: Number,
    members: Array,
    options: Array,
    items: Array,
    documents: { type: Array, default: null }, // null until its migration runs
    polls: { type: Array, default: null }, // null until its migration runs
    weather: { type: Object, default: null }, // { place, days: [{ date, code, max, min, rain }] } once within two weeks
    can: Object,
});
const isEvent = computed(() => props.trip.kind === 'event');

const keep = { preserveScroll: true, preserveState: true };
const nameOf = (id) => (id === props.me ? t('You') : props.members.find((m) => m.id === id)?.name ?? t('Someone'));
const memberById = (id) => props.members.find((m) => m.id === id);

const longDate = { weekday: 'short', day: 'numeric', month: 'short' };
const range = (option) => {
    const from = formatDate(option.dateFrom, longDate);
    return option.dateFrom === option.dateTo ? from : `${from} – ${formatDate(option.dateTo, longDate)}`;
};
const nights = (option) => Math.round((new Date(option.dateTo) - new Date(option.dateFrom)) / 86400000);

// --- Date poll ---
const mostVotes = computed(() => Math.max(0, ...props.options.map((option) => option.voters.length)));
const pollOpen = computed(() => !props.trip.dateFrom);
const vote = (option) => router.post(`/trips/${props.trip.id}/options/${option.id}/vote`, {}, keep);
function choose(option) {
    if (window.confirm(t('Go with :dates? Everyone gets told.', { dates: range(option) }))) router.post(`/trips/${props.trip.id}/options/${option.id}/choose`, {}, keep);
}
function deleteOption(option) {
    if (window.confirm(t('Remove the option :dates and its votes?', { dates: range(option) }))) router.delete(`/trips/${props.trip.id}/options/${option.id}`, keep);
}
const newOption = useForm({ from: '', to: '' });
const addingOption = ref(false);
function addOption() {
    newOption.post(`/trips/${props.trip.id}/options`, { ...keep, onSuccess: () => { newOption.reset(); addingOption.value = false; } });
}

// --- Checklist ---
const doneCount = computed(() => props.items.filter((item) => item.done).length);
const newItem = useForm({ text: '', assigned_to: null });
function addItem() {
    newItem.post(`/trips/${props.trip.id}/items`, { ...keep, onSuccess: () => newItem.reset('text') });
}
const updateItem = (item, data) => router.put(`/trips/${props.trip.id}/items/${item.id}`, data, keep);
const deleteItem = (item) => router.delete(`/trips/${props.trip.id}/items/${item.id}`, keep);

// --- Documents: tickets, bookings, PDFs ---
const upload = useForm({ file: null, name: '', for: null });
const fileInput = ref(null);
function pickFile(event) {
    upload.file = event.target.files[0] ?? null;
    // The file's name as a start, without the extension
    if (upload.file && !upload.name) upload.name = upload.file.name.replace(/\.[^.]+$/, '').slice(0, 150);
}
function addDocument() {
    upload.post(`/trips/${props.trip.id}/documents`, {
        ...keep,
        forceFormData: true,
        onSuccess: () => {
            upload.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
function deleteDocument(document) {
    if (window.confirm(t('Remove ":name"?', { name: document.name }))) router.delete(`/trips/${props.trip.id}/documents/${document.id}`, keep);
}
// Mine first, then everyone's, then the others'
const sortedDocuments = computed(() => [...(props.documents ?? [])].sort((a, b) => rank(a) - rank(b)));
const rank = (document) => (document.forUser === props.me ? 0 : document.forUser === null ? 1 : 2);
const fileSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

const statusLine = {
    planning: 'Voting the dates',
    upcoming: 'Starts in',
    ongoing: 'Happening now! Have fun 🎉',
    finished: 'Back home: time to make it an album',
    album: 'Done: it\'s an album now',
    today: 'It\'s today! 🎉',
    past: 'It\'s over: hope it was fun',
};

// --- Events: I'm in / maybe / no ---
const rsvpOptions = [
    { answer: 'yes', label: "I'm in", on: 'bg-emerald-600 text-white ring-emerald-600' },
    { answer: 'maybe', label: 'Maybe', on: 'bg-amber-500 text-white ring-amber-500' },
    { answer: 'no', label: "Can't come", on: 'bg-slate-600 text-white ring-slate-600' },
];
const myRsvp = computed(() => props.members.find((member) => member.id === props.me)?.rsvp ?? null);
const answer = (value) => router.post(`/trips/${props.trip.id}/rsvp`, { answer: value }, keep);
const byAnswer = computed(() => ({
    yes: props.members.filter((member) => member.rsvp === 'yes'),
    maybe: props.members.filter((member) => member.rsvp === 'maybe'),
    no: props.members.filter((member) => member.rsvp === 'no'),
    none: props.members.filter((member) => !member.rsvp),
}));

// --- Polls: "Pizza or sushi?" ---
const pollForm = useForm({ question: '', options: ['', ''] });
const addingPoll = ref(false);
function addPoll() {
    pollForm
        .transform((data) => ({ ...data, options: data.options.map((option) => option.trim()).filter(Boolean) }))
        .post(`/trips/${props.trip.id}/polls`, { ...keep, onSuccess: () => { pollForm.reset(); addingPoll.value = false; } });
}
const votePoll = (poll, option) => router.post(`/trips/${props.trip.id}/polls/${poll.id}/options/${option.id}/vote`, {}, keep);
function deletePoll(poll) {
    if (window.confirm(t('Delete the poll ":question"?', { question: poll.question }))) router.delete(`/trips/${props.trip.id}/polls/${poll.id}`, keep);
}
const mostPoll = (poll) => Math.max(1, ...poll.options.map((option) => option.voters.length));

// --- Weather: Open-Meteo codes ---
function weatherIcon(code) {
    if (code === 0) return '☀️';
    if (code <= 2) return '🌤️';
    if (code === 3) return '☁️';
    if (code <= 48) return '🌫️';
    if (code <= 67 || (code >= 80 && code <= 82)) return '🌧️';
    if (code <= 77 || code === 85 || code === 86) return '❄️';
    return '⛈️';
}
const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
</script>

<template>
    <Head :title="trip.title" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <Link href="/trips" class="inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
            <ArrowLeft class="size-4" /> {{ t('Trips & events') }}
        </Link>
        <Link
            v-if="can.manage"
            :href="`/trips/${trip.id}/edit`"
            class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
        >
            <Pencil class="size-4" /> {{ isEvent ? t('Edit event') : t('Edit trip') }}
        </Link>
    </div>

    <!-- Header: what, where, when, countdown -->
    <section class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-br p-6 text-white shadow-lg sm:p-8" :class="isEvent ? 'from-fuchsia-500 via-pink-600 to-rose-600' : 'from-sky-500 via-indigo-600 to-violet-700'">
        <p class="text-sm font-semibold tracking-wide text-white/80 uppercase">{{ isEvent ? '🎉' : '✈️' }} {{ t(statusLine[trip.status]) }}</p>
        <h1 class="mt-1 text-4xl font-black tracking-tight sm:text-5xl">{{ trip.title }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-white/90">
            <span v-if="trip.destination" class="inline-flex items-center gap-1"><MapPin class="size-4" /> {{ trip.destination }}</span>
            <span v-if="trip.dates" class="inline-flex items-center gap-1"><CalendarClock class="size-4" /> {{ trip.dates }}</span>
            <span v-if="trip.time" class="inline-flex items-center gap-1"><Clock class="size-4" /> {{ trip.time }}</span>
            <span class="inline-flex items-center gap-1"><Users class="size-4" /> {{ isEvent ? t(':count in', { count: byAnswer.yes.length }) : t(':count going', { count: members.length }) }}</span>
        </p>
        <div v-if="trip.status === 'upcoming'" class="mt-5 max-w-md"><Countdown :to="trip.dateFrom" :time="trip.time" /></div>

        <!-- Events: are you in? -->
        <div v-if="isEvent && trip.status !== 'past'" class="mt-5">
            <p class="mb-2 text-sm font-semibold text-white/90">{{ myRsvp ? t('Your answer') : t('Are you in?') }}</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in rsvpOptions"
                    :key="option.answer"
                    type="button"
                    class="rounded-xl px-4 py-2 text-sm font-bold ring-1 transition"
                    :class="myRsvp === option.answer ? option.on : 'bg-white/15 ring-white/40 hover:bg-white/25'"
                    :aria-pressed="myRsvp === option.answer"
                    @click="answer(option.answer)"
                >
                    {{ t(option.label) }}
                </button>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <Link v-if="trip.album" :href="`/listings/${trip.album.id}`" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-50">
                <Images class="size-4" /> {{ t('Open the album') }}
            </Link>
            <a v-else-if="can.makeAlbum" :href="`/trips/${trip.id}/album`" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-50">
                <Images class="size-4" /> {{ t('Make it an album') }}
            </a>
            <Link v-if="trip.expenseGroup" :href="`/expenses/${trip.expenseGroup.id}`" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-bold ring-1 ring-white/40 hover:bg-white/25">
                <Wallet class="size-4" /> {{ t('Shared expenses') }}
            </Link>
            <button v-else type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-bold ring-1 ring-white/40 hover:bg-white/25" @click="router.post(`/trips/${trip.id}/expenses`)">
                <Wallet class="size-4" /> {{ t('Start shared expenses') }}
            </button>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <!-- Date poll -->
            <section v-if="pollOpen || options.length" :class="cardClass">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><Vote class="size-5 text-violet-600" /> {{ pollOpen ? t('When? Vote the dates') : t('The date poll') }}</h2>
                    <span v-if="!pollOpen" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ t('decided') }}</span>
                </div>
                <p v-if="pollOpen" class="mb-3 text-sm text-slate-500">{{ t('Tap the options that work for you.') }} {{ can.manage ? t('When you\'re ready, choose one.') : t('The organiser chooses.') }}</p>

                <ul class="space-y-2">
                    <li
                        v-for="option in options"
                        :key="option.id"
                        class="rounded-2xl p-3 ring-1 transition"
                        :class="option.voters.length === mostVotes && mostVotes > 0 ? 'bg-violet-50 ring-violet-200 dark:bg-violet-950/30 dark:ring-violet-900' : 'ring-slate-200 dark:ring-slate-800'"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ range(option) }}</span>
                                <span class="text-xs text-slate-500">{{ tc(':count night|:count nights', nights(option)) }}</span>
                            </span>
                            <span v-if="option.voters.length === mostVotes && mostVotes > 0" class="inline-flex items-center gap-1 text-xs font-bold text-violet-700 dark:text-violet-300"><Crown class="size-3.5" /> {{ t('leading') }}</span>
                            <button
                                v-if="pollOpen"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold transition"
                                :class="option.voters.includes(me) ? 'bg-violet-600 text-white hover:bg-violet-700' : 'ring-1 ring-slate-300 hover:bg-slate-50 dark:ring-slate-600 dark:hover:bg-slate-800'"
                                :aria-pressed="option.voters.includes(me)"
                                @click="vote(option)"
                            >
                                <Check class="size-4" /> {{ option.voters.includes(me) ? t('Works for me') : t('Works for me?') }}
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="flex -space-x-1.5">
                                <template v-for="id in option.voters" :key="id">
                                    <img v-if="memberById(id)?.avatar" :src="memberById(id).avatar" :alt="nameOf(id)" :title="nameOf(id)" class="size-6 rounded-full object-cover ring-2 ring-white dark:ring-slate-900" />
                                    <span v-else :title="nameOf(id)" class="flex size-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold ring-2 ring-white dark:bg-slate-700 dark:ring-slate-900">{{ nameOf(id).charAt(0) }}</span>
                                </template>
                            </span>
                            <span class="text-xs text-slate-500">{{ t(':count of :total', { count: option.voters.length, total: members.length }) }}</span>
                            <span class="ml-auto flex gap-1">
                                <button v-if="pollOpen && can.manage" type="button" class="rounded-lg px-2 py-1 text-xs font-bold text-violet-700 hover:bg-violet-100 dark:text-violet-300 dark:hover:bg-violet-950" @click="choose(option)">{{ t('Choose these') }}</button>
                                <button v-if="pollOpen && option.canDelete" type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" :title="t('Remove option')" @click="deleteOption(option)">
                                    <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove option') }}</span>
                                </button>
                            </span>
                        </div>
                    </li>
                </ul>

                <template v-if="pollOpen">
                    <form v-if="addingOption" class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="addOption">
                        <label class="block flex-1"><span class="mb-1 block text-xs font-semibold">{{ t('From') }}<RequiredMark /></span><input v-model="newOption.from" type="date" required :class="inputClass" /></label>
                        <label class="block flex-1"><span class="mb-1 block text-xs font-semibold">{{ t('To') }}<RequiredMark /></span><input v-model="newOption.to" type="date" required :min="newOption.from" :class="inputClass" /></label>
                        <button type="submit" class="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700" :disabled="newOption.processing">{{ t('Add') }}</button>
                    </form>
                    <button v-else type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700 hover:underline dark:text-violet-300" @click="addingOption = true">
                        <Plus class="size-4" /> {{ t('Suggest other dates') }}
                    </button>
                    <p v-if="newOption.errors.to" class="mt-1 text-sm text-red-600">{{ newOption.errors.to }}</p>
                </template>
            </section>

            <!-- Polls -->
            <section v-if="polls" :class="cardClass">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><BarChart3 class="size-5 text-fuchsia-600" /> {{ t('Polls') }}</h2>
                    <button v-if="!addingPoll" type="button" class="ml-auto inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="addingPoll = true">
                        <Plus class="size-3.5" /> {{ t('New poll') }}
                    </button>
                </div>
                <p v-if="!polls.length && !addingPoll" class="text-sm text-slate-500">{{ t('Pizza or sushi? Which beach? Ask everyone: they tick what they like.') }}</p>

                <div v-for="poll in polls" :key="poll.id" class="mb-4 last:mb-0">
                    <div class="mb-2 flex items-start gap-2">
                        <p class="flex-1 font-semibold">{{ poll.question }}</p>
                        <button v-if="poll.canDelete" type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" :aria-label="t('Delete')" @click="deletePoll(poll)"><Trash2 class="size-4" /></button>
                    </div>
                    <ul class="space-y-1.5">
                        <li v-for="option in poll.options" :key="option.id">
                            <button type="button" class="relative flex w-full items-center gap-2 overflow-hidden rounded-xl px-3 py-2 text-left text-sm ring-1 transition" :class="option.voters.includes(me) ? 'ring-fuchsia-400 dark:ring-fuchsia-700' : 'ring-slate-200 hover:ring-slate-300 dark:ring-slate-700'" @click="votePoll(poll, option)">
                                <span class="absolute inset-y-0 left-0 bg-fuchsia-100 transition-all dark:bg-fuchsia-950/60" :style="{ width: `${(option.voters.length / mostPoll(poll)) * 100}%` }" />
                                <Check v-if="option.voters.includes(me)" class="relative size-4 shrink-0 text-fuchsia-600" />
                                <span class="relative min-w-0 flex-1 truncate font-medium">{{ option.text }}</span>
                                <span class="relative shrink-0 text-xs text-slate-500" :title="option.voters.map(nameOf).join(', ')">{{ option.voters.length }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <form v-if="addingPoll" class="space-y-2 rounded-2xl bg-slate-50 p-3 dark:bg-slate-950" @submit.prevent="addPoll">
                    <input v-model="pollForm.question" maxlength="200" required :placeholder="t('The question, e.g. Pizza or sushi?')" :class="inputClass" />
                    <div v-for="(option, index) in pollForm.options" :key="index" class="flex gap-2">
                        <input v-model="pollForm.options[index]" maxlength="100" :placeholder="t('Option :number', { number: index + 1 })" :class="inputClass" />
                        <button v-if="pollForm.options.length > 2" type="button" class="rounded-lg p-2 text-slate-400 hover:text-red-600" :aria-label="t('Remove option')" @click="pollForm.options.splice(index, 1)"><X class="size-4" /></button>
                    </div>
                    <p v-for="(message, key) in pollForm.errors" :key="key" class="text-sm text-red-600">{{ message }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="pollForm.options.length < 10" type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="pollForm.options.push('')">{{ t('Another option') }}</button>
                        <button type="button" class="ml-auto rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-500 hover:bg-white dark:hover:bg-slate-900" @click="addingPoll = false">{{ t('Cancel') }}</button>
                        <button type="submit" :disabled="pollForm.processing" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">{{ t('Ask') }}</button>
                    </div>
                </form>
            </section>

            <!-- Checklist -->
            <section :class="cardClass">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><ListChecks class="size-5 text-accent" /> {{ t('Who brings what') }}</h2>
                    <span v-if="items.length" class="ml-auto text-sm font-semibold text-slate-500">{{ t(':count/:total done', { count: doneCount, total: items.length }) }}</span>
                </div>
                <div v-if="items.length" class="mb-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-accent transition-all" :style="{ width: `${(doneCount / items.length) * 100}%` }" />
                </div>

                <form class="mb-3 flex flex-wrap gap-2" @submit.prevent="addItem">
                    <input v-model="newItem.text" maxlength="255" required :placeholder="t('Add something: tent, book the ferry, bring the speaker...')" :class="inputClass" class="min-w-48 flex-1" />
                    <select v-model="newItem.assigned_to" :class="inputClass" class="w-auto" :aria-label="t('Who takes care of it')">
                        <option :value="null">{{ t('Anyone') }}</option>
                        <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? t('Me') : member.name }}</option>
                    </select>
                    <button type="submit" class="h-10 rounded-xl bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50" :disabled="newItem.processing">{{ t('Add') }}</button>
                </form>

                <p v-if="!items.length" class="text-sm text-slate-500">{{ t('Nothing on the list yet.') }}</p>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="item in items" :key="item.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                        <input
                            type="checkbox"
                            :checked="item.done"
                            class="size-5 shrink-0 accent-teal-600"
                            :aria-label="t(':what done', { what: item.text })"
                            @change="updateItem(item, { done: !item.done })"
                        />
                        <span class="min-w-0 flex-1" :class="item.done && 'text-slate-400 line-through'">{{ item.text }}</span>
                        <button
                            v-if="!item.assignedTo && !item.done"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800"
                            @click="updateItem(item, { assigned_to: me })"
                        >
                            <Hand class="size-3.5" /> {{ t('I\'ll take it') }}
                        </button>
                        <select
                            v-else
                            :value="item.assignedTo"
                            class="h-8 rounded-lg border-0 bg-transparent px-2 text-xs font-semibold ring-1 ring-slate-200 dark:ring-slate-700"
                            :aria-label="t('Who takes care of :what', { what: item.text })"
                            @change="updateItem(item, { assigned_to: $event.target.value ? Number($event.target.value) : null })"
                        >
                            <option value="">{{ t('Anyone') }}</option>
                            <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? t('Me') : member.name }}</option>
                        </select>
                        <button v-if="item.canDelete" type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" :title="t('Remove :name', { name: item.text })" @click="deleteItem(item)">
                            <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove :name', { name: item.text }) }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Documents: tickets, bookings, PDFs (private: only the people going open them) -->
            <section v-if="documents" :class="cardClass">
                <h2 class="mb-1 flex items-center gap-2 text-lg font-bold"><Paperclip class="size-5 text-accent" /> {{ t('Documents') }}</h2>
                <p class="mb-3 text-sm text-slate-500">{{ t('Tickets, bookings, PDFs: all here, so nobody digs through WhatsApp at the airport. Only the people going can open them.') }}</p>

                <ul v-if="documents.length" class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="document in sortedDocuments" :key="document.id" class="flex items-center gap-3 py-2">
                        <a :href="document.url" target="_blank" rel="noopener" class="flex min-w-0 flex-1 items-center gap-3 rounded-xl hover:text-accent">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="document.image ? 'bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400' : 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400'">
                                <ImageIcon v-if="document.image" class="size-5" /><FileText v-else class="size-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ document.name }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    <span v-if="document.forUser" class="mr-1 rounded-full px-1.5 py-0.5 font-semibold" :class="document.forUser === me ? 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300' : 'bg-slate-100 dark:bg-slate-800'">{{ document.forUser === me ? t('Yours') : t('For :name', { name: nameOf(document.forUser) }) }}</span>
                                    {{ fileSize(document.size) }} · {{ t('added by :name', { name: document.uploadedBy ?? t('Someone') }) }}
                                </span>
                            </span>
                        </a>
                        <button v-if="document.canDelete" type="button" class="rounded-lg p-2 text-slate-400 hover:text-red-600" :aria-label="t('Remove :name', { name: document.name })" @click="deleteDocument(document)">
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>

                <form class="space-y-2 rounded-2xl bg-slate-50 p-3 dark:bg-slate-950" @submit.prevent="addDocument">
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-3 py-3 text-sm font-medium text-slate-600 hover:border-accent hover:text-accent dark:border-slate-700 dark:text-slate-300">
                        <Upload class="size-4 shrink-0" />
                        <span class="truncate">{{ upload.file ? upload.file.name : t('Choose a PDF or a photo') }}</span>
                        <input ref="fileInput" type="file" accept="application/pdf,image/*" class="sr-only" @change="pickFile" />
                    </label>
                    <template v-if="upload.file">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_12rem]">
                            <input v-model="upload.name" maxlength="150" :placeholder="t('Name, e.g. Ryanair boarding pass')" :aria-label="t('Name')" :class="inputClass" />
                            <select v-model="upload.for" :aria-label="t('Whose is it?')" :class="inputClass">
                                <option :value="null">{{ t('For everyone') }}</option>
                                <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? t('Mine') : t('For :name', { name: member.name }) }}</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="upload.processing" class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
                            <Loader2 v-if="upload.processing" class="size-4 animate-spin" /><Plus v-else class="size-4" /> {{ t('Add document') }}
                        </button>
                    </template>
                    <p v-if="upload.errors.file" class="text-sm text-red-600">{{ upload.errors.file }}</p>
                </form>
            </section>
        </div>

        <aside class="space-y-6">
            <!-- The forecast, once it's within two weeks -->
            <section v-if="weather" :class="cardClass">
                <h2 class="mb-1 flex items-center gap-2 font-bold"><CloudSun class="size-5 text-sky-500" /> {{ t('Weather') }}</h2>
                <p class="mb-3 text-xs text-slate-500">{{ weather.place }}</p>
                <ul class="space-y-1.5">
                    <li v-for="day in weather.days" :key="day.date" class="flex items-center gap-3 text-sm">
                        <span class="w-20 shrink-0 capitalize text-slate-600 dark:text-slate-300">{{ formatDate(day.date, { weekday: 'short', day: 'numeric' }) }}</span>
                        <span class="text-xl leading-none">{{ weatherIcon(day.code) }}</span>
                        <span class="font-semibold tabular-nums">{{ day.max }}°</span>
                        <span class="text-slate-500 tabular-nums">{{ day.min }}°</span>
                        <span v-if="day.rain !== null" class="ml-auto text-xs text-sky-600 tabular-nums dark:text-sky-400">💧 {{ day.rain }}%</span>
                    </li>
                </ul>
            </section>

            <!-- Events: who's in -->
            <section v-if="isEvent" :class="cardClass" class="space-y-3">
                <h2 class="flex items-center gap-2 font-bold"><Users class="size-5 text-accent" /> {{ t('Who\'s coming') }}</h2>
                <div v-for="[key, label] in [['yes', 'I\'m in'], ['maybe', 'Maybe'], ['no', 'Can\'t come'], ['none', 'No answer yet']]" v-show="byAnswer[key].length" :key="key">
                    <p class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t(label) }} · {{ byAnswer[key].length }}</p>
                    <ul class="flex flex-wrap gap-1.5">
                        <li v-for="member in byAnswer[key]" :key="member.id" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-0.5 pr-2.5 pl-0.5 text-sm dark:bg-slate-800" :class="key === 'no' && 'opacity-60'">
                            <img v-if="member.avatar" :src="member.avatar" alt="" class="size-6 rounded-full object-cover" />
                            <span v-else class="flex size-6 items-center justify-center rounded-full bg-slate-300 text-[10px] font-bold dark:bg-slate-600">{{ member.name.charAt(0).toUpperCase() }}</span>
                            {{ member.name }}
                        </li>
                    </ul>
                </div>
                <p class="text-xs text-slate-500">{{ t('Organised by :name', { name: trip.createdBy }) }}</p>
            </section>

            <section v-else :class="cardClass">
                <h2 class="mb-3 flex items-center gap-2 font-bold"><Users class="size-5 text-accent" /> {{ t('Going') }} <span class="text-sm font-medium text-slate-500">{{ members.length }}</span></h2>
                <ul class="grid grid-cols-2 gap-2">
                    <li v-for="member in members" :key="member.id" class="flex min-w-0 items-center gap-2">
                        <img v-if="member.avatar" :src="member.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ member.name.charAt(0).toUpperCase() }}</span>
                        <span class="truncate text-sm">{{ member.name }}</span>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-slate-500">{{ t('Organised by :name', { name: trip.createdBy }) }}</p>
            </section>

            <section v-if="trip.notes" :class="cardClass">
                <h2 class="mb-2 font-bold">{{ t('Notes') }}</h2>
                <p class="text-sm whitespace-pre-line text-slate-700 dark:text-slate-300">{{ trip.notes }}</p>
            </section>
        </aside>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, CalendarClock, Check, Crown, Hand, Images, ListChecks, MapPin, Pencil, Plus, Trash2, Users, Vote, Wallet,
} from 'lucide-vue-next';
import Countdown from '../../components/Countdown.vue';

const props = defineProps({
    trip: Object,
    me: Number,
    members: Array,
    options: Array,
    items: Array,
    can: Object,
});

const keep = { preserveScroll: true, preserveState: true };
const nameOf = (id) => (id === props.me ? 'You' : props.members.find((m) => m.id === id)?.name ?? 'Someone');
const memberById = (id) => props.members.find((m) => m.id === id);

const longDate = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
const range = (option) => {
    const from = longDate.format(new Date(option.dateFrom));
    return option.dateFrom === option.dateTo ? from : `${from} – ${longDate.format(new Date(option.dateTo))}`;
};
const nights = (option) => Math.round((new Date(option.dateTo) - new Date(option.dateFrom)) / 86400000);

// --- Date poll ---
const mostVotes = computed(() => Math.max(0, ...props.options.map((option) => option.voters.length)));
const pollOpen = computed(() => !props.trip.dateFrom);
const vote = (option) => router.post(`/trips/${props.trip.id}/options/${option.id}/vote`, {}, keep);
function choose(option) {
    if (window.confirm(`Go with ${range(option)}? Everyone gets told.`)) router.post(`/trips/${props.trip.id}/options/${option.id}/choose`, {}, keep);
}
function deleteOption(option) {
    if (window.confirm(`Remove the option ${range(option)} and its votes?`)) router.delete(`/trips/${props.trip.id}/options/${option.id}`, keep);
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

const statusLine = {
    planning: 'Voting the dates',
    upcoming: 'Starts in',
    ongoing: 'Happening now! Have fun 🎉',
    finished: "Back home: time to make it an album",
    album: 'Done: it\'s an album now',
};
const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-10 w-full rounded-xl border-0 bg-white px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
</script>

<template>
    <Head :title="trip.title" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <Link href="/trips" class="inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
            <ArrowLeft class="size-4" /> Trips
        </Link>
        <Link
            v-if="can.manage"
            :href="`/trips/${trip.id}/edit`"
            class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
        >
            <Pencil class="size-4" /> Edit trip
        </Link>
    </div>

    <!-- Header: what, where, when, countdown -->
    <section class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-sky-500 via-indigo-600 to-violet-700 p-6 text-white shadow-lg sm:p-8">
        <p class="text-sm font-semibold tracking-wide text-white/80 uppercase">✈️ {{ statusLine[trip.status] }}</p>
        <h1 class="mt-1 text-4xl font-black tracking-tight sm:text-5xl">{{ trip.title }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-white/90">
            <span v-if="trip.destination" class="inline-flex items-center gap-1"><MapPin class="size-4" /> {{ trip.destination }}</span>
            <span v-if="trip.dates" class="inline-flex items-center gap-1"><CalendarClock class="size-4" /> {{ trip.dates }}</span>
            <span class="inline-flex items-center gap-1"><Users class="size-4" /> {{ members.length }} going</span>
        </p>
        <div v-if="trip.status === 'upcoming'" class="mt-5 max-w-md"><Countdown :to="trip.dateFrom" /></div>

        <div class="mt-5 flex flex-wrap gap-2">
            <Link v-if="trip.album" :href="`/listings/${trip.album.id}`" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-50">
                <Images class="size-4" /> Open the album
            </Link>
            <a v-else-if="can.makeAlbum" :href="`/trips/${trip.id}/album`" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-700 hover:bg-indigo-50">
                <Images class="size-4" /> Make it an album
            </a>
            <Link v-if="trip.expenseGroup" :href="`/expenses/${trip.expenseGroup.id}`" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-bold ring-1 ring-white/40 hover:bg-white/25">
                <Wallet class="size-4" /> Shared expenses
            </Link>
            <button v-else type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2 text-sm font-bold ring-1 ring-white/40 hover:bg-white/25" @click="router.post(`/trips/${trip.id}/expenses`)">
                <Wallet class="size-4" /> Start shared expenses
            </button>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <!-- Date poll -->
            <section v-if="pollOpen || options.length" :class="cardClass">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><Vote class="size-5 text-violet-600" /> {{ pollOpen ? 'When? Vote the dates' : 'The date poll' }}</h2>
                    <span v-if="!pollOpen" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">decided</span>
                </div>
                <p v-if="pollOpen" class="mb-3 text-sm text-slate-500">Tap the options that work for you. {{ can.manage ? 'When you\'re ready, choose one.' : 'The organiser chooses.' }}</p>

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
                                <span class="text-xs text-slate-500">{{ nights(option) }} {{ nights(option) === 1 ? 'night' : 'nights' }}</span>
                            </span>
                            <span v-if="option.voters.length === mostVotes && mostVotes > 0" class="inline-flex items-center gap-1 text-xs font-bold text-violet-700 dark:text-violet-300"><Crown class="size-3.5" /> leading</span>
                            <button
                                v-if="pollOpen"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold transition"
                                :class="option.voters.includes(me) ? 'bg-violet-600 text-white hover:bg-violet-700' : 'ring-1 ring-slate-300 hover:bg-slate-50 dark:ring-slate-600 dark:hover:bg-slate-800'"
                                :aria-pressed="option.voters.includes(me)"
                                @click="vote(option)"
                            >
                                <Check class="size-4" /> {{ option.voters.includes(me) ? 'Works for me' : 'Works for me?' }}
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="flex -space-x-1.5">
                                <template v-for="id in option.voters" :key="id">
                                    <img v-if="memberById(id)?.avatar" :src="memberById(id).avatar" :alt="nameOf(id)" :title="nameOf(id)" class="size-6 rounded-full object-cover ring-2 ring-white dark:ring-slate-900" />
                                    <span v-else :title="nameOf(id)" class="flex size-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold ring-2 ring-white dark:bg-slate-700 dark:ring-slate-900">{{ nameOf(id).charAt(0) }}</span>
                                </template>
                            </span>
                            <span class="text-xs text-slate-500">{{ option.voters.length }} of {{ members.length }}</span>
                            <span class="ml-auto flex gap-1">
                                <button v-if="pollOpen && can.manage" type="button" class="rounded-lg px-2 py-1 text-xs font-bold text-violet-700 hover:bg-violet-100 dark:text-violet-300 dark:hover:bg-violet-950" @click="choose(option)">Choose these</button>
                                <button v-if="pollOpen && option.canDelete" type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" title="Remove option" @click="deleteOption(option)">
                                    <Trash2 class="size-4" /><span class="sr-only">Remove option</span>
                                </button>
                            </span>
                        </div>
                    </li>
                </ul>

                <template v-if="pollOpen">
                    <form v-if="addingOption" class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="addOption">
                        <label class="block flex-1"><span class="mb-1 block text-xs font-semibold">From</span><input v-model="newOption.from" type="date" required :class="inputClass" /></label>
                        <label class="block flex-1"><span class="mb-1 block text-xs font-semibold">To</span><input v-model="newOption.to" type="date" required :min="newOption.from" :class="inputClass" /></label>
                        <button type="submit" class="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700" :disabled="newOption.processing">Add</button>
                    </form>
                    <button v-else type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700 hover:underline dark:text-violet-300" @click="addingOption = true">
                        <Plus class="size-4" /> Suggest other dates
                    </button>
                    <p v-if="newOption.errors.to" class="mt-1 text-sm text-red-600">{{ newOption.errors.to }}</p>
                </template>
            </section>

            <!-- Checklist -->
            <section :class="cardClass">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><ListChecks class="size-5 text-accent" /> Who brings what</h2>
                    <span v-if="items.length" class="ml-auto text-sm font-semibold text-slate-500">{{ doneCount }}/{{ items.length }} done</span>
                </div>
                <div v-if="items.length" class="mb-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-accent transition-all" :style="{ width: `${(doneCount / items.length) * 100}%` }" />
                </div>

                <form class="mb-3 flex flex-wrap gap-2" @submit.prevent="addItem">
                    <input v-model="newItem.text" maxlength="255" required placeholder="Add something: tent, book the ferry, bring the speaker..." :class="inputClass" class="min-w-48 flex-1" />
                    <select v-model="newItem.assigned_to" :class="inputClass" class="w-auto" aria-label="Who takes care of it">
                        <option :value="null">Anyone</option>
                        <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? 'Me' : member.name }}</option>
                    </select>
                    <button type="submit" class="h-10 rounded-xl bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50" :disabled="newItem.processing">Add</button>
                </form>

                <p v-if="!items.length" class="text-sm text-slate-500">Nothing on the list yet.</p>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="item in items" :key="item.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                        <input
                            type="checkbox"
                            :checked="item.done"
                            class="size-5 shrink-0 accent-teal-600"
                            :aria-label="`${item.text} done`"
                            @change="updateItem(item, { done: !item.done })"
                        />
                        <span class="min-w-0 flex-1" :class="item.done && 'text-slate-400 line-through'">{{ item.text }}</span>
                        <button
                            v-if="!item.assignedTo && !item.done"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800"
                            @click="updateItem(item, { assigned_to: me })"
                        >
                            <Hand class="size-3.5" /> I'll take it
                        </button>
                        <select
                            v-else
                            :value="item.assignedTo"
                            class="h-8 rounded-lg border-0 bg-transparent px-2 text-xs font-semibold ring-1 ring-slate-200 dark:ring-slate-700"
                            :aria-label="`Who takes care of ${item.text}`"
                            @change="updateItem(item, { assigned_to: $event.target.value ? Number($event.target.value) : null })"
                        >
                            <option value="">Anyone</option>
                            <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? 'Me' : member.name }}</option>
                        </select>
                        <button v-if="item.canDelete" type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" :title="`Remove ${item.text}`" @click="deleteItem(item)">
                            <Trash2 class="size-4" /><span class="sr-only">Remove {{ item.text }}</span>
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            <section :class="cardClass">
                <h2 class="mb-3 flex items-center gap-2 font-bold"><Users class="size-5 text-accent" /> Going <span class="text-sm font-medium text-slate-500">{{ members.length }}</span></h2>
                <ul class="grid grid-cols-2 gap-2">
                    <li v-for="member in members" :key="member.id" class="flex min-w-0 items-center gap-2">
                        <img v-if="member.avatar" :src="member.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ member.name.charAt(0).toUpperCase() }}</span>
                        <span class="truncate text-sm">{{ member.name }}</span>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-slate-500">Organised by {{ trip.createdBy }}</p>
            </section>

            <section v-if="trip.notes" :class="cardClass">
                <h2 class="mb-2 font-bold">Notes</h2>
                <p class="text-sm whitespace-pre-line text-slate-700 dark:text-slate-300">{{ trip.notes }}</p>
            </section>
        </aside>
    </div>
</template>

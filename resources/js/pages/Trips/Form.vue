<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarCheck, Plus, Trash2, Vote } from 'lucide-vue-next';
import PeoplePicker from '../../components/PeoplePicker.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import RequiredNote from '../../components/RequiredNote.vue';
import { t } from '../../i18n.js';

const props = defineProps({
    trip: Object, // null when creating
    me: Number,
    creator: Number, // always going
    users: Array,
    kind: { type: String, default: 'trip' }, // creating: trip or event (a dinner, a party: one day, RSVP, no album)
});

const editing = computed(() => !!props.trip);
const isEvent = computed(() => (props.trip?.kind ?? props.kind) === 'event');
const everyone = () => (form.members = props.users.map((user) => user.id));
const form = useForm({
    kind: props.trip?.kind ?? props.kind,
    start_time: props.trip?.start_time ?? '',
    title: props.trip?.title ?? '',
    destination: props.trip?.destination ?? '',
    notes: props.trip?.notes ?? '',
    members: props.trip?.members ?? [props.me],
    // fixed: we know the dates; poll: everyone ticks the options that work
    dates: props.trip?.dates ?? 'fixed',
    date_from: props.trip?.date_from ?? '',
    date_to: props.trip?.date_to ?? '',
    options: [{ from: '', to: '' }, { from: '', to: '' }],
});

// Errors shown next to their fields; anything else (a hidden part of the form) shows by the button
const shownNextToField = (key) => ['title', 'date_from', 'date_to', 'members'].includes(key) || (form.dates === 'poll' && key.startsWith('options'));
const hiddenError = computed(() => Object.entries(form.errors).find(([key]) => !shownNextToField(key))?.[1] ?? null);

function submit() {
    const data = (values) => ({ ...values, members: [...new Set([...values.members, props.creator])] });
    if (editing.value) form.transform(data).put(`/trips/${props.trip.id}`, { preserveScroll: true });
    else form.transform(data).post('/trips', { preserveScroll: true });
}
function destroy() {
    const question = isEvent.value
        ? t('Delete the event ":title" with its answers and polls?', { title: props.trip.title })
        : t('Delete the trip ":title" with its list and date poll? (The expense group and the album stay.)', { title: props.trip.title });
    if (window.confirm(question)) {
        router.delete(`/trips/${props.trip.id}`);
    }
}

const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
const cardClass = 'space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
</script>

<template>
    <Head :title="editing ? t('Edit :title', { title: trip.title }) : isEvent ? t('New event') : t('Plan a trip')" />

    <Link :href="editing ? `/trips/${trip.id}` : '/trips'" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> {{ editing ? trip.title : t('Trips & events') }}
    </Link>
    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? (isEvent ? t('Edit event') : t('Edit trip')) : isEvent ? t('New event') : t('Plan a trip') }}</h1>

    <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
        <RequiredNote class="-mb-3" />
        <section :class="cardClass">
            <label class="block">
                <span :class="labelClass">{{ t('Name') }}<RequiredMark /></span>
                <input v-model="form.title" maxlength="100" required :placeholder="isEvent ? t('Example: Pizza at Zecca\'s') : t('Example: Sicilia 2027')" :class="[inputClass, form.errors.title && 'ring-2 ring-red-500']" />
                <span v-if="form.errors.title" class="mt-1 block text-sm text-red-600">{{ form.errors.title }}</span>
            </label>
            <label class="block">
                <span :class="labelClass">{{ t('Where') }} <span class="font-normal text-slate-500">{{ t('(optional)') }}</span></span>
                <input v-model="form.destination" maxlength="255" :placeholder="isEvent ? t('Example: Pizzeria da Mario, Bolzano') : t('Example: Palermo and around')" :class="inputClass" />
            </label>
            <label class="block">
                <span :class="labelClass">{{ t('Notes') }} <span class="font-normal text-slate-500">{{ t('(optional)') }}</span></span>
                <textarea v-model="form.notes" rows="3" maxlength="5000" :placeholder="t('The plan, the house link, the meeting point...')" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
            </label>
        </section>

        <section :class="cardClass">
            <h2 class="font-bold">{{ t('When') }}<RequiredMark /></h2>
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="tablist">
                <button
                    v-for="[mode, label, icon] in [['fixed', isEvent ? 'We know the day' : 'We know the dates', CalendarCheck], ['poll', 'Let\'s vote', Vote]]"
                    :key="mode"
                    type="button"
                    role="tab"
                    :aria-selected="form.dates === mode"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-sm font-semibold transition"
                    :class="form.dates === mode ? 'bg-white shadow-sm dark:bg-slate-950' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                    @click="form.dates = mode"
                >
                    <component :is="icon" class="size-4" /> {{ t(label) }}
                </button>
            </div>

            <!-- An event: one day and maybe a time -->
            <div v-if="form.dates === 'fixed' && isEvent" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block"><span :class="labelClass">{{ t('Day') }}<RequiredMark /></span><input v-model="form.date_from" type="date" :class="inputClass" /></label>
                <label class="block"><span :class="labelClass">{{ t('Time') }} <span class="font-normal text-slate-500">{{ t('(optional)') }}</span></span><input v-model="form.start_time" type="time" :class="inputClass" /></label>
                <p v-if="form.errors.date_from || form.errors.start_time" class="text-sm text-red-600 sm:col-span-2">{{ form.errors.date_from || form.errors.start_time }}</p>
            </div>
            <div v-else-if="form.dates === 'fixed'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block"><span :class="labelClass">{{ t('From') }}<RequiredMark /></span><input v-model="form.date_from" type="date" :class="inputClass" /></label>
                <label class="block"><span :class="labelClass">{{ t('To') }}<RequiredMark /></span><input v-model="form.date_to" type="date" :min="form.date_from" :class="inputClass" /></label>
                <p v-if="form.errors.date_from || form.errors.date_to" class="text-sm text-red-600 sm:col-span-2">{{ form.errors.date_from || form.errors.date_to }}</p>
            </div>

            <template v-else-if="editing">
                <p class="text-sm text-slate-500">
                    {{ tc('The dates are voted on the trip page (:count option): add options and choose the winner there.|The dates are voted on the trip page (:count options): add options and choose the winner there.', trip.optionCount) }}
                </p>
            </template>
            <div v-else class="space-y-2">
                <p class="text-xs text-slate-500">{{ t('Give a few options: everyone ticks the ones that work for them, then you choose.') }}</p>
                <div v-for="(option, index) in form.options" :key="index" class="flex items-end gap-2">
                    <label class="block flex-1"><span class="mb-1 block text-xs font-semibold">{{ isEvent ? t('Day') : t('From') }}<RequiredMark /></span><input v-model="option.from" type="date" :class="inputClass" /></label>
                    <label v-if="!isEvent" class="block flex-1"><span class="mb-1 block text-xs font-semibold">{{ t('To') }}<RequiredMark /></span><input v-model="option.to" type="date" :min="option.from" :class="inputClass" /></label>
                    <button v-if="form.options.length > 2" type="button" class="mb-1.5 rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" :title="t('Remove option')" @click="form.options.splice(index, 1)">
                        <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove option') }}</span>
                    </button>
                </div>
                <button v-if="form.options.length < 10" type="button" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="form.options.push({ from: '', to: '' })">
                    <Plus class="size-4" /> {{ t('Another option') }}
                </button>
                <p v-if="form.errors.options" class="text-sm text-red-600">{{ form.errors.options }}</p>
                <p v-else-if="Object.keys(form.errors).some((key) => key.startsWith('options.'))" class="text-sm text-red-600">{{ t('Fill in both dates of each option (the end after the start).') }}</p>
            </div>
        </section>

        <section :class="cardClass" class="space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="font-bold">{{ isEvent ? t('Who\'s invited') : t('Who\'s going') }}</h2>
                <button v-if="isEvent" type="button" class="ml-auto rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="everyone">{{ t('Everyone') }}</button>
            </div>
            <p class="text-xs text-slate-500">{{ isEvent ? t('They get an invitation and say if they\'re in. Only they see the event.') : t('They get an invitation and can vote the dates and use the list. Whoever created the trip is always in.') }}</p>
            <PeoplePicker v-model="form.members" :users="users" />
            <p v-if="form.errors.members" class="text-sm text-red-600">{{ form.errors.members }}</p>
        </section>

        <!-- An error on a part of the form that isn't showing (e.g. the poll, with fixed dates) -->
        <p v-if="hiddenError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ hiddenError }}</p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-2xl bg-brand px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50" :disabled="form.processing">
                {{ editing ? (isEvent ? t('Save event') : t('Save trip')) : isEvent ? t('Create event') : t('Create trip') }}
            </button>
            <button v-if="editing" type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40" @click="destroy">
                <Trash2 class="size-4" /> {{ isEvent ? t('Delete event') : t('Delete trip') }}
            </button>
        </div>
    </form>
</template>

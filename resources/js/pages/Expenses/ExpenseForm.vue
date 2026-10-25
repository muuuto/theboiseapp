<script setup>
import RequiredMark from '../../components/RequiredMark.vue';
import RequiredNote from '../../components/RequiredNote.vue';
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from 'lucide-vue-next';
import { currencySymbol, money, moneyIn, parseEuros, split } from '../../money.js';
import { t } from '../../i18n.js';
import { formatDate } from '../../time.js';
import { request } from '../../http.js';

const props = defineProps({
    group: Object,
    me: Number,
    members: Array,
    expense: Object, // null when adding
    currencies: { type: Array, default: () => [] }, // besides euro (empty before its migration runs)
});

const editing = computed(() => !!props.expense);
const today = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD, local time

const form = useForm({
    description: props.expense?.description ?? '',
    amount: props.expense?.amount ?? '',
    currency: props.expense?.currency ?? 'EUR',
    amount_eur: props.expense?.amount_eur ?? '',
    spent_on: props.expense?.spent_on ?? today,
    paid_by: props.expense?.paid_by ?? props.me,
    split_type: props.expense?.split_type ?? 'equal',
    split: {},
});

// The three ways of splitting keep their own values, so switching back and forth loses nothing
const shares = props.expense?.shares ?? {};
const values = reactive({
    equal: Object.fromEntries(props.members.map((m) => [m.id, editing.value ? m.id in shares : true])),
    exact: Object.fromEntries(props.members.map((m) => [m.id, props.expense?.split_type === 'exact' ? (shares[m.id]?.amount ?? '') : ''])),
    shares: Object.fromEntries(props.members.map((m) => [m.id, props.expense?.split_type === 'shares' ? (shares[m.id]?.shares ?? 0) : 1])),
});

const total = computed(() => parseEuros(form.amount));

// Another currency: everything is written in it, the site turns it into euro at the day's ECB rate
// (or at what the card charged, when given)
const foreign = computed(() => form.currency !== 'EUR');
const symbol = computed(() => currencySymbol(form.currency));
const inCurrency = (cents) => moneyIn(cents, form.currency);
const rate = ref(null); // { rate, date }
const rateError = ref(false);
const showCharged = ref(!!form.amount_eur);
watch(() => [form.currency, form.spent_on], async ([currency, date]) => {
    rate.value = null;
    rateError.value = false;
    if (currency === 'EUR' || !date) return;
    try {
        rate.value = await request('GET', `/expenses/rate?currency=${currency}&date=${date}`);
    } catch {
        rateError.value = true;
        showCharged.value = true;
    }
}, { immediate: true });
const inEuro = computed(() => {
    if (!foreign.value) return null;
    if (String(form.amount_eur).trim()) return parseEuros(form.amount_eur);
    return total.value && rate.value ? Math.max(1, Math.round(total.value / rate.value.rate)) : null;
});

// What each person owes with the current choices (null = can't tell yet)
const preview = computed(() => {
    if (form.split_type === 'exact') {
        return Object.fromEntries(props.members.map((m) => [m.id, parseEuros(values.exact[m.id]) ?? (String(values.exact[m.id]).trim() ? null : 0)]));
    }
    if (!total.value) return {};
    const chosen = form.split_type === 'equal'
        ? Object.fromEntries(props.members.filter((m) => values.equal[m.id]).map((m) => [m.id, true]))
        : Object.fromEntries(props.members.filter((m) => Number(values.shares[m.id]) > 0).map((m) => [m.id, Math.floor(Number(values.shares[m.id]))]));
    return split(total.value, form.split_type, chosen);
});

// Exact amounts: how much is still missing (or too much)
const exactLeft = computed(() => {
    if (form.split_type !== 'exact' || !total.value) return null;
    const sum = Object.values(preview.value).reduce((a, b) => a + (b ?? 0), 0);
    return total.value - sum;
});
const everyone = computed(() => props.members.every((m) => values.equal[m.id]));
function toggleEveryone() {
    const on = !everyone.value;
    props.members.forEach((m) => (values.equal[m.id] = on));
}

function submit() {
    form.transform((data) => ({ ...data, split: { ...values[data.split_type] } }));
    if (editing.value) {
        form.put(`/expenses/${props.group.id}/expenses/${props.expense.id}`, { preserveScroll: true });
    } else {
        form.post(`/expenses/${props.group.id}/expenses`, { preserveScroll: true });
    }
}

function destroy() {
    if (window.confirm(t('Delete ":what"? The balances change for everyone in it.', { what: props.expense.description }))) {
        router.delete(`/expenses/${props.group.id}/expenses/${props.expense.id}`);
    }
}

const types = [
    { key: 'equal', label: 'Equally' },
    { key: 'exact', label: 'Exact amounts' },
    { key: 'shares', label: 'By shares' },
];
const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
// Inputs on each person's row: their width is set where they're used
const smallInputClass = 'h-10 rounded-xl border-0 bg-white px-2 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
</script>

<template>
    <Head :title="editing ? t('Edit expense') : t('Add expense')" />

    <Link
        :href="`/expenses/${group.id}`"
        class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800"
    >
        <ArrowLeft class="size-4" /> {{ group.name }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? t('Edit expense') : t('Add expense') }}</h1>

    <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
        <RequiredNote class="-mb-3" />
        <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <label class="block">
                <span :class="labelClass">{{ t('What') }}<RequiredMark /></span>
                <input v-model="form.description" maxlength="255" required :placeholder="t('Example: Dinner at the beach')" :class="[inputClass, form.errors.description && 'ring-2 ring-red-500']" />
                <span v-if="form.errors.description" class="mt-1 block text-sm text-red-600">{{ form.errors.description }}</span>
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="block">
                    <label for="expense-amount" :class="labelClass">{{ t('Amount') }}<RequiredMark /></label>
                    <span class="flex">
                        <!-- Currency: euro, or the one of the trip -->
                        <select v-if="currencies.length" v-model="form.currency" :aria-label="t('Currency')" class="h-11 shrink-0 rounded-l-xl border-0 bg-slate-50 pr-7 pl-3 font-semibold ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-800 dark:ring-slate-700">
                            <option value="EUR">€ EUR</option>
                            <option v-for="code in currencies" :key="code" :value="code">{{ currencySymbol(code) === code ? code : `${currencySymbol(code)} ${code}` }}</option>
                        </select>
                        <span class="relative block min-w-0 flex-1">
                            <span v-if="!currencies.length" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-semibold text-slate-500">€</span>
                            <input
                                id="expense-amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                required
                                placeholder="0.00"
                                :class="[inputClass, 'text-lg font-semibold', currencies.length ? 'rounded-l-none' : 'pl-8', form.errors.amount && 'ring-2 ring-red-500']"
                            />
                        </span>
                    </span>
                    <span v-if="form.errors.amount" class="mt-1 block text-sm text-red-600">{{ form.errors.amount }}</span>
                </div>
                <label class="block">
                    <span :class="labelClass">{{ t('Date') }}<RequiredMark /></span>
                    <input v-model="form.spent_on" type="date" required :class="inputClass" />
                </label>
            </div>

            <!-- In euro: at the day's rate, or what the card charged -->
            <div v-if="foreign" class="space-y-2 rounded-2xl bg-sky-50 px-4 py-3 text-sm dark:bg-sky-950/40">
                <p v-if="inEuro" class="font-semibold text-sky-900 dark:text-sky-200">≈ {{ money(inEuro) }}</p>
                <p v-if="rate && !String(form.amount_eur).trim()" class="text-xs text-sky-800 dark:text-sky-300">
                    {{ t('1 € = :rate :currency (European Central Bank, :date)', { rate: rate.rate.toLocaleString(), currency: form.currency, date: formatDate(rate.date, { day: 'numeric', month: 'short' }) }) }}
                </p>
                <p v-else-if="rateError" class="text-xs text-amber-800 dark:text-amber-300">{{ t('The exchange rate isn\'t available right now: write what it cost in euro.') }}</p>
                <button v-if="!showCharged" type="button" class="text-xs font-semibold text-sky-800 underline dark:text-sky-300" @click="showCharged = true">{{ t('Your card charged a different amount in euro?') }}</button>
                <label v-else class="block">
                    <span class="mb-1 block text-xs font-semibold text-sky-900 dark:text-sky-200">{{ t('Charged in euro (optional)') }}</span>
                    <span class="relative block sm:w-48">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-semibold text-slate-500">€</span>
                        <input v-model="form.amount_eur" inputmode="decimal" placeholder="0.00" :class="[inputClass, 'pl-8', form.errors.amount_eur && 'ring-2 ring-red-500']" />
                    </span>
                </label>
                <span v-if="form.errors.amount_eur" class="block text-sm text-red-600">{{ form.errors.amount_eur }}</span>
            </div>

            <label class="block">
                <span :class="labelClass">{{ t('Paid by') }}<RequiredMark /></span>
                <select v-model="form.paid_by" :class="inputClass">
                    <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? `${member.name} ${t('(you)')}` : member.name }}</option>
                </select>
            </label>
        </section>

        <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <h2 class="font-bold">{{ t('Split') }}<RequiredMark /></h2>
            <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="tablist">
                <button
                    v-for="type in types"
                    :key="type.key"
                    type="button"
                    role="tab"
                    :aria-selected="form.split_type === type.key"
                    class="rounded-lg px-2 py-2 text-sm font-semibold transition"
                    :class="form.split_type === type.key ? 'bg-white shadow-sm dark:bg-slate-950' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                    @click="form.split_type = type.key"
                >
                    {{ t(type.label) }}
                </button>
            </div>
            <p class="text-xs text-slate-500">
                <template v-if="form.split_type === 'equal'">{{ t('Everyone ticked pays the same.') }}</template>
                <template v-else-if="form.split_type === 'exact'">{{ t('Write how much each person spent: it must add up to the total.') }}</template>
                <template v-else>{{ t('Example: 2 for who stayed 2 nights, 3 for who stayed 3. 0 = not in.') }}</template>
            </p>

            <button v-if="form.split_type === 'equal'" type="button" class="text-sm font-medium text-accent hover:underline" @click="toggleEveryone">
                {{ everyone ? t('Untick everyone') : t('Tick everyone') }}
            </button>

            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="member in members" :key="member.id" class="flex items-center gap-3 py-2">
                    <label class="flex min-w-0 flex-1 items-center gap-3" :class="form.split_type === 'equal' && 'cursor-pointer'">
                        <input v-if="form.split_type === 'equal'" v-model="values.equal[member.id]" type="checkbox" class="size-5 shrink-0 rounded accent-teal-600" />
                        <img v-if="member.avatar" :src="member.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-bold dark:bg-slate-700">{{ member.name.charAt(0).toUpperCase() }}</span>
                        <span class="truncate font-medium">{{ member.name }}<span v-if="member.id === me" class="ml-1 text-slate-500">{{ t('(you)') }}</span></span>
                    </label>

                    <span v-if="form.split_type === 'exact'" class="relative w-28 shrink-0">
                        <span class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-sm text-slate-500">{{ symbol }}</span>
                        <input v-model="values.exact[member.id]" inputmode="decimal" placeholder="0.00" :aria-label="t(':name\'s amount', { name: member.name })" :class="smallInputClass" class="w-full pl-9 text-right" />
                    </span>
                    <template v-else>
                        <input
                            v-if="form.split_type === 'shares'"
                            v-model.number="values.shares[member.id]"
                            type="number"
                            min="0"
                            max="1000"
                            step="1"
                            :aria-label="t(':name\'s shares', { name: member.name })"
                            :class="smallInputClass" class="w-20 shrink-0 text-center"
                        />
                        <span class="w-20 shrink-0 text-right text-sm tabular-nums" :class="preview[member.id] ? 'font-semibold' : 'text-slate-400'">
                            {{ preview[member.id] ? inCurrency(preview[member.id]) : '—' }}
                        </span>
                    </template>
                </li>
            </ul>

            <p v-if="exactLeft !== null && exactLeft !== 0" class="rounded-xl px-3 py-2 text-sm font-medium" :class="exactLeft > 0 ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'">
                {{ exactLeft > 0 ? t(':amount still to assign', { amount: inCurrency(exactLeft) }) : t(':amount too much', { amount: inCurrency(-exactLeft) }) }}
            </p>
            <p v-if="form.errors.split" class="text-sm text-red-600">{{ form.errors.split }}</p>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="rounded-2xl bg-brand px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ editing ? t('Save expense') : t('Add expense') }}
            </button>
            <button
                v-if="editing"
                type="button"
                class="ml-auto inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                @click="destroy"
            >
                <Trash2 class="size-4" /> {{ t('Delete') }}
            </button>
        </div>
    </form>
</template>

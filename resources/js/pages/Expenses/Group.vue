<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, ArrowRight, Check, ChevronDown, CircleCheck, HandCoins, Images, Pencil, Plus, Receipt, Settings, Trash2, X,
} from 'lucide-vue-next';
import { money } from '../../money.js';
import { t, tc } from '../../i18n.js';
import { formatDate } from '../../time.js';

const props = defineProps({
    group: Object,
    me: Number,
    members: Array,
    debts: Array,
    activity: Array,
    total: Number,
});

const myBalance = computed(() => props.members.find((m) => m.id === props.me)?.balance ?? 0);
const shortDate = { day: 'numeric', month: 'short' };
const longDate = { day: 'numeric', month: 'short', year: 'numeric' };
const thisYear = new Date().getFullYear();
const dateLabel = (date) => formatDate(date, new Date(date).getFullYear() === thisYear ? shortDate : longDate);

// What an expense means for me
function mine(expense) {
    if (expense.paidByMe) {
        const lent = expense.amount - (expense.myShare ?? 0);
        return lent ? { label: t('you lent'), amount: lent, class: 'text-teal-600 dark:text-teal-400' } : { label: t('just you'), amount: null };
    }
    return expense.myShare ? { label: t('you owe'), amount: expense.myShare, class: 'text-orange-600 dark:text-orange-400' } : { label: t('not in it'), amount: null };
}
const open = ref(null);

// "Paid ✓" on a debt of the simplified list: records exactly that payment
function markPaid(debt) {
    const who = debt.from.id === props.me ? t('you') : debt.from.name;
    const whom = debt.to.id === props.me ? t('you') : debt.to.name;
    if (window.confirm(t('Record that :who paid :whom :amount?', { who, whom, amount: money(debt.amount) }))) {
        router.post(`/expenses/${props.group.id}/payments`, {
            from_user_id: debt.from.id,
            to_user_id: debt.to.id,
            amount: (debt.amount / 100).toFixed(2),
        }, { preserveScroll: true });
    }
}

function deletePayment(payment) {
    if (window.confirm(t('Delete the payment ":from paid :to :amount"?', { from: payment.from, to: payment.to, amount: money(payment.amount) }))) {
        router.delete(`/expenses/${props.group.id}/payments/${payment.id}`, { preserveScroll: true });
    }
}

// "Settle up": any payment, filled in with what I owe if I owe something
const settling = ref(false);
const payment = useForm({ from_user_id: props.me, to_user_id: null, amount: '', paid_on: new Date().toLocaleDateString('en-CA') });
function settleUp() {
    const myDebt = props.debts.find((debt) => debt.from.id === props.me);
    payment.from_user_id = props.me;
    payment.to_user_id = myDebt?.to.id ?? props.members.find((m) => m.id !== props.me)?.id ?? null;
    payment.amount = myDebt ? (myDebt.amount / 100).toFixed(2) : '';
    payment.clearErrors();
    settling.value = true;
}
function savePayment() {
    payment.post(`/expenses/${props.group.id}/payments`, { preserveScroll: true, onSuccess: () => (settling.value = false) });
}
const onKey = (event) => event.key === 'Escape' && (settling.value = false);
window.addEventListener('keydown', onKey);
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const nameOf = (id) => (id === props.me ? t('You') : props.members.find((m) => m.id === id)?.name);
const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800';
const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700';
</script>

<template>
    <Head :title="group.name" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <Link href="/expenses" class="inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
            <ArrowLeft class="size-4" /> {{ t('Expenses') }}
        </Link>
        <Link
            v-if="group.canManage"
            :href="`/expenses/${group.id}/edit`"
            class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
        >
            <Settings class="size-4" /> {{ t('Edit group') }}
        </Link>
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end">
        <div class="min-w-0">
            <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ group.name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                <span>{{ tc(':count person|:count people', members.length) }} · {{ t(':amount spent', { amount: money(total) }) }}</span>
                <Link v-if="group.album" :href="`/listings/${group.album.id}`" class="inline-flex items-center gap-1 font-medium text-accent hover:underline">
                    <Images class="size-4" /> {{ group.album.title }}
                </Link>
            </p>
        </div>
        <div class="flex gap-2 sm:ml-auto">
            <button
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 sm:flex-none dark:bg-slate-800 dark:ring-slate-700"
                @click="settleUp"
            >
                <HandCoins class="size-4" /> {{ t('Settle up') }}
            </button>
            <Link
                :href="`/expenses/${group.id}/expenses/create`"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-dark sm:flex-none"
            >
                <Plus class="size-4" /> {{ t('Add expense') }}
            </Link>
        </div>
    </div>

    <!-- Phones: my balance and who pays whom first, then the list. Large screens: those on the right -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6 lg:col-start-2 lg:row-start-1">
            <section :class="cardClass">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('Your balance') }}</p>
                <p v-if="myBalance > 0" class="mt-1 text-3xl font-extrabold text-teal-600 dark:text-teal-400">+{{ money(myBalance) }}</p>
                <p v-else-if="myBalance < 0" class="mt-1 text-3xl font-extrabold text-orange-600 dark:text-orange-400">{{ money(myBalance) }}</p>
                <p v-else class="mt-1 flex items-center gap-2 text-2xl font-extrabold text-slate-500"><CircleCheck class="size-6" /> {{ t('Even') }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ myBalance > 0 ? t('Others owe you this.') : myBalance < 0 ? t('You owe this to others.') : group.settled ? t('Everyone is settled up.') : t("You're settled up.") }}
                </p>
            </section>

            <section :class="cardClass">
                <h2 class="mb-3 font-bold">{{ t('Who pays whom') }}</h2>
                <p v-if="!debts.length" class="flex items-center gap-2 text-sm text-slate-500"><CircleCheck class="size-4 text-teal-600" /> {{ t('Nobody owes anything.') }}</p>
                <ul class="space-y-2">
                    <li
                        v-for="debt in debts"
                        :key="`${debt.from.id}-${debt.to.id}`"
                        class="flex items-center gap-2 rounded-2xl p-3 text-sm"
                        :class="debt.from.id === me || debt.to.id === me ? 'bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-950/30 dark:ring-amber-900' : 'bg-slate-50 dark:bg-slate-800/60'"
                    >
                        <span class="min-w-0 flex-1">
                            <strong>{{ nameOf(debt.from.id) }}</strong>
                            <ArrowRight class="mx-1 inline size-3.5 text-slate-400" />
                            <strong>{{ nameOf(debt.to.id) }}</strong>
                            <span class="block font-bold text-slate-900 dark:text-white">{{ money(debt.amount) }}</span>
                        </span>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-xl bg-white px-3 py-1.5 font-semibold text-teal-700 shadow-sm ring-1 ring-slate-200 hover:bg-teal-50 dark:bg-slate-900 dark:text-teal-400 dark:ring-slate-700"
                            @click="markPaid(debt)"
                        >
                            <Check class="size-4" /> {{ t('Paid') }}
                        </button>
                    </li>
                </ul>
            </section>

            <section :class="cardClass">
                <h2 class="mb-3 font-bold">{{ t('Balances') }}</h2>
                <ul class="space-y-2">
                    <li v-for="member in members" :key="member.id" class="flex items-center gap-3 text-sm">
                        <img v-if="member.avatar" :src="member.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">{{ member.name.charAt(0).toUpperCase() }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium">{{ member.name }}<span v-if="member.id === me" class="ml-1 text-slate-500">{{ t('(you)') }}</span></span>
                        <span class="shrink-0 tabular-nums" :class="member.balance > 0 ? 'font-semibold text-teal-600 dark:text-teal-400' : member.balance < 0 ? 'font-semibold text-orange-600 dark:text-orange-400' : 'text-slate-400'">
                            {{ member.balance > 0 ? '+' : '' }}{{ member.balance ? money(member.balance) : t('even') }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="min-w-0 lg:col-start-1 lg:row-start-1">
            <h2 class="mb-3 text-lg font-bold">{{ t('Activity') }}</h2>
            <div v-if="!activity.length" class="rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <Receipt class="mx-auto size-10 text-slate-300" />
                <p class="mt-2 text-sm text-slate-500">{{ t('No expenses yet: add the first one.') }}</p>
            </div>
            <ul class="space-y-2">
                <li v-for="item in activity" :key="`${item.type}-${item.id}`" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <!-- Expense: click to see everyone's share -->
                    <template v-if="item.type === 'expense'">
                        <button type="button" class="flex w-full items-center gap-3 p-3 text-left" :aria-expanded="open === item.id" @click="open = open === item.id ? null : item.id">
                            <span class="w-12 shrink-0 text-center text-xs font-semibold text-slate-500 uppercase">{{ dateLabel(item.date) }}</span>
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800"><Receipt class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ item.description }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ item.paidByMe ? t('You paid :amount', { amount: money(item.amount) }) : t(':who paid :amount', { who: item.paidBy, amount: money(item.amount) }) }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs">
                                <span class="block text-slate-500">{{ mine(item).label }}</span>
                                <strong v-if="mine(item).amount" class="text-sm" :class="mine(item).class">{{ money(mine(item).amount) }}</strong>
                            </span>
                            <ChevronDown class="size-4 shrink-0 text-slate-400 transition" :class="open === item.id && 'rotate-180'" />
                        </button>
                        <div v-if="open === item.id" class="border-t border-slate-100 px-3 py-3 text-sm dark:border-slate-800">
                            <p class="mb-2 text-xs text-slate-500">
                                {{ item.splitType === 'equal' ? t('Split equally') : item.splitType === 'exact' ? t('Split by exact amounts') : t('Split by shares') }}
                            </p>
                            <ul class="space-y-1">
                                <li v-for="share in item.shares" :key="share.name" class="flex justify-between gap-3">
                                    <span class="truncate">{{ share.name }}<span v-if="share.shares" class="text-slate-500"> · {{ tc(':count share|:count shares', share.shares) }}</span></span>
                                    <span class="tabular-nums">{{ money(share.amount) }}</span>
                                </li>
                            </ul>
                            <Link
                                v-if="item.canEdit"
                                :href="`/expenses/${group.id}/expenses/${item.id}/edit`"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 font-medium ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800"
                            >
                                <Pencil class="size-4" /> {{ t('Edit') }}
                            </Link>
                        </div>
                    </template>

                    <!-- Payment -->
                    <div v-else class="flex items-center gap-3 p-3">
                        <span class="w-12 shrink-0 text-center text-xs font-semibold text-slate-500 uppercase">{{ dateLabel(item.date) }}</span>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950 dark:text-teal-400"><HandCoins class="size-5" /></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <strong>{{ item.from }}</strong> {{ t('paid') }} <strong>{{ item.to }}</strong>
                            <span class="block font-bold text-teal-600 dark:text-teal-400">{{ money(item.amount) }}</span>
                        </span>
                        <button v-if="item.canDelete" type="button" class="shrink-0 rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" :title="t('Delete payment')" @click="deletePayment(item)">
                            <Trash2 class="size-4" /><span class="sr-only">{{ t('Delete payment') }}</span>
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </div>

    <!-- Settle up -->
    <Teleport to="body">
        <div v-if="settling" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center" @click.self="settling = false">
            <form role="dialog" aria-modal="true" :aria-label="t('Settle up')" class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-5 shadow-xl dark:bg-slate-900" @submit.prevent="savePayment">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold">{{ t('Settle up') }}</h2>
                    <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="settling = false">
                        <X class="size-5" /><span class="sr-only">{{ t('Close') }}</span>
                    </button>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-end gap-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold">{{ t('Who paid') }}</span>
                        <select v-model="payment.from_user_id" :class="inputClass">
                            <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? t('You') : member.name }}</option>
                        </select>
                    </label>
                    <ArrowRight class="mb-3 size-4 text-slate-400" />
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold">{{ t('To') }}</span>
                        <select v-model="payment.to_user_id" :class="inputClass">
                            <option v-for="member in members" :key="member.id" :value="member.id">{{ member.id === me ? t('You') : member.name }}</option>
                        </select>
                    </label>
                </div>
                <p v-if="payment.errors.to_user_id" class="text-sm text-red-600">{{ t('Choose two different people.') }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold">{{ t('Amount') }}</span>
                        <span class="relative block">
                            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-semibold text-slate-500">€</span>
                            <input v-model="payment.amount" inputmode="decimal" required placeholder="0.00" :class="[inputClass, 'pl-8 font-semibold']" />
                        </span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-semibold">{{ t('Date') }}</span>
                        <input v-model="payment.paid_on" type="date" :class="inputClass" />
                    </label>
                </div>
                <p v-if="payment.errors.amount" class="text-sm text-red-600">{{ payment.errors.amount }}</p>
                <button type="submit" class="w-full rounded-2xl bg-brand px-4 py-3 font-semibold text-white hover:bg-brand-dark disabled:opacity-50" :disabled="payment.processing">
                    {{ t('Record payment') }}
                </button>
            </form>
        </div>
    </Teleport>
</template>

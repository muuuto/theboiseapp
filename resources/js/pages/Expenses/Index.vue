<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, Images, Plus, Wallet } from 'lucide-vue-next';
import { money } from '../../money.js';
import { ago } from '../../time.js';

defineProps({ groups: Array, totals: Object });
</script>

<template>
    <Head :title="t('Expenses')" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('Expenses') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('Split trips and bills, then settle up.') }}</p>
        </div>
        <Link
            href="/expenses/create"
            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark sm:ml-auto sm:py-2"
        >
            <Plus class="size-4" /> {{ t('New group') }}
        </Link>
    </div>

    <!-- Overall: what you get back and what you owe, in every group -->
    <div v-if="groups.length" class="mb-6 grid grid-cols-2 gap-3 sm:max-w-md">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('You get back') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-teal-600 dark:text-teal-400">{{ money(totals.owed) }}</p>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('You owe') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-orange-600 dark:text-orange-400">{{ money(totals.owe) }}</p>
        </div>
    </div>

    <div v-if="groups.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="group in groups"
            :key="group.id"
            class="group relative flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800"
        >
            <div class="flex items-start gap-3">
                <img v-if="group.album?.cover" :src="group.album.cover" alt="" class="size-14 shrink-0 rounded-xl object-cover" />
                <span v-else class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950 dark:text-teal-400">
                    <Wallet class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold leading-snug group-hover:text-accent">
                        <!-- The whole card is the link -->
                        <Link :href="`/expenses/${group.id}`" class="after:absolute after:inset-0">{{ group.name }}</Link>
                    </h2>
                    <p v-if="group.album" class="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-500">
                        <Images class="size-3.5 shrink-0" /> <span class="truncate">{{ group.album.title }}</span>
                    </p>
                    <p v-if="group.lastActivityAt" class="mt-0.5 text-xs text-slate-500">{{ t('Updated :when', { when: ago(group.lastActivityAt) }) }}</p>
                </div>
            </div>

            <div class="mt-auto flex items-end justify-between gap-2">
                <div class="flex -space-x-2">
                    <template v-for="member in group.members.slice(0, 5)" :key="member.id">
                        <img v-if="member.avatar" :src="member.avatar" :alt="member.name" :title="member.name" class="size-7 rounded-full object-cover ring-2 ring-white dark:ring-slate-900" />
                        <span v-else :title="member.name" class="flex size-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold ring-2 ring-white dark:bg-slate-700 dark:ring-slate-900">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </span>
                    </template>
                    <span v-if="group.members.length > 5" class="flex size-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold ring-2 ring-white dark:bg-slate-800 dark:ring-slate-900">
                        +{{ group.members.length - 5 }}
                    </span>
                </div>
                <p class="text-right text-sm">
                    <template v-if="group.myBalance > 0">
                        <span class="block text-xs text-slate-500">{{ t('you get back') }}</span>
                        <strong class="text-teal-600 dark:text-teal-400">{{ money(group.myBalance) }}</strong>
                    </template>
                    <template v-else-if="group.myBalance < 0">
                        <span class="block text-xs text-slate-500">{{ t('you owe') }}</span>
                        <strong class="text-orange-600 dark:text-orange-400">{{ money(-group.myBalance) }}</strong>
                    </template>
                    <span v-else class="inline-flex items-center gap-1 text-slate-500">
                        <CircleCheck class="size-4" /> {{ group.settled ? t('all settled') : "you're even" }}
                    </span>
                </p>
            </div>
        </article>
    </div>

    <div v-else class="mx-auto mt-10 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <Wallet class="mx-auto size-10 text-teal-600" />
        <h2 class="mt-3 text-lg font-bold">{{ t('No groups yet') }}</h2>
        <p class="mt-1 text-sm text-slate-500">
            {{ t('Make a group for a trip or the house, add who paid what, and TheBoise tells everyone who owes whom.') }}
        </p>
        <Link href="/expenses/create" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
            <Plus class="size-4" /> {{ t('Create the first group') }}
        </Link>
    </div>
</template>

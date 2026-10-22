<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Wrench } from 'lucide-vue-next';
import { ago, fullDate } from '../../time.js';

defineProps({ users: Array });
</script>

<template>
    <Head title="Monitor" />

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">Monitor</h1>
            <p class="text-sm text-slate-500">{{ users.length }} users, most recently on the site first</p>
        </div>
        <Link href="/admin/maintenance" class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700">
            <Wrench class="size-4" /> Maintenance
        </Link>
    </div>

    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <li v-for="user in users" :key="user.id" class="flex items-center gap-3 px-4 py-3">
                <img v-if="user.avatar" :src="user.avatar" alt="" class="size-9 shrink-0 rounded-full object-cover" />
                <span v-else class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-bold dark:bg-slate-700">
                    {{ user.name.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-semibold">
                        <span class="truncate">{{ user.name }}</span>
                        <span v-if="user.isAdmin" class="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-semibold text-brand">admin</span>
                        <span v-if="user.isDemo" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">demo</span>
                    </p>
                    <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                </div>
                <span class="shrink-0 text-sm text-slate-500" :title="user.lastLogin ? fullDate(user.lastLogin) : ''">
                    {{ user.lastLogin ? ago(user.lastLogin) : 'never' }}
                </span>
            </li>
        </ul>
    </div>
</template>

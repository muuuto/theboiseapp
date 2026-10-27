<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Search, Users } from 'lucide-vue-next';

// Everyone in the circle, the ones you share most albums with first
const props = defineProps({ people: Array });

const search = ref('');
const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const shown = computed(() => props.people.filter((person) => !search.value.trim() || plain(person.name).includes(plain(search.value.trim()))));
</script>

<template>
    <Head :title="t('People')" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="flex items-center gap-2 text-3xl font-extrabold tracking-tight"><Users class="size-7 text-accent" /> {{ t('People') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('Everyone in the circle: their numbers, their badges, and what you share.') }}</p>
        </div>
        <label class="relative block sm:ml-auto sm:w-64">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input v-model="search" type="search" :placeholder="t('Search people...')" class="h-10 w-full rounded-xl border-0 bg-white pr-3 pl-9 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700" />
        </label>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Link
            v-for="person in shown"
            :key="person.id"
            :href="`/people/${person.id}`"
            class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-900 dark:ring-slate-800"
        >
            <img v-if="person.avatar" :src="person.avatar" alt="" class="size-12 shrink-0 rounded-full object-cover" />
            <span v-else class="flex size-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-lg font-bold dark:bg-slate-700">{{ person.name.charAt(0).toUpperCase() }}</span>
            <span class="min-w-0">
                <span class="block truncate font-bold">{{ person.name }}<span v-if="person.me" class="ml-1 font-normal text-slate-500">{{ t('(you)') }}</span></span>
                <span class="block text-xs text-slate-500">
                    {{ tc(':count album|:count albums', person.albums) }}<template v-if="person.together"> · {{ tc(':count together|:count together', person.together) }}</template>
                </span>
            </span>
        </Link>
    </div>
</template>

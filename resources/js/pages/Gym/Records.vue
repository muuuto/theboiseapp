<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Trophy } from 'lucide-vue-next';
import { kg } from '../../gym.js';

// The records of the gym users who share them. Yours stay private until you switch it on
const props = defineProps({ sharing: Boolean, people: Array });

const saving = ref(false);
function setSharing(on) {
    saving.value = true;
    router.put('/gym/share', { on }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}

const medal = (index) => ['🥇', '🥈', '🥉'][index] ?? '';
</script>

<template>
    <Head :title="t('Gym records')" />

    <div class="mb-6 flex items-center gap-2">
        <Link href="/gym" class="-ml-2 rounded-full p-2 hover:bg-slate-200/70 dark:hover:bg-slate-800" :aria-label="t('Back to the gym')"><ArrowLeft class="size-5" /></Link>
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('Gym records') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('The strongest lifts of the gym users who share them.') }}</p>
        </div>
    </div>

    <button
        type="button"
        role="switch"
        :aria-checked="sharing"
        :disabled="saving"
        class="mb-6 flex w-full items-center gap-3 rounded-2xl bg-white p-4 text-left shadow-sm ring-1 transition sm:max-w-xl dark:bg-slate-900"
        :class="sharing ? 'ring-teal-300 dark:ring-teal-800' : 'ring-slate-200 dark:ring-slate-800'"
        @click="setSharing(!sharing)"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-semibold">{{ t('Show my records to the other gym users') }}</span>
            <span class="block text-sm text-slate-500">{{ sharing ? t('They see your best lifts here, never your workouts or notes.') : t('Off: your records are private.') }}</span>
        </span>
        <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition" :class="sharing ? 'bg-accent' : 'bg-slate-300 dark:bg-slate-600'">
            <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="sharing ? 'left-5.5' : 'left-0.5'" />
        </span>
    </button>

    <div v-if="people.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article v-for="person in people" :key="person.id" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="mb-3 flex items-center gap-3">
                <img v-if="person.avatar" :src="person.avatar" alt="" class="size-10 rounded-full object-cover" />
                <span v-else class="flex size-10 items-center justify-center rounded-full bg-slate-200 font-bold dark:bg-slate-700">{{ person.name.charAt(0).toUpperCase() }}</span>
                <h2 class="font-bold">{{ person.name }}<span v-if="person.me" class="ml-1 font-normal text-slate-500">{{ t('(you)') }}</span></h2>
            </div>
            <ol v-if="person.lifts.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="(lift, index) in person.lifts" :key="lift.exerciseId" class="flex items-baseline gap-2 py-1.5 text-sm">
                    <span class="w-5 shrink-0 text-center">{{ medal(index) }}</span>
                    <Link :href="`/gym/exercises/${lift.exerciseId}`" class="min-w-0 flex-1 hover:text-accent">{{ lift.exercise }}</Link>
                    <strong class="shrink-0 tabular-nums">{{ kg(lift.weight) }}</strong>
                    <span class="w-20 shrink-0 text-right text-xs text-slate-500 tabular-nums" :title="t('Estimated 1-rep max')"><template v-if="lift.oneRepMax">1RM {{ kg(lift.oneRepMax) }}</template></span>
                </li>
            </ol>
            <p v-else class="text-sm text-slate-500">{{ t('No lifts with weights yet.') }}</p>
        </article>
    </div>

    <div v-else class="mx-auto mt-6 max-w-md rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <Lock v-if="!sharing" class="mx-auto size-10 text-slate-400" />
        <Trophy v-else class="mx-auto size-10 text-amber-500" />
        <p class="mt-3 text-sm text-slate-500">{{ t('Nobody shares their records yet. Switch it on above to be the first.') }}</p>
    </div>
</template>

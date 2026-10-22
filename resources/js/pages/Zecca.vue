<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw, Volume2 } from 'lucide-vue-next';
import AppLayout from '../layouts/AppLayout.vue';
import GuestLayout from '../layouts/GuestLayout.vue';

// Public page: with the sidebar for friends logged in, the simple layout for everyone else
defineOptions({ layout: null });

const props = defineProps({ answer: String, broken: Boolean });

const user = computed(() => usePage().props.auth.user);
const audio = ref(null);

function play() {
    audio.value.currentTime = 0;
    audio.value.play().catch(() => {});
}

// Browsers may block sound until the first click: then the button is there
onMounted(() => !props.broken && play());
</script>

<template>
    <Head :title="t('Is Zecca gay?')" />

    <component :is="user ? AppLayout : GuestLayout">
        <div class="mx-auto max-w-xl text-center">
            <Link v-if="user" href="/" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
                <ArrowLeft class="size-4" /> {{ t('Albums') }}
            </Link>
            <div class="rounded-3xl bg-gradient-to-br from-teal-600 to-teal-700 px-6 py-10 text-white shadow-lg" :class="!user && '-m-6 sm:-m-8'">
                <h1 class="text-3xl font-extrabold uppercase">{{ t('Is Zecca gay??') }}</h1>
                <p class="mt-6 text-2xl font-bold uppercase">{{ answer }}</p>

                <button v-if="broken" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 font-semibold text-teal-700 hover:bg-teal-50" @click="router.reload()">
                    <RotateCcw class="size-4" /> {{ t('Try again') }}
                </button>
                <template v-else>
                    <audio ref="audio" src="/zue.mp3" preload="auto" />
                    <button class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 font-semibold text-teal-700 hover:bg-teal-50" @click="play">
                        <Volume2 class="size-4" /> {{ t('Try me') }}
                    </button>
                </template>
            </div>
        </div>
    </component>
</template>

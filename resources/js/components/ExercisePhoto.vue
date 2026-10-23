<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Dumbbell, X } from 'lucide-vue-next';

// An exercise's photo (start position); a tap shows how it's done: start and end, big
defineProps({ exercise: Object, size: { type: String, default: 'size-12' } });
const open = ref(false);

const closeOnEscape = (event) => event.key === 'Escape' && (open.value = false);
watch(open, (on) => (on ? window.addEventListener('keydown', closeOnEscape) : window.removeEventListener('keydown', closeOnEscape)));
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <button
        v-if="exercise.photos?.length"
        type="button"
        :title="t('How it\'s done')"
        class="shrink-0 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 transition hover:ring-accent dark:ring-slate-700"
        :class="size"
        @click.stop.prevent="open = true"
    >
        <img :src="exercise.photos[0]" alt="" loading="lazy" class="size-full object-cover" />
    </button>
    <span v-else class="flex shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950 dark:text-teal-400" :class="size">
        <Dumbbell class="size-1/2" />
    </span>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="open = false">
            <div class="w-full max-w-3xl rounded-3xl bg-white p-4 shadow-2xl dark:bg-slate-900">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="flex-1 text-lg font-bold">{{ exercise.label }}</h2>
                    <button type="button" class="rounded-full p-2 hover:bg-slate-100 dark:hover:bg-slate-800" :aria-label="t('Close')" @click="open = false"><X class="size-5" /></button>
                </div>
                <div class="grid gap-3" :class="exercise.photos.length > 1 && 'grid-cols-2'">
                    <figure v-for="(photo, index) in exercise.photos" :key="photo">
                        <img :src="photo" alt="" class="w-full rounded-2xl bg-white object-contain" />
                        <figcaption v-if="exercise.photos.length > 1" class="mt-1 text-center text-xs font-semibold text-slate-500">{{ index === 0 ? t('Start') : t('End') }}</figcaption>
                    </figure>
                </div>
            </div>
        </div>
    </Teleport>
</template>

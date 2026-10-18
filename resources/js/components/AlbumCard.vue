<script setup>
import { computed } from 'vue';
import { CalendarDays, MapPin } from 'lucide-vue-next';

// eager: the first cards are visible right away, load them immediately instead of lazily
const props = defineProps({ album: Object, eager: Boolean });
defineEmits(['tag']);

const formatter = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
const dates = computed(() => {
    const from = formatter.format(new Date(props.album.dateFrom));
    const to = formatter.format(new Date(props.album.dateTo));
    return from === to ? from : `${from} – ${to}`;
});
</script>

<template>
    <!-- Album pages are still Blade: a normal link -->
    <article class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800">
        <a :href="`/listings/${album.id}`" class="block">
            <div class="relative aspect-[4/3] overflow-hidden bg-slate-200 dark:bg-slate-800">
                <img
                    :src="album.cover"
                    :alt="album.title"
                    :loading="eager ? 'eager' : 'lazy'"
                    class="size-full object-cover transition duration-300 group-hover:scale-105"
                />
                <span v-if="album.isNew" class="absolute top-3 left-3 rounded-full bg-brand px-2.5 py-1 text-xs font-bold text-white shadow">
                    New
                </span>
            </div>

            <div class="space-y-2 p-4 pb-2">
                <h3 class="text-lg leading-snug font-bold group-hover:text-brand">{{ album.title }}</h3>
                <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <CalendarDays class="size-4 shrink-0" /> {{ dates }}
                </p>
                <p class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <MapPin class="size-4 shrink-0" /> {{ album.location }}
                </p>
            </div>
        </a>

        <ul class="flex flex-wrap gap-1.5 px-4 pt-1 pb-4">
            <li v-for="tag in album.tags" :key="tag">
                <button
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium transition"
                    :class="tag === 'Youtube'
                        ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-950 dark:text-red-300'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    @click="$emit('tag', tag)"
                >
                    {{ tag }}
                </button>
            </li>
        </ul>
    </article>
</template>

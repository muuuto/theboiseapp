<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CalendarHeart, ImagePlus } from 'lucide-vue-next';

const page = usePage();
const open = ref(false);
const root = ref(null);

const memories = computed(() => page.props.notifications?.memories ?? []);
const unseen = computed(() => page.props.notifications?.unseen ?? []);
const hasNews = computed(() => memories.value.length > 0 || unseen.value.length > 0);

const closeOnOutsideClick = (event) => {
    if (root.value && !root.value.contains(event.target)) open.value = false;
};
onMounted(() => document.addEventListener('click', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Bell class="size-5" />
            <span v-if="hasNews" class="absolute top-1.5 right-1.5 size-2 rounded-full bg-brand ring-2 ring-white dark:ring-slate-900" />
            <span class="sr-only">Notifications</span>
        </button>

        <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800"
            >
                <section class="p-2">
                    <h3 class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">On this day</h3>
                    <Link
                        v-for="memory in memories"
                        :key="`memory-${memory.id}`"
                        :href="`/listings/${memory.id}`"
                        class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700"
                    >
                        <CalendarHeart class="mt-0.5 size-4 shrink-0 text-brand" />
                        <span class="text-sm">
                            {{ memory.years }} {{ memory.years === 1 ? 'year' : 'years' }} ago:
                            <strong>{{ memory.title }}</strong>
                        </span>
                    </Link>
                    <p v-if="!memories.length" class="px-3 py-2 text-sm text-slate-400">No memories today</p>
                </section>

                <section class="border-t border-slate-100 p-2 dark:border-slate-700">
                    <h3 class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">New albums</h3>
                    <Link
                        v-for="album in unseen"
                        :key="`album-${album.id}`"
                        :href="`/listings/${album.id}`"
                        class="flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700"
                    >
                        <ImagePlus class="mt-0.5 size-4 shrink-0 text-accent" />
                        <span class="text-sm"><strong>{{ album.title }}</strong></span>
                    </Link>
                    <p v-if="!unseen.length" class="px-3 py-2 text-sm text-slate-400">No new albums</p>
                </section>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Check, Search } from 'lucide-vue-next';

// Who is in the album: a searchable grid of everyone, click to select
const selected = defineModel({ type: Array, default: () => [] });
const props = defineProps({ users: Array });

const query = ref('');
const visible = computed(() =>
    props.users.filter((user) => user.name.toLowerCase().includes(query.value.trim().toLowerCase())),
);

function toggle(id) {
    selected.value = selected.value.includes(id) ? selected.value.filter((existing) => existing !== id) : [...selected.value, id];
}
</script>

<template>
    <!-- @container: 3 columns only when the picker itself is wide enough (it also sits in narrow side columns) -->
    <div class="@container space-y-2">
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Search people..."
                    class="h-10 w-full rounded-xl border-0 bg-white pr-3 pl-9 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700"
                />
            </div>
            <span class="text-sm whitespace-nowrap text-slate-500">{{ selected.length }} selected</span>
        </div>

        <div class="grid max-h-72 grid-cols-2 gap-1.5 overflow-y-auto rounded-2xl bg-white p-2 ring-1 ring-slate-200 @lg:grid-cols-3 dark:bg-slate-900 dark:ring-slate-700">
            <button
                v-for="user in visible"
                :key="user.id"
                type="button"
                class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-left text-sm transition"
                :class="selected.includes(user.id)
                    ? 'bg-accent/15 font-semibold text-accent'
                    : 'hover:bg-slate-100 dark:hover:bg-slate-800'"
                :aria-pressed="selected.includes(user.id)"
                @click="toggle(user.id)"
            >
                <span class="relative shrink-0">
                    <img v-if="user.avatar" :src="user.avatar" alt="" class="size-7 rounded-full object-cover" />
                    <span v-else class="flex size-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </span>
                    <span v-if="selected.includes(user.id)" class="absolute -right-1 -bottom-1 rounded-full bg-accent p-0.5 text-white">
                        <Check class="size-2.5" />
                    </span>
                </span>
                <span class="truncate">{{ user.name }}</span>
                <span v-if="user.isDemo" class="ml-auto shrink-0 rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">demo</span>
            </button>
            <p v-if="!visible.length" class="col-span-full p-2 text-sm text-slate-500">Nobody found</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { X } from 'lucide-vue-next';

// Tags as chips. Enter or comma adds the typed tag; suggestions come from the tags already used
const tags = defineModel({ type: Array, default: () => [] });
const props = defineProps({ suggestions: { type: Array, default: () => [] } });

const text = ref('');
const matches = computed(() => {
    const query = text.value.trim().toLowerCase();
    if (!query) return [];
    return props.suggestions
        .filter((tag) => tag.toLowerCase().includes(query) && !tags.value.includes(tag))
        .slice(0, 8);
});

function add(tag) {
    const clean = tag.replace(/,/g, ' ').trim();
    if (clean && !tags.value.some((existing) => existing.toLowerCase() === clean.toLowerCase())) {
        tags.value = [...tags.value, clean];
    }
    text.value = '';
}

function onKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add(matches.value.length && event.key === 'Enter' && matches.value[0].toLowerCase().startsWith(text.value.trim().toLowerCase()) ? matches.value[0] : text.value);
    } else if (event.key === 'Backspace' && !text.value && tags.value.length) {
        tags.value = tags.value.slice(0, -1);
    }
}

function remove(tag) {
    tags.value = tags.value.filter((existing) => existing !== tag);
}
</script>

<template>
    <div class="relative">
        <div class="flex flex-wrap items-center gap-1.5 rounded-2xl bg-white px-3 py-2 ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-accent dark:bg-slate-900 dark:ring-slate-700">
            <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pr-1 pl-2.5 text-sm dark:bg-slate-800"
            >
                {{ tag }}
                <button type="button" class="rounded-full p-0.5 hover:bg-slate-200 dark:hover:bg-slate-700" @click="remove(tag)">
                    <X class="size-3.5" />
                    <span class="sr-only">{{ t('Remove :name', { name: tag }) }}</span>
                </button>
            </span>
            <input
                v-model="text"
                type="text"
                class="min-w-32 flex-1 border-0 bg-transparent py-1 text-sm focus:outline-none"
                :placeholder="t('Add a tag and press Enter')"
                @keydown="onKeydown"
                @blur="text.trim() && add(text)"
            />
        </div>
        <div v-if="matches.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <button
                v-for="match in matches"
                :key="match"
                type="button"
                class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                @mousedown.prevent="add(match)"
            >
                {{ match }}
            </button>
        </div>
    </div>
</template>

<script setup>
// A labelled input with its error message (v-model on the input)
const value = defineModel();
defineProps({
    label: String,
    error: String,
    type: { type: String, default: 'text' },
    autocomplete: String,
    hint: String,
});
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-sm font-semibold">{{ label }}</span>
        <input
            v-model="value"
            :type="type"
            :autocomplete="autocomplete"
            class="h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700"
            :class="error && 'ring-2 ring-red-500'"
        />
        <span v-if="hint && !error" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error }}</span>
    </label>
</template>

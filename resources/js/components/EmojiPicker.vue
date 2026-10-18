<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Smile } from 'lucide-vue-next';
import 'emoji-picker-element';
// Emoji data bundled with the site (by default the picker downloads it from a CDN)
import emojiData from 'emoji-picker-element-data/en/emojibase/data.json?url';

const emit = defineEmits(['pick']);
// "top" above the button (comment box), "bottom" below it (editor toolbar). On phones: centered at the bottom
const props = defineProps({ placement: { type: String, default: 'top' } });
const open = ref(false);
const root = ref(null);
const container = ref(null);
let picker;

onMounted(() => {
    picker = document.createElement('emoji-picker');
    picker.dataSource = emojiData;
    picker.addEventListener('emoji-click', (event) => {
        emit('pick', event.detail.unicode);
        open.value = false;
    });
    container.value.append(picker);
    document.addEventListener('click', closeOnOutsideClick);
});

// Match the site's light/dark mode
watch(open, (isOpen) => {
    if (isOpen) picker.className = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
});

function closeOnOutsideClick(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Smile class="size-5" />
            <span class="sr-only">Add an emoji</span>
        </button>
        <div
            v-show="open"
            ref="container"
            class="absolute z-40 overflow-hidden rounded-2xl shadow-xl max-sm:fixed max-sm:inset-x-0 max-sm:top-auto max-sm:bottom-3 max-sm:mx-auto max-sm:w-fit"
            :class="placement === 'bottom' ? 'top-full right-0 mt-2' : 'bottom-full left-0 mb-2'"
        />
    </div>
</template>

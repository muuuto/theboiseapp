<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { SendHorizontal } from 'lucide-vue-next';
import EmojiPicker from './EmojiPicker.vue';

// Plain text comment with emojis. Enter makes a new line, Ctrl/Cmd + Enter sends.
// `extra` are fields sent along with the comment (e.g. the album id).
// "@" suggests `people` (who can see it): picking one writes "@Their Name", and they're told
const props = defineProps({
    url: String,
    extra: { type: Object, default: () => ({}) },
    placeholder: { type: String, default: 'Write a comment...' },
    people: { type: Array, default: () => [] },
});

const form = useForm({ ...props.extra, comment: '' });
const textarea = ref(null);

function resize() {
    const field = textarea.value;
    field.style.height = 'auto';
    field.style.height = `${Math.min(field.scrollHeight, 240)}px`;
}

function addEmoji(emoji) {
    const field = textarea.value;
    const start = field.selectionStart ?? form.comment.length;
    const end = field.selectionEnd ?? form.comment.length;
    form.comment = form.comment.slice(0, start) + emoji + form.comment.slice(end);
    nextTick(() => {
        field.focus();
        field.setSelectionRange(start + emoji.length, start + emoji.length);
        resize();
    });
}

// --- @mentions ---
const query = ref(null); // the text after "@" being typed, null when not suggesting
const highlighted = ref(0);
const plain = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const suggestions = computed(() => {
    if (query.value === null) return [];
    const typed = plain(query.value);
    return props.people.filter((person) => plain(person.name).split(/\s+/).some((word) => word.startsWith(typed)) || plain(person.name).startsWith(typed)).slice(0, 6);
});
function lookForMention() {
    const field = textarea.value;
    const before = form.comment.slice(0, field.selectionStart ?? form.comment.length);
    const match = props.people.length ? before.match(/(?:^|\s)@([^\s@]{0,20})$/) : null;
    query.value = match ? match[1] : null;
    highlighted.value = 0;
}
// Leaving the box closes the list (after a tap on it has picked someone)
const closeSoon = () => setTimeout(() => (query.value = null), 150);
function mention(person) {
    const field = textarea.value;
    const caret = field.selectionStart ?? form.comment.length;
    const start = form.comment.lastIndexOf('@', caret - 1);
    const insert = `@${person.name} `;
    form.comment = form.comment.slice(0, start) + insert + form.comment.slice(caret);
    query.value = null;
    nextTick(() => {
        field.focus();
        field.setSelectionRange(start + insert.length, start + insert.length);
        resize();
    });
}

function submit() {
    if (!form.comment.trim()) return;
    form.post(props.url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('comment');
            nextTick(resize);
        },
    });
}

function onKeydown(event) {
    if (suggestions.value.length) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const count = suggestions.value.length;
            highlighted.value = (highlighted.value + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
            return;
        }
        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            mention(suggestions.value[highlighted.value]);
            return;
        }
        if (event.key === 'Escape') {
            query.value = null;
            return;
        }
    }
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) submit();
}
</script>

<template>
    <form class="relative rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-accent dark:bg-slate-900 dark:ring-slate-800" @submit.prevent="submit">
        <textarea
            ref="textarea"
            v-model="form.comment"
            rows="2"
            maxlength="5000"
            :placeholder="t(placeholder)"
            class="block w-full resize-none border-0 bg-transparent px-1 focus:outline-none"
            @input="resize(); lookForMention()"
            @click="lookForMention"
            @keydown="onKeydown"
            @blur="closeSoon"
        />
        <!-- @ suggestions: tap or arrows + Enter -->
        <ul v-if="suggestions.length" role="listbox" class="absolute bottom-full left-3 z-30 mb-1 w-64 max-w-[calc(100%-1.5rem)] overflow-hidden rounded-xl bg-white py-1 shadow-xl ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <li v-for="(person, index) in suggestions" :key="person.id" role="option" :aria-selected="index === highlighted">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
                    :class="index === highlighted ? 'bg-teal-50 dark:bg-slate-700' : 'hover:bg-slate-50 dark:hover:bg-slate-700'"
                    @mousedown.prevent="mention(person)"
                >
                    <img v-if="person.avatar" :src="person.avatar" alt="" class="size-6 rounded-full object-cover" />
                    <span v-else class="flex size-6 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-600">{{ person.name.charAt(0).toUpperCase() }}</span>
                    <span class="truncate">{{ person.name }}</span>
                </button>
            </li>
        </ul>
        <p v-if="form.errors.comment" class="px-1 text-sm text-red-600">{{ form.errors.comment }}</p>
        <div class="mt-2 flex items-center justify-between">
            <EmojiPicker @pick="addEmoji" />
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:opacity-40"
                :disabled="form.processing || !form.comment.trim()"
            >
                <SendHorizontal class="size-4" /> {{ t('Send') }}
            </button>
        </div>
    </form>
</template>

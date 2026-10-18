<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { SendHorizontal } from 'lucide-vue-next';
import EmojiPicker from './EmojiPicker.vue';

// Plain text comment with emojis. Enter makes a new line, Ctrl/Cmd + Enter sends.
// `extra` are fields sent along with the comment (e.g. the album id)
const props = defineProps({
    url: String,
    extra: { type: Object, default: () => ({}) },
    placeholder: { type: String, default: 'Write a comment...' },
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
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) submit();
}
</script>

<template>
    <form class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-accent dark:bg-slate-900 dark:ring-slate-800" @submit.prevent="submit">
        <textarea
            ref="textarea"
            v-model="form.comment"
            rows="2"
            maxlength="5000"
            :placeholder="placeholder"
            class="block w-full resize-none border-0 bg-transparent px-1 focus:outline-none"
            @input="resize"
            @keydown="onKeydown"
        />
        <p v-if="form.errors.comment" class="px-1 text-sm text-red-600">{{ form.errors.comment }}</p>
        <div class="mt-2 flex items-center justify-between">
            <EmojiPicker @pick="addEmoji" />
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:opacity-40"
                :disabled="form.processing || !form.comment.trim()"
            >
                <SendHorizontal class="size-4" /> Send
            </button>
        </div>
    </form>
</template>

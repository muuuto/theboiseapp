<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { TableKit } from '@tiptap/extension-table';
import EmojiPicker from './EmojiPicker.vue';
import {
    AlignCenter, AlignLeft, AlignRight, Bold, Heading2, Heading3, Highlighter, Italic, Link2, List, ListOrdered,
    Redo2, Strikethrough, Underline, Undo2,
} from 'lucide-vue-next';

// Rich text (HTML) for descriptions and forum posts. The extensions keep the formatting of the
// content written with the old TinyMCE editor: colors, highlights, font sizes, alignment, tables
const html = defineModel({ type: String, default: '' });
defineProps({ placeholder: String });

const editor = useEditor({
    content: html.value,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false, defaultProtocol: 'https' } }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight.configure({ multicolor: true }),
        TextStyleKit,
        TableKit,
    ],
    editorProps: {
        attributes: { class: 'rich-text min-h-48 px-4 py-3 focus:outline-none' },
    },
    onUpdate: ({ editor }) => {
        html.value = editor.isEmpty ? '' : editor.getHTML();
    },
});

// Content replaced from outside (e.g. the form was reset)
watch(html, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(value === '' && editor.value.isEmpty)) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    const previous = editor.value.getAttributes('link').href || '';
    const url = window.prompt('Link (empty to remove it)', previous);
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}

const buttons = [
    { icon: Bold, label: 'Bold', run: (c) => c.toggleBold(), active: 'bold' },
    { icon: Italic, label: 'Italic', run: (c) => c.toggleItalic(), active: 'italic' },
    { icon: Underline, label: 'Underline', run: (c) => c.toggleUnderline(), active: 'underline' },
    { icon: Strikethrough, label: 'Strikethrough', run: (c) => c.toggleStrike(), active: 'strike' },
    { separator: true },
    { icon: Heading2, label: 'Heading', run: (c) => c.toggleHeading({ level: 2 }), active: ['heading', { level: 2 }] },
    { icon: Heading3, label: 'Small heading', run: (c) => c.toggleHeading({ level: 3 }), active: ['heading', { level: 3 }] },
    { icon: List, label: 'Bullet list', run: (c) => c.toggleBulletList(), active: 'bulletList' },
    { icon: ListOrdered, label: 'Numbered list', run: (c) => c.toggleOrderedList(), active: 'orderedList' },
    { separator: true },
    { icon: AlignLeft, label: 'Align left', run: (c) => c.setTextAlign('left'), active: [{ textAlign: 'left' }] },
    { icon: AlignCenter, label: 'Center', run: (c) => c.setTextAlign('center'), active: [{ textAlign: 'center' }] },
    { icon: AlignRight, label: 'Align right', run: (c) => c.setTextAlign('right'), active: [{ textAlign: 'right' }] },
    { icon: Highlighter, label: 'Highlight', run: (c) => c.toggleHighlight({ color: '#fef08a' }), active: 'highlight' },
    { icon: Link2, label: 'Link', custom: setLink, active: 'link' },
    { separator: true },
    { icon: Undo2, label: 'Undo', run: (c) => c.undo() },
    { icon: Redo2, label: 'Redo', run: (c) => c.redo() },
];

// Emojis go where the cursor is (useful on PCs, which have no emoji keyboard)
function insertEmoji(emoji) {
    editor.value.chain().focus().insertContent(emoji).run();
}

function press(button) {
    if (button.custom) return button.custom();
    button.run(editor.value.chain().focus()).run();
}

function isActive(button) {
    if (!button.active || !editor.value) return false;
    return Array.isArray(button.active) ? editor.value.isActive(...button.active) : editor.value.isActive(button.active);
}
</script>

<template>
    <!-- No overflow-hidden: the emoji picker opens below the toolbar -->
    <div class="rounded-2xl bg-white ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-accent dark:bg-slate-900 dark:ring-slate-700">
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 rounded-t-2xl border-b border-slate-200 px-2 py-1.5 dark:border-slate-700">
            <template v-for="(button, index) in buttons" :key="index">
                <span v-if="button.separator" class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700" />
                <button
                    v-else
                    type="button"
                    class="rounded-lg p-1.5 transition"
                    :class="isActive(button)
                        ? 'bg-accent/15 text-accent'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'"
                    :title="button.label"
                    @click="press(button)"
                >
                    <component :is="button.icon" class="size-4" />
                    <span class="sr-only">{{ button.label }}</span>
                </button>
            </template>
            <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700" />
            <EmojiPicker placement="bottom" @pick="insertEmoji" />
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { t, tc } from '../i18n.js';
import EmojiPicker from './EmojiPicker.vue';

// Emoji reactions to an album or a forum post, one each: tap an emoji to react, the same one
// again to take it back, another to change it. Six quick ones, any other from the picker.
// reactions: { list: [{ emoji, count, names }], mine, canReact }
const props = defineProps({ type: String, id: Number, reactions: Object });

const QUICK = ['❤️', '😂', '😮', '😢', '🔥', '👏'];

// Changed right away on the page, then saved (the page's data comes back from the server)
const list = ref([]);
const mine = ref(null);
watch(() => props.reactions, (reactions) => {
    list.value = reactions.list.map((reaction) => ({ ...reaction, names: [...reaction.names] }));
    mine.value = reactions.mine;
}, { immediate: true, deep: true });

const me = t('You');
function react(emoji) {
    if (!props.reactions.canReact) return;
    const previous = mine.value;
    // Take mine off the emoji I had
    if (previous) {
        const old = list.value.find((reaction) => reaction.emoji === previous);
        if (old) {
            old.count--;
            old.names = old.names.filter((name) => name !== me);
        }
    }
    mine.value = previous === emoji ? null : emoji;
    if (mine.value) {
        const reaction = list.value.find((item) => item.emoji === emoji);
        if (reaction) {
            reaction.count++;
            reaction.names.push(me);
        } else {
            list.value.push({ emoji, count: 1, names: [me] });
        }
    }
    list.value = list.value.filter((reaction) => reaction.count > 0);
    router.post('/reactions', { type: props.type, id: props.id, emoji }, { preserveScroll: true, preserveState: true, only: ['reactions'] });
}

// The quick ones nobody used yet, as buttons after the ones in use
const unused = computed(() => (props.reactions.canReact ? QUICK.filter((emoji) => !list.value.some((reaction) => reaction.emoji === emoji)) : []));
const total = computed(() => list.value.reduce((sum, reaction) => sum + reaction.count, 0));
const names = computed(() => [...new Set(list.value.flatMap((reaction) => reaction.names))]);
const summary = computed(() => {
    const shown = names.value.slice(0, 2);
    const others = names.value.length - shown.length;
    return others > 0 ? tc(':names and :count other|:names and :count others', others, { names: shown.join(', ') }) : shown.join(t(' and '));
});
const showWho = ref(false);
</script>

<template>
    <div v-if="reactions.canReact || total" class="space-y-2">
        <div class="flex flex-wrap items-center gap-1.5">
            <button
                v-for="reaction in list"
                :key="reaction.emoji"
                type="button"
                class="inline-flex h-9 items-center gap-1 rounded-full px-2.5 text-sm font-semibold ring-1 transition"
                :class="[
                    reaction.emoji === mine
                        ? 'bg-teal-50 text-teal-800 ring-teal-300 dark:bg-teal-950/50 dark:text-teal-200 dark:ring-teal-800'
                        : 'bg-white text-slate-700 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700',
                    reactions.canReact ? 'hover:ring-teal-400 active:scale-95' : 'cursor-default',
                ]"
                :title="reaction.names.join(', ')"
                :aria-pressed="reaction.emoji === mine"
                :aria-label="t(':emoji: :names', { emoji: reaction.emoji, names: reaction.names.join(', ') })"
                @click="react(reaction.emoji)"
            >
                <span class="text-lg leading-none">{{ reaction.emoji }}</span>
                <span class="tabular-nums">{{ reaction.count }}</span>
            </button>
            <button
                v-for="emoji in unused"
                :key="emoji"
                type="button"
                class="flex size-9 items-center justify-center rounded-full text-lg opacity-60 grayscale-[40%] transition hover:bg-slate-100 hover:opacity-100 hover:grayscale-0 active:scale-90 dark:hover:bg-slate-800"
                :aria-label="t('React with :emoji', { emoji })"
                @click="react(emoji)"
            >
                {{ emoji }}
            </button>
            <EmojiPicker v-if="reactions.canReact" placement="bottom" @pick="react" />
        </div>

        <!-- Who reacted: tap to see each person's emoji -->
        <div v-if="total" class="text-sm text-slate-500">
            <button type="button" class="hover:text-slate-800 hover:underline dark:hover:text-slate-200" :aria-expanded="showWho" @click="showWho = !showWho">
                {{ summary }}
            </button>
            <ul v-if="showWho" class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                <template v-for="reaction in list" :key="reaction.emoji">
                    <li v-for="name in reaction.names" :key="`${reaction.emoji}-${name}`" class="text-slate-700 dark:text-slate-300">{{ reaction.emoji }} {{ name }}</li>
                </template>
            </ul>
        </div>
    </div>
</template>

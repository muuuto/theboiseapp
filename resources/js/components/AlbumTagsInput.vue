<script setup>
import { computed, ref } from 'vue';
import { Plus, Sparkles, X } from 'lucide-vue-next';
import { t } from '../i18n.js';

// Album tags from the library: everyone writes a tag the same way ("Matteo", never "Matte").
// Typing suggests the library's tags (an alias gives its tag); a name the library doesn't have
// asks first "did you mean…?", and becomes a new tag only when confirmed (kept in `confirmed`,
// the server checks the same)
const tags = defineModel({ type: Array, default: () => [] });
const confirmed = defineModel('confirmed', { type: Array, default: () => [] });
const props = defineProps({ library: { type: Array, default: () => [] } }); // [{ name, aliases, userId }]

const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
const spellings = (tag) => [tag.name, ...(tag.aliases ?? [])];

// Same as the server (Tags::resolve / Tags::similar)
function resolve(typed) {
    const wanted = plain(typed);
    return props.library.find((tag) => tag.name === typed.trim())
        ?? props.library.find((tag) => plain(tag.name) === wanted)
        ?? props.library.find((tag) => (tag.aliases ?? []).some((alias) => plain(alias) === wanted));
}
function distance(a, b) {
    const row = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        let previous = row[0];
        row[0] = i;
        for (let j = 1; j <= b.length; j++) {
            const current = row[j];
            row[j] = Math.min(row[j] + 1, row[j - 1] + 1, previous + (a[i - 1] === b[j - 1] ? 0 : 1));
            previous = current;
        }
    }
    return row[b.length];
}
function similar(typed) {
    const wanted = plain(typed);
    return props.library.filter((tag) => spellings(tag).some((spelling) => {
        const other = plain(spelling);
        if (other === wanted) return true;
        if (Math.min(other.length, wanted.length) >= 3 && (other.startsWith(wanted) || wanted.startsWith(other))) return true;
        return distance(other, wanted) <= (Math.max(other.length, wanted.length) >= 6 ? 2 : 1);
    }));
}

const text = ref('');
const pending = ref(null); // { typed, similar } while asking about a name the library doesn't have
const has = (name) => tags.value.some((tag) => plain(tag) === plain(name));

// Suggestions while typing: by name or alias, not the ones already in
const matches = computed(() => {
    const query = plain(text.value);
    if (!query) return [];
    return props.library
        .filter((tag) => !has(tag.name) && spellings(tag).some((spelling) => plain(spelling).includes(query)))
        .slice(0, 8)
        .map((tag) => ({ name: tag.name, alias: plain(tag.name).includes(query) ? null : (tag.aliases ?? []).find((alias) => plain(alias).includes(query)) }));
});

// Shown A to Z, like everywhere (capitals and accents don't count)
const sorted = (list) => [...list].sort((a, b) => plain(a).localeCompare(plain(b)) || a.localeCompare(b));
function addName(name) {
    if (!has(name)) tags.value = sorted([...tags.value, name]);
    text.value = '';
    pending.value = null;
}
function submitText() {
    const typed = text.value.replace(/,/g, ' ').trim();
    if (!typed) return;
    const tag = resolve(typed);
    if (tag) return addName(tag.name);
    pending.value = { typed, similar: similar(typed) };
}
function addNew() {
    const name = pending.value.typed;
    if (!confirmed.value.some((tag) => plain(tag) === plain(name))) confirmed.value = [...confirmed.value, name];
    addName(name);
}

function onKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        const first = matches.value[0];
        if (event.key === 'Enter' && first && plain(first.alias ?? first.name).startsWith(plain(text.value))) addName(first.name);
        else submitText();
    } else if (event.key === 'Escape') {
        pending.value = null;
    } else if (event.key === 'Backspace' && !text.value && tags.value.length) {
        remove(tags.value.at(-1));
    }
}

function remove(name) {
    tags.value = tags.value.filter((tag) => tag !== name);
    confirmed.value = confirmed.value.filter((tag) => tag !== name);
}
const isNew = (name) => confirmed.value.includes(name) && !resolve(name);
</script>

<template>
    <div class="relative">
        <div class="flex flex-wrap items-center gap-1.5 rounded-2xl bg-white px-3 py-2 ring-1 ring-slate-200 focus-within:ring-2 focus-within:ring-accent dark:bg-slate-900 dark:ring-slate-700">
            <span
                v-for="tag in sorted(tags)"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-full py-0.5 pr-1 pl-2.5 text-sm"
                :class="isNew(tag) ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' : 'bg-slate-100 dark:bg-slate-800'"
                :title="isNew(tag) ? t('New tag') : null"
            >
                <Sparkles v-if="isNew(tag)" class="size-3.5" />
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
                :placeholder="t('Who was there? Type a name')"
                @keydown="onKeydown"
                @input="pending = null"
            />
        </div>

        <div v-if="matches.length && !pending" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <button
                v-for="match in matches"
                :key="match.name"
                type="button"
                class="flex w-full items-baseline gap-2 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                @mousedown.prevent="addName(match.name)"
            >
                {{ match.name }}<span v-if="match.alias" class="text-xs text-slate-500">({{ match.alias }})</span>
            </button>
            <button v-if="!resolve(text)" type="button" class="flex w-full items-center gap-1.5 border-t border-slate-100 px-3 py-2 text-left text-sm font-medium text-accent hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-700" @mousedown.prevent="submitText">
                <Plus class="size-4" /> {{ t('Add ":name"', { name: text.trim() }) }}
            </button>
        </div>

        <!-- A name the library doesn't have: did you mean…? -->
        <div v-if="pending" class="mt-2 space-y-2 rounded-2xl bg-amber-50 p-3 text-sm dark:bg-amber-950/40">
            <p class="font-medium text-amber-900 dark:text-amber-200">
                {{ pending.similar.length ? t('":name" isn\'t a tag yet. Did you mean:', { name: pending.typed }) : t('":name" isn\'t a tag yet.', { name: pending.typed }) }}
            </p>
            <div v-if="pending.similar.length" class="flex flex-wrap gap-1.5">
                <button v-for="tag in pending.similar" :key="tag.name" type="button" class="rounded-full bg-white px-3 py-1 font-semibold ring-1 ring-amber-200 hover:ring-amber-400 dark:bg-slate-900 dark:ring-amber-900" @click="addName(tag.name)">
                    {{ tag.name }}
                </button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700" @click="addNew">
                    <Plus class="size-4" /> {{ pending.similar.length ? t('No, add ":name" as someone new', { name: pending.typed }) : t('Add ":name" as a new tag', { name: pending.typed }) }}
                </button>
                <button type="button" class="rounded-xl px-3 py-1.5 font-semibold text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-900/40" @click="pending = null; text = ''">{{ t('Cancel') }}</button>
            </div>
        </div>
    </div>
</template>

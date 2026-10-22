<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownUp, ChevronDown, ChevronUp, GripVertical, MessageSquareText, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ago } from '../../time.js';
import { useReorder } from '../../reorder.js';

const props = defineProps({ categories: Array, canReorder: Boolean });

// Admins: reorder the categories
const reorder = useReorder(() => props.categories);
const container = reorder.container;
const list = computed(() => (reorder.active.value ? reorder.items.value : props.categories));
const saving = ref(false);
function saveOrder() {
    saving.value = true;
    router.put('/forum/order', { ids: reorder.ids() }, {
        preserveScroll: true,
        onSuccess: () => reorder.stop(),
        onFinish: () => (saving.value = false),
    });
}

function destroy(category) {
    if (window.confirm(`Delete the category "${category.title}" and all its posts? This can't be undone.`)) {
        router.delete(`/forum/${category.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Forum" />

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight">Forum</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ categories.length }} categories</p>
        </div>
        <div v-if="!reorder.active.value" class="flex gap-2 sm:ml-auto">
            <button
                v-if="canReorder && categories.length > 1"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 sm:flex-none sm:py-2 dark:bg-slate-800 dark:ring-slate-700"
                @click="reorder.start()"
            >
                <ArrowDownUp class="size-4" /> Reorder
            </button>
            <Link
                href="/forum/category/create"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-forum px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 sm:flex-none sm:py-2"
            >
                <Plus class="size-4" /> New category
            </Link>
        </div>
    </div>

    <!-- Reorder mode: sticky so Save is always at hand, also at the bottom of a long list -->
    <div
        v-if="reorder.active.value"
        class="sticky top-[calc(env(safe-area-inset-top,0px)+4.5rem)] z-20 mb-4 flex flex-wrap items-center gap-3 rounded-2xl bg-forum/10 p-3 ring-1 ring-forum/30 backdrop-blur dark:bg-forum/20"
    >
        <p class="min-w-48 flex-1 text-sm">
            Drag the categories by <GripVertical class="inline size-4" /> or use the arrows. Everyone sees this order.
        </p>
        <div class="flex gap-2">
            <button class="rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-slate-300 hover:bg-white dark:ring-slate-600 dark:hover:bg-slate-800" @click="reorder.stop()">Cancel</button>
            <button class="rounded-xl bg-forum px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50" :disabled="saving" @click="saveOrder">
                {{ saving ? 'Saving…' : 'Save order' }}
            </button>
        </div>
    </div>

    <div v-if="categories.length" ref="container" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="(category, index) in list"
            :key="category.id"
            class="group relative flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition dark:bg-slate-900 dark:ring-slate-800"
            :class="reorder.active.value ? 'items-center gap-3 select-none' : 'hover:-translate-y-0.5 hover:shadow-lg'"
        >
            <button
                v-if="reorder.active.value"
                data-drag-handle
                class="-ml-2 shrink-0 cursor-grab touch-none rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 active:cursor-grabbing dark:hover:bg-slate-800"
                title="Drag to move"
            >
                <GripVertical class="size-5" /><span class="sr-only">Drag {{ category.title }}</span>
            </button>
            <img :src="category.logo" alt="" class="size-16 shrink-0 rounded-xl object-cover" />
            <div class="min-w-0 flex-1">
                <h2 v-if="reorder.active.value" class="font-bold leading-snug">{{ category.title }}</h2>
                <h2 v-else class="font-bold leading-snug group-hover:text-forum">
                    <!-- The whole card is the link -->
                    <Link :href="`/forum/${category.id}`" class="after:absolute after:inset-0">{{ category.title }}</Link>
                </h2>
                <p class="mt-0.5 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ category.description }}</p>
                <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <MessageSquareText class="size-3.5" />
                    {{ category.postCount }} {{ category.postCount === 1 ? 'post' : 'posts' }}
                    <template v-if="category.lastPostAt"> · last {{ ago(category.lastPostAt) }}</template>
                </p>
            </div>

            <div v-if="reorder.active.value" class="flex shrink-0 flex-col">
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="index === 0" title="Move up" @click="reorder.move(index, index - 1)">
                    <ChevronUp class="size-5" /><span class="sr-only">Move {{ category.title }} up</span>
                </button>
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="index === list.length - 1" title="Move down" @click="reorder.move(index, index + 1)">
                    <ChevronDown class="size-5" /><span class="sr-only">Move {{ category.title }} down</span>
                </button>
            </div>

            <!-- Above the card link -->
            <div v-else-if="category.canEdit || category.canDelete" class="relative z-10 flex shrink-0 flex-col gap-1">
                <Link v-if="category.canEdit" :href="`/forum/${category.id}/edit`" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" title="Edit">
                    <Pencil class="size-4" /><span class="sr-only">Edit {{ category.title }}</span>
                </Link>
                <button v-if="category.canDelete" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="Delete" @click="destroy(category)">
                    <Trash2 class="size-4" /><span class="sr-only">Delete {{ category.title }}</span>
                </button>
            </div>
        </article>
    </div>
    <p v-else class="mt-10 text-center text-slate-500">No categories yet: create the first one.</p>
</template>

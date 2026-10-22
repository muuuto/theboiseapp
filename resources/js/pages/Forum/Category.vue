<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownUp, ArrowLeft, ChevronDown, ChevronUp, Eye, GripVertical, Lock, MessageCircle, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ago, fullDate } from '../../time.js';
import { useReorder } from '../../reorder.js';

const props = defineProps({
    category: Object,
    posts: Array,
    canReorder: Boolean,
});

// Admins: reorder the posts
const reorder = useReorder(() => props.posts);
const container = reorder.container;
const list = computed(() => (reorder.active.value ? reorder.items.value : props.posts));
const saving = ref(false);
function saveOrder() {
    saving.value = true;
    router.put(`/forum/${props.category.id}/order`, { ids: reorder.ids() }, {
        preserveScroll: true,
        onSuccess: () => reorder.stop(),
        onFinish: () => (saving.value = false),
    });
}

function destroy(post) {
    if (window.confirm(`Delete the post "${post.title}"? This can't be undone.`)) {
        router.delete(`/forum/${props.category.id}/${post.id}/delete`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="category.title" />

    <Link href="/forum" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> Forum
    </Link>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex min-w-0 items-center gap-4">
            <img :src="category.logo" alt="" class="size-16 shrink-0 rounded-2xl object-cover shadow-sm" />
            <div class="min-w-0">
                <h1 class="text-3xl font-extrabold tracking-tight">{{ category.title }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ category.description }}</p>
            </div>
        </div>
        <div v-if="!reorder.active.value" class="flex flex-wrap gap-2 sm:ml-auto">
            <button
                v-if="canReorder && posts.length > 1"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 sm:flex-none sm:py-2 dark:bg-slate-800 dark:ring-slate-700"
                @click="reorder.start()"
            >
                <ArrowDownUp class="size-4" /> Reorder
            </button>
            <Link
                v-if="category.canEdit"
                :href="`/forum/${category.id}/edit`"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 sm:flex-none sm:py-2 dark:bg-slate-800 dark:ring-slate-700"
            >
                <Pencil class="size-4" /> Edit category
            </Link>
            <Link
                :href="`/forum/${category.id}/create`"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-forum px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 sm:flex-none sm:py-2"
            >
                <Plus class="size-4" /> New post
            </Link>
        </div>
    </div>

    <!-- Reorder mode: sticky so Save is always at hand, also at the bottom of a long list -->
    <div
        v-if="reorder.active.value"
        class="sticky top-[calc(env(safe-area-inset-top,0px)+4.5rem)] z-20 mb-4 flex flex-wrap items-center gap-3 rounded-2xl bg-forum/10 p-3 ring-1 ring-forum/30 backdrop-blur dark:bg-forum/20"
    >
        <p class="min-w-48 flex-1 text-sm">
            Drag the posts by <GripVertical class="inline size-4" /> or use the arrows. Everyone sees this order.
        </p>
        <div class="flex gap-2">
            <button class="rounded-xl px-4 py-2 text-sm font-semibold ring-1 ring-slate-300 hover:bg-white dark:ring-slate-600 dark:hover:bg-slate-800" @click="reorder.stop()">Cancel</button>
            <button class="rounded-xl bg-forum px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50" :disabled="saving" @click="saveOrder">
                {{ saving ? 'Saving…' : 'Save order' }}
            </button>
        </div>
    </div>

    <div v-if="posts.length" ref="container" class="space-y-3">
        <article
            v-for="(post, index) in list"
            :key="post.id"
            class="group relative flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition sm:p-4 dark:bg-slate-900 dark:ring-slate-800"
            :class="reorder.active.value ? 'items-center gap-3 select-none' : 'hover:shadow-lg'"
        >
            <button
                v-if="reorder.active.value"
                data-drag-handle
                class="-ml-1 shrink-0 cursor-grab touch-none rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 active:cursor-grabbing dark:hover:bg-slate-800"
                title="Drag to move"
            >
                <GripVertical class="size-5" /><span class="sr-only">Drag {{ post.title }}</span>
            </button>
            <img v-if="post.cover" :src="post.cover" alt="" loading="lazy" class="size-20 shrink-0 rounded-xl object-cover sm:size-28" />
            <div class="min-w-0 flex-1">
                <h2 class="flex items-center gap-2 font-bold leading-snug sm:text-lg" :class="{ 'group-hover:text-forum': !reorder.active.value }">
                    <Lock v-if="post.isPrivate" class="size-4 shrink-0 text-amber-600" title="Only you can see it" />
                    <span v-if="reorder.active.value">{{ post.title }}</span>
                    <Link v-else :href="`/forum/${category.id}/${post.id}`" class="after:absolute after:inset-0">{{ post.title }}</Link>
                </h2>
                <p class="mt-0.5 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ post.description }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span><strong class="font-semibold text-slate-700 dark:text-slate-300">{{ post.author }}</strong> · <time :datetime="post.createdAt" :title="fullDate(post.createdAt)">{{ ago(post.createdAt) }}</time></span>
                    <span class="inline-flex items-center gap-1"><Eye class="size-3.5" /> {{ post.views }}</span>
                    <span class="inline-flex items-center gap-1"><MessageCircle class="size-3.5" /> {{ post.comments }}</span>
                    <span v-for="tag in post.tags" :key="tag" class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ tag }}</span>
                </div>
            </div>

            <div v-if="reorder.active.value" class="flex shrink-0 flex-col">
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="index === 0" title="Move up" @click="reorder.move(index, index - 1)">
                    <ChevronUp class="size-5" /><span class="sr-only">Move {{ post.title }} up</span>
                </button>
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" :disabled="index === list.length - 1" title="Move down" @click="reorder.move(index, index + 1)">
                    <ChevronDown class="size-5" /><span class="sr-only">Move {{ post.title }} down</span>
                </button>
            </div>

            <div v-else-if="post.canEdit || post.canDelete" class="relative z-10 flex shrink-0 flex-col gap-1">
                <Link v-if="post.canEdit" :href="`/forum/${category.id}/${post.id}/edit`" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" title="Edit">
                    <Pencil class="size-4" /><span class="sr-only">Edit {{ post.title }}</span>
                </Link>
                <button v-if="post.canDelete" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" title="Delete" @click="destroy(post)">
                    <Trash2 class="size-4" /><span class="sr-only">Delete {{ post.title }}</span>
                </button>
            </div>
        </article>
    </div>
    <p v-else class="mt-10 text-center text-slate-500">No posts yet: write the first one.</p>
</template>

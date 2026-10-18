<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Lock, MessageCircle, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ago, fullDate } from '../../time.js';

const props = defineProps({
    category: Object,
    posts: Array,
});

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
        <div class="flex gap-2 sm:ml-auto">
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

    <div v-if="posts.length" class="space-y-3">
        <article
            v-for="post in posts"
            :key="post.id"
            class="group relative flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 transition hover:shadow-lg sm:p-4 dark:bg-slate-900 dark:ring-slate-800"
        >
            <img v-if="post.cover" :src="post.cover" alt="" loading="lazy" class="size-20 shrink-0 rounded-xl object-cover sm:size-28" />
            <div class="min-w-0 flex-1">
                <h2 class="flex items-center gap-2 font-bold leading-snug group-hover:text-forum sm:text-lg">
                    <Lock v-if="post.isPrivate" class="size-4 shrink-0 text-amber-600" title="Only you can see it" />
                    <Link :href="`/forum/${category.id}/${post.id}`" class="after:absolute after:inset-0">{{ post.title }}</Link>
                </h2>
                <p class="mt-0.5 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ post.description }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span><strong class="font-semibold text-slate-700 dark:text-slate-300">{{ post.author }}</strong> · <time :datetime="post.createdAt" :title="fullDate(post.createdAt)">{{ ago(post.createdAt) }}</time></span>
                    <span class="inline-flex items-center gap-1"><Eye class="size-3.5" /> {{ post.views }}</span>
                    <span class="inline-flex items-center gap-1"><MessageCircle class="size-3.5" /> {{ post.comments }}</span>
                    <span v-for="tag in post.tags" :key="tag" class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ tag }}</span>
                </div>
            </div>

            <div v-if="post.canEdit || post.canDelete" class="relative z-10 flex shrink-0 flex-col gap-1">
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

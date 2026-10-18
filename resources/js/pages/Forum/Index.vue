<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageSquareText, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ago } from '../../time.js';

defineProps({ categories: Array });

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
        <Link
            href="/forum/category/create"
            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-forum px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 sm:ml-auto sm:py-2"
        >
            <Plus class="size-4" /> New category
        </Link>
    </div>

    <div v-if="categories.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
            v-for="category in categories"
            :key="category.id"
            class="group relative flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800"
        >
            <img :src="category.logo" alt="" class="size-16 shrink-0 rounded-xl object-cover" />
            <div class="min-w-0 flex-1">
                <h2 class="font-bold leading-snug group-hover:text-forum">
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

            <!-- Above the card link -->
            <div v-if="category.canEdit || category.canDelete" class="relative z-10 flex shrink-0 flex-col gap-1">
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

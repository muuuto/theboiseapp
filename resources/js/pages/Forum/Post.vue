<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, FileText, History, Lock, Mail, Pencil, Pin, PinOff, Trash2 } from 'lucide-vue-next';
import CommentBox from '../../components/CommentBox.vue';
import LikeButton from '../../components/LikeButton.vue';
import QrCodeButton from '../../components/QrCodeButton.vue';
import ReactionBar from '../../components/ReactionBar.vue';
import { ago, fullDate } from '../../time.js';
import { t } from '../../i18n.js';

const props = defineProps({
    category: Object,
    post: Object,
    comments: Array,
    reactions: Object,
    mentionable: { type: Array, default: () => [] }, // who can be @mentioned in a comment
    can: Object,
    pinned: Boolean, // linked at the top of the home page
});

// A comment's text in pieces, the @names (found by the server) in bold
function withMentions(comment) {
    if (!comment.mentions?.length) return [{ text: comment.text }];
    const names = [...comment.mentions].sort((a, b) => b.length - a.length).map((name) => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    return comment.text.split(new RegExp(`(@(?:${names.join('|')}))`, 'giu')).filter(Boolean).map((text) => ({ text, mention: text.startsWith('@') && comment.mentions.some((name) => `@${name}`.toLowerCase() === text.toLowerCase()) }));
}

// Admins: pin this post at the top of the home page (it replaces the one pinned before)
function togglePin() {
    const url = `/forum/${props.category.id}/${props.post.id}/pin`;
    props.pinned ? router.delete(url, { preserveScroll: true }) : router.post(url, {}, { preserveScroll: true });
}

// Image attachments: open one big, browse with the arrows
const viewing = ref(null);
function onKeydown(event) {
    if (viewing.value === null) return;
    if (event.key === 'Escape') viewing.value = null;
    if (event.key === 'ArrowRight') viewing.value = (viewing.value + 1) % props.post.images.length;
    if (event.key === 'ArrowLeft') viewing.value = (viewing.value - 1 + props.post.images.length) % props.post.images.length;
}

function destroy() {
    if (window.confirm(t('Delete the post ":title"? This can\'t be undone.', { title: props.post.title }))) {
        router.delete(`/forum/${props.category.id}/${props.post.id}/delete`);
    }
}

function notifyAll() {
    if (window.confirm(t('Send an email about this post to everyone who can see it?'))) {
        router.post(`/forum/${props.category.id}/${props.post.id}/notify`, {}, { preserveScroll: true });
    }
}

// Arriving from a notification (#comment-12): scroll to that comment and light it up for a moment
const highlighted = ref(null);
onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    const match = window.location.hash.match(/^#comment-(\d+)$/);
    if (!match) return;
    highlighted.value = Number(match[1]);
    nextTick(() => document.getElementById(`comment-${highlighted.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    setTimeout(() => (highlighted.value = null), 2500);
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head :title="post.title" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <Link :href="`/forum/${category.id}`" class="inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
            <ArrowLeft class="size-4" /> {{ category.title }}
        </Link>
        <div class="ml-auto flex flex-wrap gap-2">
            <QrCodeButton :path="`/forum/${category.id}/${post.id}`" :title="post.title" />
            <button v-if="can.notify" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700" @click="notifyAll">
                <Mail class="size-4" /> {{ t('Notify all') }}
            </button>
            <button v-if="can.pin" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700" :class="pinned && 'text-accent'" @click="togglePin">
                <component :is="pinned ? PinOff : Pin" class="size-4" /> {{ pinned ? t('Unpin from home') : t('Pin on the home page') }}
            </button>
            <Link v-if="can.edit" :href="`/forum/${category.id}/${post.id}/edit`" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700">
                <Pencil class="size-4" /> {{ t('Edit') }}
            </Link>
            <button v-if="can.delete" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-slate-200 hover:bg-red-50 dark:bg-slate-800 dark:ring-slate-700 dark:hover:bg-red-950/40" @click="destroy">
                <Trash2 class="size-4" /> {{ t('Delete') }}
            </button>
        </div>
    </div>

    <article class="mx-auto max-w-3xl space-y-6">
        <img v-if="post.cover" :src="post.cover" alt="" class="max-h-[60vh] w-full rounded-3xl bg-slate-200 object-contain shadow-sm dark:bg-slate-800" />

        <header>
            <h1 class="flex items-start gap-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                <Lock v-if="post.isPrivate" class="mt-2 size-6 shrink-0 text-amber-600" :title="t('Only you can see it')" />
                {{ post.title }}
            </h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">{{ post.description }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
                <span class="flex items-center gap-2">
                    <img v-if="post.authorAvatar" :src="post.authorAvatar" alt="" class="size-7 rounded-full object-cover" />
                    <strong class="text-slate-700 dark:text-slate-300">{{ post.author }}</strong>
                </span>
                <time :datetime="post.createdAt" :title="fullDate(post.createdAt)">{{ ago(post.createdAt) }}</time>
                <span class="inline-flex items-center gap-1"><Eye class="size-4" /> {{ post.views }}</span>
                <span v-for="tag in post.tags" :key="tag" class="rounded-full bg-white px-2.5 py-0.5 text-xs ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">{{ tag }}</span>
            </div>
        </header>

        <div class="rich-text rounded-3xl bg-white p-6 text-[17px] shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800" v-html="post.content" />

        <ReactionBar type="post" :id="post.id" :reactions="reactions" />

        <section v-if="post.images.length" class="space-y-2">
            <h2 class="font-bold">{{ t('Photos') }}</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <button v-for="(image, index) in post.images" :key="image" class="overflow-hidden rounded-2xl" @click="viewing = index">
                    <img :src="image" alt="" loading="lazy" class="aspect-square w-full object-cover transition hover:scale-105" />
                </button>
            </div>
        </section>

        <section v-if="post.pdfs.length" class="space-y-2">
            <h2 class="font-bold">{{ t('Documents') }}</h2>
            <a
                v-for="pdf in post.pdfs"
                :key="pdf.url"
                :href="pdf.url"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 font-medium shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-900 dark:ring-slate-800"
            >
                <FileText class="size-5 text-red-600" /> {{ pdf.name }} <span class="text-xs text-slate-500">PDF</span>
            </a>
        </section>

        <details v-if="post.lastChanges.length" class="text-sm text-slate-500">
            <summary class="flex cursor-pointer items-center gap-1.5"><History class="size-4" /> {{ tc('Edited :count time|Edited :count times', post.lastChanges.length) }}</summary>
            <ul class="mt-2 space-y-0.5 pl-6">
                <li v-for="change in post.lastChanges" :key="change">{{ change }}</li>
            </ul>
        </details>

        <!-- Comments -->
        <section class="space-y-4">
            <h2 class="text-lg font-bold">
                {{ t('Comments') }} <span class="text-sm font-medium text-slate-500">{{ comments.length }}</span>
            </h2>

            <CommentBox :url="`/forum/${category.id}/${post.id}/comment`" :placeholder="t('Write a comment...')" :people="mentionable" />

            <p v-if="!comments.length" class="text-sm text-slate-500">{{ t('No comments yet, be the first.') }}</p>

            <article
                v-for="comment in comments"
                :id="`comment-${comment.id}`"
                :key="comment.id"
                class="-mx-2 flex scroll-mt-24 gap-3 rounded-2xl p-2 transition-colors duration-700"
                :class="highlighted === comment.id && 'bg-brand/10'"
            >
                <img v-if="comment.avatar" :src="comment.avatar" alt="" class="size-10 shrink-0 rounded-full object-cover" />
                <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-full bg-forum font-bold text-white">
                    {{ comment.author.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="rounded-2xl rounded-tl-sm bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <p class="text-sm font-semibold">{{ comment.author }}</p>
                        <!-- Plain text: shown as text, line breaks kept -->
                        <p class="text-[15px] break-words whitespace-pre-line"><template v-for="(part, index) in withMentions(comment)" :key="index"><strong v-if="part.mention">{{ part.text }}</strong><template v-else>{{ part.text }}</template></template></p>
                    </div>
                    <div class="mt-1 flex items-center gap-2 pl-2 text-xs text-slate-500">
                        <time :datetime="comment.createdAt" :title="fullDate(comment.createdAt)">{{ ago(comment.createdAt) }}</time>
                        <LikeButton type="post_comment" :id="comment.id" :likes="comment.likes" />
                    </div>
                </div>
            </article>
        </section>
    </article>

    <!-- Photo viewer -->
    <Teleport to="body">
        <div v-if="viewing !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" @click.self="viewing = null">
            <img :src="post.images[viewing]" alt="" class="max-h-full max-w-full rounded-lg" />
            <button class="absolute top-4 right-4 rounded-full bg-white/10 px-3 py-1 text-white hover:bg-white/20" @click="viewing = null">{{ t('Close') }}</button>
            <template v-if="post.images.length > 1">
                <button class="absolute top-1/2 left-4 -translate-y-1/2 rounded-full bg-white/10 px-3 py-2 text-2xl text-white hover:bg-white/20" @click="viewing = (viewing - 1 + post.images.length) % post.images.length">‹</button>
                <button class="absolute top-1/2 right-4 -translate-y-1/2 rounded-full bg-white/10 px-3 py-2 text-2xl text-white hover:bg-white/20" @click="viewing = (viewing + 1) % post.images.length">›</button>
            </template>
        </div>
    </Teleport>
</template>

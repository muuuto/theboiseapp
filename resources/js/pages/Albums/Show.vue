<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft, CalendarDays, Check, Copy, ExternalLink, MapPin, Pencil, PlayCircle, Trash2, UserCog, Users,
} from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';
import CommentBox from '../../components/CommentBox.vue';
import { ago, fullDate } from '../../time.js';
import LikeButton from '../../components/LikeButton.vue';

const props = defineProps({
    album: Object,
    comments: Array,
    can: Object,
});

const longDate = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const dates = computed(() => {
    const from = longDate.format(new Date(props.album.dateFrom));
    const to = longDate.format(new Date(props.album.dateTo));
    return from === to ? [from] : [from, to];
});

// One pin per place (an album can be in several places)
const pins = computed(() =>
    props.album.places.map((place, index) => ({
        id: index,
        albumId: props.album.id,
        latitude: place.latitude,
        longitude: place.longitude,
        title: props.album.title,
        cover: props.album.cover,
        dateFrom: props.album.dateFrom,
        suggested: place.suggested,
    })),
);
const placeNames = computed(() => props.album.places.map((place) => place.placeName).filter((name) => name && name !== props.album.location));

// YouTube links become embedded players (privacy-friendly domain), other links stay buttons
function youtubeId(url) {
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/))([\w-]{11})/);
    return match ? match[1] : null;
}
const videos = computed(() => props.album.videoLinks.map((url) => ({ url, youtube: youtubeId(url) })));

// Copy the Amazon Photos link, to share it
const copied = ref(false);
async function copyAlbumLink() {
    await navigator.clipboard.writeText(props.album.albumLink);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

function destroy() {
    if (window.confirm(`Delete the album "${props.album.title}"? This can't be undone.`)) {
        router.delete(`/listings/${props.album.id}`);
    }
}

// Arriving from a notification (#comment-12): scroll to that comment and light it up for a moment
const highlighted = ref(null);
onMounted(() => {
    const match = window.location.hash.match(/^#comment-(\d+)$/);
    if (!match) return;
    highlighted.value = Number(match[1]);
    nextTick(() => document.getElementById(`comment-${highlighted.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
    setTimeout(() => (highlighted.value = null), 2500);
});

</script>

<template>
    <Head :title="album.title" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <Link href="/" class="inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
            <ArrowLeft class="size-4" /> Albums
        </Link>
        <div v-if="can.manage" class="ml-auto flex gap-2">
            <Link
                :href="`/listings/${album.id}/edit`"
                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
            >
                <Pencil class="size-4" /> Edit
            </Link>
            <button
                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-slate-200 hover:bg-red-50 dark:bg-slate-800 dark:ring-slate-700 dark:hover:bg-red-950/40"
                @click="destroy"
            >
                <Trash2 class="size-4" /> Delete
            </button>
        </div>
    </div>

    <!-- Phones: photo and description, then the details, then the comments.
         Large screens: details in a column on the right, next to both -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6 lg:col-start-1">
            <div class="overflow-hidden rounded-3xl bg-slate-200 shadow-sm dark:bg-slate-800">
                <img :src="album.cover" :alt="album.title" class="mx-auto max-h-[75vh] w-full object-contain" />
            </div>

            <div>
                <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ album.title }}</h1>
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    <li v-for="tag in album.tags" :key="tag">
                        <Link
                            :href="`/?tag=${encodeURIComponent(tag)}`"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="tag === 'Youtube'
                                ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'"
                        >
                            {{ tag }}
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Admins only (the count is 0 for everyone else): people never know their version is different -->
            <p v-if="album.personalizedCount" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                <UserCog class="size-3.5" />
                Admin: {{ album.personalizedCount }} {{ album.personalizedCount === 1 ? 'person sees' : 'people see' }} a different link or description
            </p>

            <section v-if="album.description" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div class="rich-text" v-html="album.description" />
            </section>

            <section v-if="videos.length" class="space-y-3">
                <h2 class="text-lg font-bold">Videos</h2>
                <template v-for="(video, index) in videos" :key="video.url">
                    <div v-if="video.youtube" class="aspect-video overflow-hidden rounded-2xl bg-black shadow-sm">
                        <iframe
                            :src="`https://www.youtube-nocookie.com/embed/${video.youtube}`"
                            :title="`Video ${index + 1}`"
                            class="size-full"
                            allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            loading="lazy"
                            allowfullscreen
                        />
                    </div>
                    <a v-else :href="video.url" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-2xl bg-red-600 px-4 py-3 font-semibold text-white hover:bg-red-700">
                        <PlayCircle class="size-5" /> Video {{ index + 1 }}
                    </a>
                </template>
            </section>
        </div>

        <!-- Details -->
        <aside class="space-y-4 lg:sticky lg:top-22 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
            <div class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div class="flex gap-3">
                    <CalendarDays class="mt-0.5 size-5 shrink-0 text-accent" />
                    <div class="text-sm">
                        <p v-for="day in dates" :key="day" class="font-medium">{{ day }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <MapPin class="mt-0.5 size-5 shrink-0 text-accent" />
                    <div class="text-sm">
                        <p class="font-medium">{{ album.location }}</p>
                        <p v-for="name in placeNames" :key="name" class="text-xs text-slate-500">{{ name }}</p>
                    </div>
                </div>
                <LeafletMap v-if="pins.length" :markers="pins" :zoom="11" class="h-48" />

                <a
                    :href="album.albumLink"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                    <ExternalLink class="size-4" /> Open the photos
                </a>
                <button
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800"
                    @click="copyAlbumLink"
                >
                    <component :is="copied ? Check : Copy" class="size-4" />
                    {{ copied ? 'Copied!' : 'Copy the photos link' }}
                </button>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <h2 class="mb-3 flex items-center gap-2 font-bold">
                    <Users class="size-5 text-accent" /> People <span class="text-sm font-medium text-slate-500">{{ album.people.length }}</span>
                </h2>
                <ul class="grid grid-cols-2 gap-2">
                    <li v-for="person in album.people" :key="person.id" class="flex min-w-0 items-center gap-2">
                        <img v-if="person.avatar" :src="person.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold dark:bg-slate-700">
                            {{ person.name.charAt(0).toUpperCase() }}
                        </span>
                        <span class="truncate text-sm">{{ person.name }}</span>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Comments -->
        <section class="min-w-0 space-y-4 lg:col-start-1">
            <h2 class="text-lg font-bold">
                Comments <span class="text-sm font-medium text-slate-500">{{ comments.length }}</span>
            </h2>

            <CommentBox
                v-if="can.comment"
                url="/listings/comments"
                :extra="{ listing_id: album.id }"
                placeholder="Write a comment... my favorite photo is"
            />

            <p v-if="!comments.length" class="text-sm text-slate-500">No comments yet{{ can.comment ? ', be the first' : '' }}.</p>

            <article
                v-for="comment in comments"
                :id="`comment-${comment.id}`"
                :key="comment.id"
                class="-mx-2 flex scroll-mt-24 gap-3 rounded-2xl p-2 transition-colors duration-700"
                :class="highlighted === comment.id && 'bg-brand/10'"
            >
                <img v-if="comment.avatar" :src="comment.avatar" alt="" class="size-10 shrink-0 rounded-full object-cover" />
                <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent font-bold text-white">
                    {{ comment.author.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="rounded-2xl rounded-tl-sm bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <p class="text-sm font-semibold">{{ comment.author }}</p>
                        <div class="rich-text text-[15px]" v-html="comment.html" />
                    </div>
                    <div class="mt-1 flex items-center gap-2 pl-2 text-xs text-slate-500">
                        <time :datetime="comment.createdAt" :title="fullDate(comment.createdAt)">{{ ago(comment.createdAt) }}</time>
                        <LikeButton type="album_comment" :id="comment.id" :likes="comment.likes" :disabled="!can.comment" />
                    </div>
                </div>
            </article>
        </section>

    </div>
</template>

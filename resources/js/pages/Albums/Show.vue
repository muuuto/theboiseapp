<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Link2, CalendarDays, Check, Copy, ExternalLink, History, MapPin, Plane, Pencil, PlayCircle, ShieldCheck, Trash2, UserCog, Users, Wallet,
} from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';
import CommentBox from '../../components/CommentBox.vue';
import { ago, formatDate, fullDate } from '../../time.js';
import { t } from '../../i18n.js';
import LikeButton from '../../components/LikeButton.vue';
import QrCodeButton from '../../components/QrCodeButton.vue';
import ReactionBar from '../../components/ReactionBar.vue';
import AlbumHistory from '../../components/AlbumHistory.vue';

const props = defineProps({
    album: Object,
    comments: Array,
    reactions: Object,
    mentionable: { type: Array, default: () => [] }, // who can be @mentioned in a comment
    can: Object,
    expenseGroups: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] }, // admins only
    trip: { type: Object, default: null }, // the trip plan it was made from
    sameTrip: { type: Array, default: () => [] }, // linked albums of the same trip (the ones I'm in)
    linkable: { type: Array, default: null }, // who can edit it: the albums it could be linked to
});

const longDate = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
const dates = computed(() => {
    const from = formatDate(props.album.dateFrom, longDate);
    const to = formatDate(props.album.dateTo, longDate);
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
    if (window.confirm(t('Delete the album ":title"? This can\'t be undone.', { title: props.album.title }))) {
        router.delete(`/listings/${props.album.id}`);
    }
}

// Replying: the box opens under the thread; to a reply, it starts with "@Name "
const replying = ref(null);
let replyKey = 0;
function startReply(thread, comment) {
    const me = usePage().props.auth.user?.name;
    replying.value = {
        key: ++replyKey,
        thread: thread.id,
        comment: comment.id,
        name: comment.author,
        initial: comment.id !== thread.id && comment.author !== me ? `@${comment.author} ` : '',
    };
}

// Albums of the same trip: link this one to another (search, closest dates first), or unlink it
const linking = ref(false);
const linkSearch = ref('');
const linkMatches = computed(() => {
    const query = linkSearch.value.trim().toLowerCase();
    return (props.linkable ?? []).filter((album) => !query || album.title.toLowerCase().includes(query)).slice(0, 8);
});
function linkTo(album) {
    router.post(`/listings/${props.album.id}/links`, { with: album.id }, { preserveScroll: true, onSuccess: () => (linking.value = false) });
}
function unlink() {
    if (window.confirm(t('Take this album out of the group of the same trip?'))) router.delete(`/listings/${props.album.id}/links`, { preserveScroll: true });
}
const shortRange = (album) => formatDate(album.dateFrom, { day: 'numeric', month: 'short', year: 'numeric' });

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
            <ArrowLeft class="size-4" /> {{ t('Albums') }}
        </Link>
        <div class="ml-auto flex flex-wrap gap-2">
            <QrCodeButton :path="`/listings/${album.id}`" :title="album.title" />
            <Link
                v-if="can.edit"
                :href="`/listings/${album.id}/edit`"
                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
            >
                <Pencil class="size-4" /> {{ t('Edit') }}
            </Link>
            <button
                v-if="can.delete"
                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-slate-200 hover:bg-red-50 dark:bg-slate-800 dark:ring-slate-700 dark:hover:bg-red-950/40"
                @click="destroy"
            >
                <Trash2 class="size-4" /> {{ t('Delete') }}
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
                <p v-if="album.postedBy" class="mt-1 text-sm text-slate-500">{{ t('Posted by :name', { name: album.postedBy }) }}</p>
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
                <ReactionBar type="album" :id="album.id" :reactions="reactions" class="mt-4" />
            </div>

            <!-- Admins only (the count is 0 for everyone else): people never know their version is different -->
            <p v-if="can.adminView" class="flex items-center gap-2 rounded-2xl bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                <ShieldCheck class="size-4 shrink-0" /> {{ t("Admin view: you're not in this album, so you can look but not comment.") }}
            </p>

            <p v-if="album.personalizedCount" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                <UserCog class="size-3.5" />
                {{ tc('Admin: :count person sees a different link or description|Admin: :count people see a different link or description', album.personalizedCount) }}
            </p>

            <section v-if="album.description" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div class="rich-text" v-html="album.description" />
            </section>

            <section v-if="videos.length" class="space-y-3">
                <h2 class="text-lg font-bold">{{ t('Videos') }}</h2>
                <template v-for="(video, index) in videos" :key="video.url">
                    <div v-if="video.youtube" class="aspect-video overflow-hidden rounded-2xl bg-black shadow-sm">
                        <iframe
                            :src="`https://www.youtube-nocookie.com/embed/${video.youtube}`"
                            :title="t('Video :number', { number: index + 1 })"
                            class="size-full"
                            allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            loading="lazy"
                            allowfullscreen
                        />
                    </div>
                    <a v-else :href="video.url" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-2xl bg-red-600 px-4 py-3 font-semibold text-white hover:bg-red-700">
                        <PlayCircle class="size-5" /> {{ t('Video :number', { number: index + 1 }) }}
                    </a>
                </template>
            </section>

            <!-- Albums of the same trip, posted by other people -->
            <section v-if="sameTrip.length || linkable" class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><Link2 class="size-5 text-accent" /> {{ t('Same trip') }}</h2>
                    <div v-if="linkable" class="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                        <button v-if="!linking" type="button" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="linking = true">
                            <Plus class="size-4" /> {{ t('Link an album') }}
                        </button>
                        <button v-if="sameTrip.length" type="button" class="ml-auto rounded-xl px-3 py-1.5 text-sm font-semibold text-slate-500 hover:text-red-600 sm:ml-0" @click="unlink">{{ t('Unlink') }}</button>
                    </div>
                </div>
                <p v-if="!sameTrip.length && !linking" class="text-sm text-slate-500">{{ t('Someone else posted photos of this trip? Link their album: everyone in both sees them side by side.') }}</p>

                <div v-if="sameTrip.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link
                        v-for="other in sameTrip"
                        :key="other.id"
                        :href="`/listings/${other.id}`"
                        class="flex items-center gap-3 rounded-2xl bg-white p-2 pr-3 shadow-sm ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-900 dark:ring-slate-800"
                    >
                        <img :src="other.cover" alt="" class="size-16 shrink-0 rounded-xl object-cover" />
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ other.title }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ other.postedBy ? t('Posted by :name', { name: other.postedBy }) : '' }} · {{ shortRange(other) }}</span>
                        </span>
                    </Link>
                </div>

                <!-- Linking: pick the other album -->
                <div v-if="linking" class="space-y-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="flex items-center gap-2">
                        <input v-model="linkSearch" type="search" :placeholder="t('Search albums')" class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-slate-50 px-3 text-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700" />
                        <button type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="linking = false">{{ t('Cancel') }}</button>
                    </div>
                    <p class="text-xs text-slate-500">{{ t('Albums you\'re in, the ones with the closest dates first.') }}</p>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="other in linkMatches" :key="other.id">
                            <button type="button" class="flex w-full items-center gap-3 py-2 text-left hover:text-accent" @click="linkTo(other)">
                                <img :src="other.cover" alt="" class="size-11 shrink-0 rounded-lg object-cover" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ other.title }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ shortRange(other) }}<template v-if="other.postedBy"> · {{ t('Posted by :name', { name: other.postedBy }) }}</template></span>
                                </span>
                                <Plus class="size-4 shrink-0" />
                            </button>
                        </li>
                    </ul>
                    <p v-if="!linkMatches.length" class="py-2 text-sm text-slate-500">{{ t('No albums found.') }}</p>
                </div>
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

                <!-- An album can be only videos: then there's no photos link -->
                <a
                    v-if="album.albumLink"
                    :href="album.albumLink"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                >
                    <ExternalLink class="size-4" /> {{ t('Open the photos') }}
                </a>
                <button
                    v-if="album.albumLink"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800"
                    @click="copyAlbumLink"
                >
                    <component :is="copied ? Check : Copy" class="size-4" />
                    {{ copied ? t('Copied!') : t('Copy the photos link') }}
                </button>
                <Link
                    v-if="trip"
                    :href="`/trips/${trip.id}`"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-sky-700 ring-1 ring-slate-200 transition hover:bg-sky-50 dark:text-sky-400 dark:ring-slate-700 dark:hover:bg-slate-800"
                >
                    <Plane class="size-4" /> {{ t('The trip plan') }}
                </Link>
                <Link
                    v-for="expenseGroup in expenseGroups"
                    :key="expenseGroup.id"
                    :href="`/expenses/${expenseGroup.id}`"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-teal-700 ring-1 ring-slate-200 transition hover:bg-teal-50 dark:text-teal-400 dark:ring-slate-700 dark:hover:bg-slate-800"
                >
                    <Wallet class="size-4" /> {{ t('Expenses: :name', { name: expenseGroup.name }) }}
                </Link>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <h2 class="mb-3 flex items-center gap-2 font-bold">
                    <Users class="size-5 text-accent" /> {{ t('People') }} <span class="text-sm font-medium text-slate-500">{{ album.people.length }}</span>
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

            <!-- Admins only (the list is empty for everyone else) -->
            <details v-if="history.length" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <summary class="flex cursor-pointer items-center gap-2 font-bold">
                    <History class="size-5 text-accent" /> {{ t('History') }} <span class="text-sm font-medium text-slate-500">{{ t('admins only') }}</span>
                </summary>
                <div class="mt-3"><AlbumHistory :events="history" /></div>
            </details>
        </aside>

        <!-- Comments -->
        <section class="min-w-0 space-y-4 lg:col-start-1">
            <h2 class="text-lg font-bold">
                {{ t('Comments') }} <span class="text-sm font-medium text-slate-500">{{ comments.reduce((count, thread) => count + 1 + thread.replies.length, 0) }}</span>
            </h2>

            <CommentBox
                v-if="can.comment"
                url="/listings/comments"
                :extra="{ listing_id: album.id }"
                :people="mentionable"
                :placeholder="t('Write a comment... my favorite photo is')"
            />

            <p v-if="!comments.length" class="text-sm text-slate-500">{{ can.comment ? t('No comments yet, be the first.') : t('No comments yet.') }}</p>

            <article
                v-for="thread in comments"
                :id="`comment-${thread.id}`"
                :key="thread.id"
                class="-mx-2 flex scroll-mt-24 gap-3 rounded-2xl p-2 transition-colors duration-700"
                :class="highlighted === thread.id && 'bg-brand/10'"
            >
                <img v-if="thread.avatar" :src="thread.avatar" alt="" class="size-10 shrink-0 rounded-full object-cover" />
                <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent font-bold text-white">
                    {{ thread.author.charAt(0).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="rounded-2xl rounded-tl-sm bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <p class="text-sm font-semibold">{{ thread.author }}</p>
                        <div class="rich-text text-[15px]" v-html="thread.html" />
                    </div>
                    <div class="mt-1 flex items-center gap-2 pl-2 text-xs text-slate-500">
                        <time :datetime="thread.createdAt" :title="fullDate(thread.createdAt)">{{ ago(thread.createdAt) }}</time>
                        <LikeButton type="album_comment" :id="thread.id" :likes="thread.likes" :disabled="!can.comment" />
                        <button v-if="can.comment" type="button" class="rounded-full px-2 py-1 font-semibold hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white" @click="startReply(thread, thread)">{{ t('Reply') }}</button>
                    </div>

                    <!-- Replies, oldest first -->
                    <div v-if="thread.replies.length || replying?.thread === thread.id" class="mt-2 space-y-1 border-l-2 border-slate-200 pl-3 dark:border-slate-800">
                        <div
                            v-for="reply in thread.replies"
                            :id="`comment-${reply.id}`"
                            :key="reply.id"
                            class="flex scroll-mt-24 gap-2 rounded-2xl p-1 transition-colors duration-700"
                            :class="highlighted === reply.id && 'bg-brand/10'"
                        >
                            <img v-if="reply.avatar" :src="reply.avatar" alt="" class="size-7 shrink-0 rounded-full object-cover" />
                            <span v-else class="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-bold text-white">{{ reply.author.charAt(0).toUpperCase() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="rounded-2xl rounded-tl-sm bg-white px-3 py-2 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                    <p class="text-sm font-semibold">{{ reply.author }}</p>
                                    <div class="rich-text text-[15px]" v-html="reply.html" />
                                </div>
                                <div class="mt-1 flex items-center gap-2 pl-2 text-xs text-slate-500">
                                    <time :datetime="reply.createdAt" :title="fullDate(reply.createdAt)">{{ ago(reply.createdAt) }}</time>
                                    <LikeButton type="album_comment" :id="reply.id" :likes="reply.likes" :disabled="!can.comment" />
                                    <button v-if="can.comment" type="button" class="rounded-full px-2 py-1 font-semibold hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white" @click="startReply(thread, reply)">{{ t('Reply') }}</button>
                                </div>
                            </div>
                        </div>
                        <CommentBox
                            v-if="replying?.thread === thread.id"
                            :key="replying.key"
                            url="/listings/comments"
                            :extra="{ listing_id: album.id, parent_id: replying.comment }"
                            :people="mentionable"
                            :initial="replying.initial"
                            :placeholder="t('Reply to :name...', { name: replying.name })"
                            reply
                            @sent="replying = null"
                            @cancel="replying = null"
                        />
                    </div>
                </div>
            </article>
        </section>

    </div>
</template>

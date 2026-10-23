<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Heart, Pause, Play, RotateCcw, Share2, X } from 'lucide-vue-next';
import { intlLocale, t, tc } from '../i18n.js';

// TheBoise Wrapped: the year as story slides (tap/swipe/arrows), ending with a card to save and share
defineOptions({ layout: null });
const props = defineProps({
    wrapped: Object, // null: no albums that year, or not open yet
    year: Number,
    years: Array,
    opensOn: String,
});

const w = props.wrapped;
const number = new Intl.NumberFormat(intlLocale());
const bolzanoRome = 516; // km as the crow flies

// Only the slides with something to say
const slides = computed(() => {
    if (!w) return [];
    const list = ['intro', 'albums', 'days', 'world'];
    if (w.farthest && w.farthest.km > 50) list.push('farthest');
    if (w.busiestMonth && w.albumCount > 2) list.push('month');
    if (w.friends.length) list.push('crew');
    if (w.comments.best || w.comments.written) list.push('comment');
    list.push('summary');
    return list;
});
const backgrounds = {
    intro: 'from-teal-600 via-teal-700 to-slate-900',
    albums: 'from-rose-500 via-red-600 to-orange-500',
    days: 'from-amber-400 via-orange-500 to-rose-600',
    world: 'from-sky-500 via-indigo-600 to-violet-700',
    farthest: 'from-emerald-500 via-teal-600 to-cyan-700',
    month: 'from-fuchsia-500 via-purple-600 to-indigo-700',
    crew: 'from-orange-400 via-rose-500 to-fuchsia-600',
    comment: 'from-slate-800 via-slate-900 to-black',
    summary: 'from-teal-500 via-teal-700 to-slate-900',
};

const index = ref(0);
const current = computed(() => slides.value[index.value]);
const paused = ref(false);
const SLIDE_MS = 7000;
const progress = ref(0);
let started = performance.now();
let frame = null;

function go(to) {
    index.value = Math.max(0, Math.min(slides.value.length - 1, to));
    started = performance.now();
    progress.value = 0;
}
const next = () => go(index.value + 1);
const back = () => go(index.value - 1);

// Auto-advance (not on the last card), paused while holding a finger down
function tick(now) {
    if (!paused.value && current.value !== 'summary') {
        progress.value = Math.min(1, (now - started) / SLIDE_MS);
        if (progress.value >= 1) next();
    } else {
        started = now - progress.value * SLIDE_MS;
    }
    frame = requestAnimationFrame(tick);
}

// Big numbers count up when their slide appears
const shown = ref({});
function countUp(key, target) {
    const begin = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - begin) / 1200);
        shown.value = { ...shown.value, [key]: Math.round(target * (1 - (1 - t) ** 3)) };
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}
watch(current, (slide) => {
    if (slide === 'albums') countUp('albums', w.albumCount);
    if (slide === 'days') countUp('days', w.daysAway);
    if (slide === 'world') countUp('km', w.km);
    if (slide === 'farthest') countUp('farthest', w.farthest.km);
});

// Tap: left third back, the rest forward. Swipe works the same
let touchX = null;
function onPointerDown(event) {
    touchX = event.clientX;
    paused.value = true;
}
function onPointerUp(event) {
    paused.value = false;
    if (touchX === null || event.target.closest('button, a, select')) return;
    const moved = event.clientX - touchX;
    touchX = null;
    if (Math.abs(moved) > 40) return moved < 0 ? next() : back();
    const box = event.currentTarget.getBoundingClientRect();
    event.clientX - box.left < box.width / 3 ? back() : next();
}
const onKey = (event) => {
    if (event.key === 'ArrowRight' || event.key === ' ') next();
    if (event.key === 'ArrowLeft') back();
    if (event.key === 'Escape') router.visit('/');
};
onMounted(() => {
    window.addEventListener('keydown', onKey);
    if (w) frame = requestAnimationFrame(tick);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    cancelAnimationFrame(frame);
});

const comparison = computed(() => {
    if (!w?.km) return null;
    if (w.km >= 40075) return t("That's :times times around the Earth.", { times: (w.km / 40075).toFixed(1) });
    const romes = w.km / bolzanoRome;
    return romes >= 1.5
        ? t('Like going from Bolzano to Rome :times times.', { times: Math.round(romes) })
        : t(':percent% of the way around the Earth.', { percent: Math.round((w.km / 40075) * 1000) / 10 });
});
const albumsVsLastYear = computed(() => {
    if (!w?.previousYear) return t('Your first year on TheBoise. Welcome!');
    const diff = w.albumCount - w.previousYear;
    if (diff > 0) return t(":count more than in :year. Someone's been busy.", { count: diff, year: w.year - 1 });
    if (diff < 0) return t(':count fewer than in :year. Quality over quantity.', { count: -diff, year: w.year - 1 });
    return t('Exactly like :year. Consistency is key.', { year: w.year - 1 });
});
const crewLine = computed(() => {
    const top = w?.friends[0];
    if (!top) return '';
    return top.trips >= 5 ? t('You and :name: basically inseparable.', { name: top.name }) : t(':name was there the most.', { name: top.name });
});

// The summary as an image (1080×1920, story size) to save or share
const saving = ref(false);
async function summaryImage() {
    const canvas = document.createElement('canvas');
    canvas.width = 1080;
    canvas.height = 1920;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 1080, 1920);
    gradient.addColorStop(0, '#14b8a6');
    gradient.addColorStop(0.5, '#0f766e');
    gradient.addColorStop(1, '#0f172a');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, 1080, 1920);

    const font = (weight, size) => `${weight} ${size}px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`;
    const text = (value, x, y, weight, size, color = '#ffffff', align = 'left') => {
        ctx.font = font(weight, size);
        ctx.fillStyle = color;
        ctx.textAlign = align;
        ctx.fillText(value, x, y);
    };
    text('THEBOISE WRAPPED', 90, 170, 800, 44, 'rgba(255,255,255,0.75)');
    text(String(w.year), 90, 400, 900, 230);
    text(w.name, 90, 500, 700, 64);

    const stats = [
        [number.format(w.albumCount), t('albums')],
        [number.format(w.daysAway), t('days away')],
        [number.format(w.km), t('km travelled')],
        [String(w.countries.length), w.countries.length === 1 ? t('country') : t('countries')],
    ];
    stats.forEach(([value, label], i) => {
        const x = 90 + (i % 2) * 470;
        const y = 720 + Math.floor(i / 2) * 270;
        ctx.fillStyle = 'rgba(255,255,255,0.12)';
        ctx.beginPath();
        ctx.roundRect(x - 30, y - 150, 440, 230, 36);
        ctx.fill();
        // Long numbers (12,537) get smaller to stay inside their box
        let size = 110;
        ctx.font = font(900, size);
        while (ctx.measureText(value).width > 380 && size > 50) ctx.font = font(900, (size -= 4));
        text(value, x, y, 900, size);
        text(label, x, y + 55, 600, 40, 'rgba(255,255,255,0.8)');
    });

    let y = 1340;
    if (w.countries.length) {
        text(w.countries.map((c) => c.flag ?? '🏳️').join(' '), 90, y, 400, 72);
        y += 110;
    }
    if (w.friends.length) {
        text(t('Top crew'), 90, y, 600, 40, 'rgba(255,255,255,0.75)');
        text(w.friends.map((f) => f.name).join(' · '), 90, y + 65, 800, 52);
        y += 170;
    }
    if (w.farthest) {
        text(t('Farthest'), 90, y, 600, 40, 'rgba(255,255,255,0.75)');
        text(`${w.farthest.name}, ${number.format(w.farthest.km)} km`, 90, y + 65, 800, 52);
    }
    text('theboise.it', 990, 1840, 800, 40, 'rgba(255,255,255,0.75)', 'right');

    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}
const fileName = () => `theboise-wrapped-${w.year}.png`;
const canShare = typeof navigator !== 'undefined' && !!navigator.canShare;
async function saveImage() {
    saving.value = true;
    const blob = await summaryImage();
    const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: fileName() });
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    saving.value = false;
}
async function shareImage() {
    const file = new File([await summaryImage()], fileName(), { type: 'image/png' });
    try {
        if (navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: t('My :year on TheBoise', { year: w.year }) });
    } catch {
        // Share sheet closed
    }
}
</script>

<template>
    <Head :title="wrapped ? `Wrapped ${wrapped.year}` : 'Wrapped'" />

    <div class="flex min-h-screen items-center justify-center bg-slate-950 text-white sm:p-6">
        <!-- Nothing to show -->
        <div v-if="!wrapped" class="max-w-sm p-8 text-center">
            <p class="text-6xl">🎁</p>
            <h1 class="mt-4 text-3xl font-extrabold">{{ t('TheBoise Wrapped') }}</h1>
            <p class="mt-3 text-slate-300">
                <template v-if="year">{{ t('No albums in :year for you, so no Wrapped this time.', { year }) }}</template>
                <template v-else>{{ t('Your year in albums, places and friends. It opens every year on :date.', { date: opensOn }) }}</template>
            </p>
            <div v-if="years.length" class="mt-5 flex flex-wrap justify-center gap-2">
                <Link v-for="other in years" :key="other" :href="`/wrapped/${other}`" class="rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold hover:bg-white/20">{{ other }}</Link>
            </div>
            <Link href="/" class="mt-6 inline-block text-sm font-semibold text-teal-300 hover:underline">{{ t('Back to the albums') }}</Link>
        </div>

        <!-- The story: full screen on phones, a phone-shaped card on computers -->
        <div
            v-else
            class="relative flex h-dvh w-full touch-pan-y flex-col overflow-hidden bg-gradient-to-br transition-colors duration-700 select-none sm:aspect-[9/16] sm:h-[min(90vh,860px)] sm:w-auto sm:rounded-[2rem] sm:shadow-2xl"
            :class="backgrounds[current]"
            @pointerdown="onPointerDown"
            @pointerup="onPointerUp"
            @pointercancel="paused = false"
        >
            <!-- Progress bars and close -->
            <div class="relative z-10 flex items-center gap-1 px-3 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <span v-for="(slide, i) in slides" :key="slide" class="h-1 flex-1 overflow-hidden rounded-full bg-white/30">
                    <span class="block h-full bg-white" :style="{ width: i < index ? '100%' : i === index ? `${(current === 'summary' ? 1 : progress) * 100}%` : '0%' }" />
                </span>
            </div>
            <div class="relative z-10 flex items-center justify-between px-4 pt-2 text-xs font-bold tracking-widest text-white/80 uppercase">
                <span>TheBoise Wrapped {{ wrapped.year }}</span>
                <span class="flex items-center gap-1">
                    <button type="button" class="rounded-full p-1.5 hover:bg-white/15" :aria-label="paused ? t('Play') : t('Pause')" @click="paused = !paused">
                        <component :is="paused ? Play : Pause" class="size-4" />
                    </button>
                    <Link href="/" class="rounded-full p-1.5 hover:bg-white/15" :aria-label="t('Close')"><X class="size-5" /></Link>
                </span>
            </div>

            <Transition
                mode="out-in"
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="translate-y-6 opacity-0"
                leave-active-class="transition duration-200"
                leave-to-class="opacity-0"
            >
                <div :key="current" class="flex flex-1 flex-col justify-center px-7 pb-10">
                    <!-- Intro -->
                    <template v-if="current === 'intro'">
                        <p class="text-lg font-semibold text-white/80">{{ t('Hey :name,', { name: wrapped.name }) }}</p>
                        <h1 class="mt-2 text-6xl leading-none font-black">{{ t('your :year', { year: wrapped.year }) }}<br />{{ t('on TheBoise') }}</h1>
                        <p class="mt-6 text-lg text-white/80">{{ t('Albums, places, friends. Let\'s see what you\'ve been up to.') }}</p>
                        <p class="mt-10 text-sm font-semibold text-white/60">{{ t('Tap to start →') }}</p>
                    </template>

                    <!-- Albums -->
                    <template v-else-if="current === 'albums'">
                        <p class="text-xl font-semibold text-white/80">{{ t('This year you were in') }}</p>
                        <p class="text-[7rem] leading-none font-black tabular-nums">{{ shown.albums ?? 0 }}</p>
                        <p class="text-3xl font-extrabold">{{ wrapped.albumCount === 1 ? t('album') : t('albums') }}</p>
                        <p class="mt-4 text-lg text-white/85">{{ albumsVsLastYear }}</p>
                        <div class="mt-6 grid grid-cols-4 gap-1.5">
                            <img
                                v-for="(album, i) in wrapped.albums.filter((a) => a.cover).slice(0, 8)"
                                :key="album.id"
                                :src="album.cover"
                                alt=""
                                class="aspect-square w-full rounded-lg object-cover shadow-lg"
                                :style="{ transform: `rotate(${(i % 2 ? 1 : -1) * (2 + (i % 3))}deg)` }"
                            />
                        </div>
                    </template>

                    <!-- Days -->
                    <template v-else-if="current === 'days'">
                        <p class="text-xl font-semibold text-white/80">{{ t('You spent') }}</p>
                        <p class="text-[7rem] leading-none font-black tabular-nums">{{ shown.days ?? 0 }}</p>
                        <p class="text-3xl font-extrabold">{{ t('days away from the sofa') }}</p>
                        <div class="mt-8 rounded-3xl bg-white/15 p-5 backdrop-blur">
                            <p class="text-sm font-semibold tracking-wide text-white/75 uppercase">{{ t('Longest one') }}</p>
                            <p class="mt-1 text-2xl font-extrabold">{{ wrapped.longest.title }}</p>
                            <p class="text-white/85">{{ tc(':count day in a row|:count days in a row', wrapped.longest.days) }}</p>
                        </div>
                    </template>

                    <!-- World -->
                    <template v-else-if="current === 'world'">
                        <p class="text-xl font-semibold text-white/80">{{ tc('You went to :count place in|You went to :count places in', wrapped.placeCount) }}</p>
                        <p class="text-6xl leading-none font-black">{{ tc(':count country|:count countries', wrapped.countries.length) }}</p>
                        <p class="mt-4 flex flex-wrap gap-2 text-5xl">
                            <span v-for="country in wrapped.countries" :key="country.name" :title="country.name">{{ country.flag ?? '🏳️' }}</span>
                        </p>
                        <template v-if="wrapped.km">
                            <p class="mt-8 text-5xl font-black tabular-nums">{{ number.format(shown.km ?? 0) }} km</p>
                            <p class="text-lg text-white/85">{{ t('as the crow flies, from :home and back.', { home: wrapped.home ?? 'Bolzano' }) }} {{ comparison }}</p>
                        </template>
                    </template>

                    <!-- Farthest -->
                    <template v-else-if="current === 'farthest'">
                        <p class="text-xl font-semibold text-white/80">{{ t('The farthest you went') }}</p>
                        <p class="mt-2 text-6xl leading-none font-black">{{ wrapped.farthest.name }}</p>
                        <p class="mt-4 text-4xl font-black tabular-nums">{{ number.format(shown.farthest ?? 0) }} km</p>
                        <p class="text-lg text-white/85">{{ t('from home, for ":album".', { album: wrapped.farthest.album }) }}</p>
                    </template>

                    <!-- Month -->
                    <template v-else-if="current === 'month'">
                        <p class="text-xl font-semibold text-white/80">{{ t('Your busiest month') }}</p>
                        <p class="mt-2 text-7xl leading-none font-black">{{ wrapped.busiestMonth.name }}</p>
                        <p class="mt-4 text-2xl font-bold">{{ t(':count albums started that month.', { count: wrapped.busiestMonth.albums }) }}</p>
                        <p class="mt-2 text-lg text-white/85">{{ t('Calendar? Never heard of it.') }}</p>
                    </template>

                    <!-- Crew -->
                    <template v-else-if="current === 'crew'">
                        <p class="text-xl font-semibold text-white/80">{{ t('Your crew') }}</p>
                        <p class="mt-1 text-2xl font-bold">{{ crewLine }}</p>
                        <ol class="mt-6 space-y-3">
                            <li v-for="(friend, i) in wrapped.friends" :key="friend.name" class="flex items-center gap-4 rounded-3xl bg-white/15 p-3 backdrop-blur">
                                <span class="w-6 text-center text-2xl font-black">{{ i + 1 }}</span>
                                <img v-if="friend.avatar" :src="friend.avatar" alt="" class="size-14 rounded-full object-cover ring-2 ring-white/70" />
                                <span v-else class="flex size-14 items-center justify-center rounded-full bg-white/25 text-xl font-black">{{ friend.name.charAt(0) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xl font-extrabold">{{ friend.name }}</span>
                                    <span class="text-white/85">{{ tc(':count album together|:count albums together', friend.trips) }}</span>
                                </span>
                            </li>
                        </ol>
                    </template>

                    <!-- Comment -->
                    <template v-else-if="current === 'comment'">
                        <template v-if="wrapped.comments.best">
                            <p class="text-xl font-semibold text-white/80">{{ t('Your most liked comment') }}</p>
                            <blockquote class="mt-4 text-3xl leading-snug font-extrabold">“{{ wrapped.comments.best.text }}”</blockquote>
                            <p class="mt-4 flex items-center gap-2 text-lg text-white/85">
                                <Heart class="size-5 fill-rose-500 text-rose-500" /> {{ wrapped.comments.best.likes }}
                                {{ tc('like|likes', wrapped.comments.best.likes) }} · {{ t('on ":title"', { title: wrapped.comments.best.on }) }}
                            </p>
                        </template>
                        <template v-else>
                            <p class="text-xl font-semibold text-white/80">{{ t('You wrote') }}</p>
                            <p class="text-[7rem] leading-none font-black">{{ wrapped.comments.written }}</p>
                            <p class="text-3xl font-extrabold">{{ wrapped.comments.written === 1 ? t('comment') : t('comments') }}</p>
                            <p class="mt-4 text-lg text-white/85">{{ t('Next year, aim for some likes. Now they exist. 😉') }}</p>
                        </template>
                    </template>

                    <!-- Summary: the card to share -->
                    <template v-else-if="current === 'summary'">
                        <p class="text-sm font-bold tracking-widest text-white/75 uppercase">{{ wrapped.name }}'s</p>
                        <p class="text-7xl leading-none font-black">{{ wrapped.year }}</p>
                        <div class="mt-5 grid grid-cols-2 gap-2">
                            <div class="rounded-2xl bg-white/12 p-3"><p class="text-3xl font-black">{{ wrapped.albumCount }}</p><p class="text-sm text-white/80">{{ t('albums') }}</p></div>
                            <div class="rounded-2xl bg-white/12 p-3"><p class="text-3xl font-black">{{ wrapped.daysAway }}</p><p class="text-sm text-white/80">{{ t('days away') }}</p></div>
                            <div class="rounded-2xl bg-white/12 p-3"><p class="text-3xl font-black">{{ number.format(wrapped.km) }}</p><p class="text-sm text-white/80">{{ t('km travelled') }}</p></div>
                            <div class="rounded-2xl bg-white/12 p-3"><p class="text-3xl font-black">{{ wrapped.countries.length }}</p><p class="text-sm text-white/80">{{ wrapped.countries.length === 1 ? t('country') : t('countries') }}</p></div>
                        </div>
                        <p class="mt-4 text-3xl">{{ wrapped.countries.map((c) => c.flag ?? '🏳️').join(' ') }}</p>
                        <p v-if="wrapped.friends.length" class="mt-3 text-sm text-white/80">{{ t('Top crew:') }} <strong class="text-white">{{ wrapped.friends.map((f) => f.name).join(' · ') }}</strong></p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <button v-if="canShare" type="button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 font-bold text-slate-900" @click="shareImage">
                                <Share2 class="size-5" /> {{ t('Share') }}
                            </button>
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-white/15 px-4 py-3 font-bold ring-1 ring-white/30 disabled:opacity-60" :disabled="saving" @click="saveImage">
                                <Download class="size-5" /> {{ t('Save image') }}
                            </button>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-semibold text-white/85 hover:bg-white/15" @click="go(0)">
                                <RotateCcw class="size-4" /> {{ t('Watch again') }}
                            </button>
                            <Link
                                v-for="other in years.filter((y) => y !== wrapped.year)"
                                :key="other"
                                :href="`/wrapped/${other}`"
                                class="rounded-full bg-white/10 px-3 py-1.5 font-semibold hover:bg-white/20"
                            >
                                {{ other }}
                            </Link>
                        </div>
                    </template>
                </div>
            </Transition>
        </div>
    </div>
</template>

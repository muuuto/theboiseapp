<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { intlLocale, t, tc } from '../i18n.js';
import { formatDate } from '../time.js';
import { kg } from '../gym.js';

// Progress of one exercise over time, one point per workout: the heaviest set and the estimated
// one-rep max (both kg, one axis). Bodyweight exercises (no kg): the most reps.
// Hover or touch a day to read its values
const props = defineProps({ points: Array }); // [{ date, best, oneRepMax, reps }]

// Drawn at the real width of its box (not scaled), so the labels stay readable on phones
const box = ref(null);
const width = ref(640);
const height = 240;
let observer = null;
onMounted(() => {
    observer = new ResizeObserver(([entry]) => (width.value = Math.max(240, Math.round(entry.contentRect.width))));
    observer.observe(box.value);
});
onBeforeUnmount(() => observer?.disconnect());
const pad = { top: 16, right: 56, bottom: 28, left: 40 };

const byWeight = computed(() => props.points.some((point) => point.best));
// Colors checked for colorblind separation on the light and dark backgrounds
const series = computed(() => (byWeight.value
    ? [
        { key: 'best', label: t('Heaviest set'), line: 'stroke-teal-600', dot: 'fill-teal-600', dashed: false },
        { key: 'oneRepMax', label: t('Estimated 1-rep max'), line: 'stroke-orange-700 dark:stroke-orange-600', dot: 'fill-orange-700 dark:fill-orange-600', dashed: true },
    ]
    : [{ key: 'reps', label: t('Most reps'), line: 'stroke-teal-600', dot: 'fill-teal-600', dashed: false }]));
const plainNumber = (value) => new Intl.NumberFormat(intlLocale(), { maximumFractionDigits: 1 }).format(value);
const format = (value) => (byWeight.value ? kg(value) : tc(':count rep|:count reps', value));

const time = (date) => new Date(`${date}T00:00:00`).getTime();
const scale = computed(() => {
    const values = props.points.flatMap((point) => series.value.map((line) => point[line.key])).filter((value) => value !== null && value !== undefined);
    let low = Math.min(...values);
    let high = Math.max(...values);
    const room = Math.max((high - low) * 0.15, byWeight.value ? 2.5 : 1);
    low = Math.max(0, low - room);
    high += room;
    // Round steps: 1, 2, 2.5, 5, 10, 20, 25, 50…
    const raw = (high - low) / 4;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((m) => m >= raw);
    low = Math.floor(low / step) * step;
    high = Math.ceil(high / step) * step;
    const ticks = [];
    for (let value = low; value <= high + step / 2; value += step) ticks.push(Math.round(value * 100) / 100);

    const first = time(props.points[0].date);
    const last = time(props.points.at(-1).date);
    const x = (date) => (first === last ? (pad.left + width.value - pad.right) / 2 : pad.left + ((time(date) - first) / (last - first)) * (width.value - pad.left - pad.right));
    const y = (value) => pad.top + (1 - (value - low) / (high - low)) * (height - pad.top - pad.bottom);

    return { x, y, ticks };
});

const lines = computed(() => series.value.map((line) => {
    const points = props.points.filter((point) => point[line.key] !== null && point[line.key] !== undefined);
    return {
        ...line,
        points,
        path: points.map((point, index) => `${index ? 'L' : 'M'}${scale.value.x(point.date).toFixed(1)},${scale.value.y(point[line.key]).toFixed(1)}`).join(' '),
    };
}));

// A few dates under the axis: first, last, and some in between that don't overlap
const dateTicks = computed(() => {
    const count = Math.min(props.points.length, Math.max(2, Math.floor(width.value / 110)));
    if (count === 1) return [props.points[0].date];
    const first = time(props.points[0].date);
    const last = time(props.points.at(-1).date);
    const picked = [];
    for (let i = 0; i < count; i++) {
        const target = first + ((last - first) * i) / (count - 1);
        const nearest = props.points.reduce((best, point) => (Math.abs(time(point.date) - target) < Math.abs(time(best.date) - target) ? point : best));
        if (!picked.includes(nearest.date)) picked.push(nearest.date);
    }
    return picked;
});
const spansYears = computed(() => props.points[0].date.slice(0, 4) !== props.points.at(-1).date.slice(0, 4));
const shortDate = (date) => formatDate(date, spansYears.value ? { month: 'short', year: '2-digit' } : { day: 'numeric', month: 'short' });

// Hover / touch: the nearest day
const svg = ref(null);
const active = ref(null);
function track(event) {
    const x = event.clientX - svg.value.getBoundingClientRect().left;
    active.value = props.points.reduce((best, point) => (Math.abs(scale.value.x(point.date) - x) < Math.abs(scale.value.x(best.date) - x) ? point : best));
}
const tooltipLeft = computed(() => active.value && (scale.value.x(active.value.date) / width.value) * 100);
// Near the edges the box opens inwards, so it never goes off the screen
const tooltipShift = computed(() => (tooltipLeft.value < 25 ? 'translate-x-0' : tooltipLeft.value > 75 ? '-translate-x-full' : '-translate-x-1/2'));
</script>

<template>
    <div>
        <!-- Legend (two lines: also told apart by solid / dashed) -->
        <div v-if="series.length > 1" class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
            <span v-for="line in series" :key="line.key" class="inline-flex items-center gap-1.5">
                <svg width="20" height="8" aria-hidden="true"><line x1="0" y1="4" x2="20" y2="4" stroke-width="2" :stroke-dasharray="line.dashed ? '4 3' : null" :class="line.line" /></svg>
                {{ line.label }}
            </span>
        </div>

        <div ref="box" class="relative">
            <svg
                ref="svg"
                :width="width"
                :height="height"
                class="block touch-pan-y overflow-visible select-none"
                role="img"
                :aria-label="series.map((line) => line.label).join(', ')"
                @pointermove="track"
                @pointerdown="track"
                @pointerleave="active = null"
            >
                <!-- Grid and axes: quiet -->
                <g v-for="tick in scale.ticks" :key="tick">
                    <line :x1="pad.left" :x2="width - pad.right" :y1="scale.y(tick)" :y2="scale.y(tick)" class="stroke-slate-200 dark:stroke-slate-800" stroke-width="1" />
                    <text :x="pad.left - 8" :y="scale.y(tick)" text-anchor="end" dominant-baseline="middle" class="fill-slate-500 text-[11px] tabular-nums">{{ plainNumber(tick) }}</text>
                </g>
                <text v-for="date in dateTicks" :key="date" :x="scale.x(date)" :y="height - 8" text-anchor="middle" class="fill-slate-500 text-[11px]">{{ shortDate(date) }}</text>

                <line v-if="active" :x1="scale.x(active.date)" :x2="scale.x(active.date)" :y1="pad.top" :y2="height - pad.bottom" class="stroke-slate-400 dark:stroke-slate-500" stroke-width="1" />

                <g v-for="line in lines" :key="line.key">
                    <path :d="line.path" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" :stroke-dasharray="line.dashed ? '6 4' : null" :class="line.line" />
                    <circle
                        v-for="point in line.points"
                        :key="point.date"
                        :cx="scale.x(point.date)"
                        :cy="scale.y(point[line.key])"
                        :r="active?.date === point.date ? 6 : 4"
                        stroke-width="2"
                        class="stroke-white dark:stroke-slate-900"
                        :class="line.dot"
                    />
                    <!-- The latest value, written at the end of the line -->
                    <text
                        v-if="line.points.length"
                        :x="scale.x(line.points.at(-1).date) + 10"
                        :y="scale.y(line.points.at(-1)[line.key])"
                        dominant-baseline="middle"
                        class="fill-slate-700 text-[11px] font-semibold dark:fill-slate-200"
                    >{{ plainNumber(line.points.at(-1)[line.key]) }}</text>
                </g>
            </svg>

            <div
                v-if="active"
                class="pointer-events-none absolute top-0 z-10 rounded-xl bg-white px-3 py-2 text-xs whitespace-nowrap shadow-lg ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"
                :class="tooltipShift"
                :style="{ left: `${tooltipLeft}%` }"
            >
                <p class="font-semibold">{{ formatDate(active.date, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}</p>
                <p v-for="line in series" :key="line.key" class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                    <svg width="14" height="8" aria-hidden="true"><line x1="0" y1="4" x2="14" y2="4" stroke-width="2" :stroke-dasharray="line.dashed ? '3 2' : null" :class="line.line" /></svg>
                    {{ line.label }}: <strong class="text-slate-900 dark:text-white">{{ active[line.key] ? format(active[line.key]) : '–' }}</strong>
                </p>
            </div>
        </div>
    </div>
</template>

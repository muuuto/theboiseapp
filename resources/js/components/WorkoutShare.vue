<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Download, Share2, X } from 'lucide-vue-next';
import { t, tc } from '../i18n.js';
import { formatDate } from '../time.js';
import { focusLabel, rowText, total } from '../gym.js';

// The day's workout as a picture (1080×1920, story size) to share or save: date, focus, totals
// and every exercise with its rows (🏆 on the records)
const props = defineProps({
    date: String,
    name: String,
    focus: String, // upper | lower | mixed | null
    exercises: Array, // [{ label, rows: [{ weight, sets, reps }], record }]
});

const open = ref(false);
const image = ref(null);

const W = 1080;
const H = 1920;
const font = (weight, size) => `${weight} ${size}px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`;

function draw() {
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, W, H);
    gradient.addColorStop(0, '#ef3b2d');
    gradient.addColorStop(0.45, '#7f1d1d');
    gradient.addColorStop(1, '#0f172a');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, W, H);

    // Cut to the width, with … at the end
    const fit = (value, max) => {
        let text = value;
        while (ctx.measureText(text).width > max && text.length > 1) text = text.slice(0, -2) + '…';
        return text;
    };
    const text = (value, x, y, weight, size, color = '#ffffff', align = 'left', max = W - 180) => {
        ctx.font = font(weight, size);
        ctx.fillStyle = color;
        ctx.textAlign = align;
        ctx.fillText(fit(value, max), x, y);
    };
    const soft = 'rgba(255,255,255,0.75)';

    text('THEBOISE GYM', 90, 160, 800, 44, soft);
    text(formatDate(props.date, { weekday: 'long' }), 90, 290, 900, 110);
    text(formatDate(props.date, { day: 'numeric', month: 'long', year: 'numeric' }), 90, 370, 700, 56, soft);
    let y = 460;
    if (props.focus) {
        ctx.font = font(800, 40);
        const label = focusLabel(props.focus).toUpperCase();
        const width = ctx.measureText(label).width + 64;
        ctx.fillStyle = 'rgba(255,255,255,0.18)';
        ctx.beginPath();
        ctx.roundRect(90, y - 50, width, 72, 36);
        ctx.fill();
        text(label, 122, y, 800, 40);
    }
    if (props.name) text(props.name, W - 90, y, 700, 44, soft, 'right', 420);

    // Totals
    const rows = props.exercises.flatMap((exercise) => exercise.rows);
    const sets = rows.reduce((sum, row) => sum + (row.sets || 0), 0);
    const volume = rows.reduce((sum, row) => sum + (row.weight || 0) * (row.reps || 0) * (row.sets || 0), 0);
    const records = props.exercises.filter((exercise) => exercise.record).length;
    const stats = [
        [String(props.exercises.length), tc('exercise|exercises', props.exercises.length)],
        [String(sets), tc('set|sets', sets)],
        volume ? [total(Math.round(volume)), t('lifted')] : null,
        records ? [`🏆 ${records}`, tc('record|records', records)] : null,
    ].filter(Boolean).slice(0, 3);
    const boxWidth = (W - 180 - (stats.length - 1) * 30) / stats.length;
    stats.forEach(([value, label], i) => {
        const x = 90 + i * (boxWidth + 30);
        ctx.fillStyle = 'rgba(255,255,255,0.12)';
        ctx.beginPath();
        ctx.roundRect(x, 540, boxWidth, 210, 36);
        ctx.fill();
        let size = 84;
        ctx.font = font(900, size);
        while (ctx.measureText(value).width > boxWidth - 60 && size > 40) ctx.font = font(900, (size -= 4));
        text(value, x + 30, 660, 900, size, '#ffffff', 'left', boxWidth - 60);
        text(label, x + 30, 715, 600, 36, soft, 'left', boxWidth - 60);
    });

    // The exercises: smaller when there are many, "+ 3 more" when they still don't fit
    const top = 840;
    const bottom = H - 150;
    let scale = Math.min(1, (bottom - top) / (props.exercises.length * 128));
    scale = Math.max(scale, 0.6);
    const step = 128 * scale;
    const fits = Math.floor((bottom - top) / step);
    const shown = props.exercises.length > fits ? props.exercises.slice(0, fits - 1) : props.exercises;
    y = top;
    for (const exercise of shown) {
        text(`${exercise.record ? '🏆 ' : ''}${exercise.label}`, 90, y + 50 * scale, 800, Math.round(52 * scale));
        text(exercise.rows.map(rowText).join(', ') || '–', 90, y + 104 * scale, 500, Math.round(40 * scale), soft);
        y += step;
    }
    if (shown.length < props.exercises.length) {
        text(t('+ :count more', { count: props.exercises.length - shown.length }), 90, y + 50 * scale, 700, Math.round(44 * scale), soft);
    }

    text('theboise.it', W - 90, H - 80, 800, 40, soft, 'right');

    image.value = canvas.toDataURL('image/png');
}

const fileName = () => `theboise-gym-${props.date}.png`;

function show() {
    draw();
    open.value = true;
}
const close = () => (open.value = false);
const onKey = (event) => event.key === 'Escape' && close();
watch(open, (on) => (on ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey)));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

// Phones: the image itself goes to WhatsApp, Instagram...
const canShareFile = typeof navigator !== 'undefined' && !!navigator.canShare;
async function share() {
    const blob = await (await fetch(image.value)).blob();
    const file = new File([blob], fileName(), { type: 'image/png' });
    try {
        if (navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: t('My workout') });
    } catch {
        // Closed the share sheet: nothing to do
    }
}

const actionClass = 'inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
</script>

<template>
    <button
        type="button"
        :disabled="!exercises.length"
        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-50 dark:ring-slate-700 dark:hover:bg-slate-800"
        @click="show"
    >
        <Share2 class="size-4" /> {{ t('Share') }}
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="close">
            <div role="dialog" aria-modal="true" :aria-label="t('Share the workout')" class="flex max-h-full w-full max-w-sm flex-col rounded-3xl bg-white p-4 shadow-xl dark:bg-slate-900">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-lg font-bold">{{ t('Share the workout') }}</h2>
                    <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="close">
                        <X class="size-5" /><span class="sr-only">{{ t('Close') }}</span>
                    </button>
                </div>
                <img :src="image" :alt="t('Summary of the workout')" class="mx-auto min-h-0 w-auto max-w-full flex-1 rounded-2xl object-contain" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-if="canShareFile" type="button" :class="actionClass" class="bg-brand text-white hover:bg-brand-dark" @click="share">
                        <Share2 class="size-4" /> {{ t('Share') }}
                    </button>
                    <a :href="image" :download="fileName()" :class="actionClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900">
                        <Download class="size-4" /> {{ t('Download') }}
                    </a>
                </div>
            </div>
        </div>
    </Teleport>
</template>

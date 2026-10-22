<script setup>
import { onBeforeUnmount, ref } from 'vue';
import QRCode from 'qrcode';
import { Check, Copy, Download, QrCode, Share2, X } from 'lucide-vue-next';

// A QR code of a page of the site (album, forum post), with the logo in the middle and the title
// under it, to download, share or show from the phone. Whoever scans it lands on that page
// (after logging in, if needed: the login brings them back to it)
const props = defineProps({
    path: { type: String, required: true },
    title: { type: String, required: true },
});

const open = ref(false);
const image = ref(null);
const url = () => window.location.origin + props.path;
const fileName = () => 'theboise-' + (props.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'qr') + '.png';

const SIZE = 1024;
const loadImage = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null); // no logo: the QR code still works
        img.src = src;
    });

function roundedRect(ctx, x, y, size, radius) {
    ctx.beginPath();
    ctx.roundRect(x, y, size, size, radius);
    ctx.fill();
}

async function draw() {
    // "H" correction: the code still reads with up to 30% hidden, the logo covers about 5%
    const qr = document.createElement('canvas');
    await QRCode.toCanvas(qr, url(), { errorCorrectionLevel: 'H', margin: 3, width: SIZE, color: { dark: '#0f172a', light: '#ffffff' } });

    const canvas = document.createElement('canvas');
    canvas.width = SIZE;
    canvas.height = SIZE + 150;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(qr, 0, 0);

    // Logo on a white rounded square in the middle
    const logo = await loadImage('/images/logo.png');
    if (logo) {
        const box = SIZE * 0.22;
        const x = (SIZE - box) / 2;
        ctx.fillStyle = '#ffffff';
        roundedRect(ctx, x, x, box, box * 0.2);
        const inner = box * 0.8;
        ctx.save();
        ctx.beginPath();
        ctx.roundRect((SIZE - inner) / 2, (SIZE - inner) / 2, inner, inner, inner * 0.18);
        ctx.clip();
        ctx.drawImage(logo, (SIZE - inner) / 2, (SIZE - inner) / 2, inner, inner);
        ctx.restore();
    }

    // Title and site under the code, for when it's printed
    ctx.textAlign = 'center';
    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 52px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    let title = props.title;
    while (ctx.measureText(title).width > SIZE - 120 && title.length > 1) title = title.slice(0, -2) + '…';
    ctx.fillText(title, SIZE / 2, SIZE + 30);
    ctx.fillStyle = '#0d9488';
    ctx.font = 'bold 34px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    ctx.fillText('THEBOISE', SIZE / 2, SIZE + 95);

    image.value = canvas.toDataURL('image/png');
}

async function show() {
    open.value = true;
    if (!image.value) await draw();
}
const close = () => (open.value = false);
const onKey = (event) => event.key === 'Escape' && close();
window.addEventListener('keydown', onKey);
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

// Phones: share the image itself (WhatsApp, Telegram...)
const canShareFile = typeof navigator !== 'undefined' && !!navigator.canShare;
async function share() {
    const blob = await (await fetch(image.value)).blob();
    const file = new File([blob], fileName(), { type: 'image/png' });
    try {
        if (navigator.canShare({ files: [file] })) {
            await navigator.share({ files: [file], title: props.title });
        } else {
            await navigator.share({ title: props.title, url: url() });
        }
    } catch {
        // Closed the share sheet: nothing to do
    }
}

const copied = ref(false);
async function copyLink() {
    await navigator.clipboard.writeText(url());
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

const actionClass = 'inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200 hover:ring-slate-300 dark:bg-slate-800 dark:ring-slate-700"
        @click="show"
    >
        <QrCode class="size-4" /> {{ t('QR code') }}
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="close">
            <div role="dialog" aria-modal="true" :aria-label="t('QR code')" class="w-full max-w-sm rounded-3xl bg-white p-5 shadow-xl dark:bg-slate-900">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-lg font-bold">{{ t('QR code') }}</h2>
                    <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="close">
                        <X class="size-5" /><span class="sr-only">{{ t('Close') }}</span>
                    </button>
                </div>

                <!-- Always on white, also in dark mode: phone cameras read dark on light best -->
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200 dark:ring-slate-700">
                    <img v-if="image" :src="image" :alt="t('QR code of :title', { title })" class="w-full" />
                    <div v-else class="aspect-square animate-pulse bg-slate-100" />
                </div>
                <p class="mt-3 text-center text-xs text-slate-500">
                    {{ t('Scanning it opens this page. People need a TheBoise account that can see it.') }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a v-if="image" :href="image" :download="fileName()" :class="actionClass" class="bg-brand text-white hover:bg-brand-dark">
                        <Download class="size-4" /> {{ t('Download') }}
                    </a>
                    <button v-if="canShareFile && image" type="button" :class="actionClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" @click="share">
                        <Share2 class="size-4" /> {{ t('Share') }}
                    </button>
                    <button type="button" :class="actionClass" class="ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="copyLink">
                        <component :is="copied ? Check : Copy" class="size-4" /> {{ copied ? t('Copied!') : t('Copy link') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

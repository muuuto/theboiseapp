<script setup>
import { computed, ref } from 'vue';
import { X } from 'lucide-vue-next';

// Holiday or birthday decoration of the home page: things falling from the sky, and a badge.
// The badge's ✕ hides the falling things in this browser until that holiday is over.
// Nothing falls for who asked their device for less motion
const props = defineProps({
    decoration: Object, // { key, falling: 'snow' | [emojis], message, season }
});

const storageKey = 'decoration-hidden';
const hidden = ref(read() === props.decoration.season);
function read() {
    try {
        return localStorage.getItem(storageKey);
    } catch {
        return null;
    }
}
function toggle() {
    hidden.value = !hidden.value;
    try {
        hidden.value ? localStorage.setItem(storageKey, props.decoration.season) : localStorage.removeItem(storageKey);
    } catch {}
}

// Random but fixed for the visit: where each one falls, how big, how fast
const snow = props.decoration.falling === 'snow';
const count = Math.round((snow ? 45 : 26) * (window.innerWidth < 640 ? 0.6 : 1));
const random = (min, max) => min + Math.random() * (max - min);
const particles = computed(() =>
    Array.from({ length: count }, (_, i) => ({
        id: i,
        left: random(0, 100),
        size: snow ? random(4, 10) : random(18, 34),
        duration: snow ? random(9, 18) : random(8, 15),
        // Already falling when the page opens
        delay: -random(0, 18),
        sway: random(15, 45) * (i % 2 ? 1 : -1),
        swayTime: random(3, 6),
        opacity: snow ? random(0.5, 0.95) : random(0.75, 1),
        emoji: snow ? (i % 6 === 0 ? '❄️' : null) : props.decoration.falling[i % props.decoration.falling.length],
    })),
);
</script>

<template>
    <div class="inline-flex items-center gap-1 rounded-full bg-white/20 py-1 pr-1 pl-3 text-sm font-semibold text-white ring-1 ring-white/40 backdrop-blur">
        <span>{{ decoration.message }}</span>
        <button
            type="button"
            class="rounded-full p-1 hover:bg-white/20"
            :title="hidden ? t('Show the decorations') : t('Hide the decorations')"
            :aria-label="hidden ? t('Show the decorations') : t('Hide the decorations')"
            @click="toggle"
        >
            <X v-if="!hidden" class="size-3.5" />
            <span v-else class="block px-1 text-xs">{{ t('show') }}</span>
        </button>
    </div>

    <Teleport to="body">
        <!-- Above everything, the menu and the drawer included (it never catches a click) -->
        <div v-if="!hidden" class="falling pointer-events-none fixed inset-0 z-[60] overflow-hidden" aria-hidden="true">
            <span
                v-for="p in particles"
                :key="p.id"
                class="particle absolute top-0"
                :style="{ left: `${p.left}%`, animationDuration: `${p.duration}s`, animationDelay: `${p.delay}s` }"
            >
                <span class="sway block" :style="{ '--sway': `${p.sway}px`, animationDuration: `${p.swayTime}s`, opacity: p.opacity }">
                    <span v-if="p.emoji" :style="{ fontSize: `${p.emoji && snow ? 14 : p.size}px` }">{{ p.emoji }}</span>
                    <span v-else class="flake block rounded-full" :style="{ width: `${p.size}px`, height: `${p.size}px` }" />
                </span>
            </span>
        </div>
    </Teleport>
</template>

<style scoped>
.particle {
    animation-name: fall;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    will-change: transform;
}
.sway {
    animation-name: sway;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    animation-direction: alternate;
}
/* White with a soft edge: visible on the light page and on the teal banner */
.flake {
    background: #fff;
    box-shadow: 0 0 3px rgba(100, 116, 139, 0.55);
}
@keyframes fall {
    from { transform: translateY(-10vh); }
    to { transform: translateY(110vh); }
}
@keyframes sway {
    from { transform: translateX(calc(var(--sway) * -1)) rotate(-12deg); }
    to { transform: translateX(var(--sway)) rotate(12deg); }
}
@media (prefers-reduced-motion: reduce) {
    .falling { display: none; }
}
</style>

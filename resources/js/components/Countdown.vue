<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Time left until a date (a trip starts at midnight, an event at its time), ticking every second
const props = defineProps({
    to: { type: String, required: true }, // YYYY-MM-DD
    time: { type: String, default: null }, // HH:MM (events)
    size: { type: String, default: 'large' }, // large | small
});

const now = ref(Date.now());
let timer = null;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(timer));

const parts = computed(() => {
    const [year, month, day] = props.to.split('-').map(Number);
    const [hour, minute] = (props.time ?? '00:00').split(':').map(Number);
    const left = Math.max(0, new Date(year, month - 1, day, hour, minute).getTime() - now.value);
    return {
        days: Math.floor(left / 86400000),
        hours: Math.floor((left / 3600000) % 24),
        minutes: Math.floor((left / 60000) % 60),
        seconds: Math.floor((left / 1000) % 60),
        over: left === 0,
    };
});
const two = (value) => String(value).padStart(2, '0');
</script>

<template>
    <span v-if="size === 'small'" class="tabular-nums">
        <template v-if="parts.days > 1">{{ t('in :count days', { count: parts.days }) }}</template>
        <template v-else-if="parts.days === 1">{{ t('tomorrow') }}</template>
        <template v-else-if="!parts.over">{{ t('in :hoursh :minutesm', { hours: parts.hours, minutes: two(parts.minutes) }) }}</template>
        <template v-else>{{ t('now') }}</template>
    </span>
    <div v-else class="grid grid-cols-4 gap-2 text-center" role="timer" :aria-label="t(':days days :hours hours to go', { days: parts.days, hours: parts.hours })">
        <div v-for="[value, label] in [[parts.days, parts.days === 1 ? t('day') : t('days')], [two(parts.hours), t('hours')], [two(parts.minutes), t('min')], [two(parts.seconds), t('sec')]]" :key="label" class="rounded-2xl bg-white/15 px-1 py-3 backdrop-blur">
            <span class="block text-3xl font-black tabular-nums sm:text-4xl">{{ value }}</span>
            <span class="text-xs font-semibold tracking-wide text-white/80 uppercase">{{ label }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';

// likes: { count, liked, names }
const props = defineProps({
    type: String,
    id: Number,
    likes: Object,
    disabled: Boolean,
});

const who = computed(() => {
    const names = props.likes.names;
    if (!names.length) return 'No likes yet';
    return names.length > 6 ? `${names.slice(0, 6).join(', ')} and ${names.length - 6} more` : names.join(', ');
});

function toggle() {
    if (props.disabled) return;
    router.post('/likes', { type: props.type, id: props.id }, { preserveScroll: true, preserveState: true, only: ['comments'] });
}
</script>

<template>
    <button
        type="button"
        class="group inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium transition"
        :class="[
            likes.liked ? 'text-brand' : 'text-slate-500 dark:text-slate-400',
            disabled ? 'cursor-default' : 'hover:bg-red-50 hover:text-brand dark:hover:bg-red-950/40',
        ]"
        :title="who"
        :aria-pressed="likes.liked"
        @click="toggle"
    >
        <Heart class="size-4 transition group-active:scale-125" :class="likes.liked && 'fill-current'" />
        <span v-if="likes.count">{{ likes.count }}</span>
        <span class="sr-only">{{ likes.liked ? 'Unlike' : 'Like' }} ({{ who }})</span>
    </button>
</template>

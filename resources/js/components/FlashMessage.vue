<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';

const page = usePage();
const visible = ref(false);
let timer;

watch(
    () => page.props.flash?.message,
    (message) => {
        if (!message) return;
        visible.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (visible.value = false), 2500);
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0 translate-y-2"
        leave-active-class="transition duration-200"
        leave-to-class="opacity-0 translate-y-2"
    >
        <div
            v-if="visible && page.props.flash?.message"
            class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-2xl bg-slate-900 px-5 py-3 text-sm font-medium text-white shadow-xl dark:bg-white dark:text-slate-900"
            role="status"
        >
            <CheckCircle2 class="size-5 text-green-400 dark:text-green-600" />
            {{ page.props.flash.message }}
        </div>
    </Transition>
</template>

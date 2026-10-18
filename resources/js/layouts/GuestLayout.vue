<script setup>
import FlashMessage from '../components/FlashMessage.vue';

// Pages for people not logged in (login, passwords, invites): a centered card, no sidebar
defineProps({ wide: Boolean });
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="/login" class="mb-8 flex items-center gap-2">
            <img src="/images/logo.png" alt="" class="h-11 w-auto" />
            <span class="text-2xl font-extrabold uppercase tracking-tight">The<span class="text-brand">Boise</span></span>
        </a>
        <div
            class="w-full rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8 dark:bg-slate-900 dark:ring-slate-800"
            :class="wide ? 'max-w-2xl' : 'max-w-md'"
        >
            <slot />
        </div>
        <FlashMessage />
    </div>
</template>

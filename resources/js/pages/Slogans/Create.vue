<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Quote } from 'lucide-vue-next';

const form = useForm({ sloganPhrase: '' });
</script>

<template>
    <Head title="Post a slogan" />

    <Link href="/" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> Albums
    </Link>

    <form class="mx-auto max-w-xl rounded-3xl bg-gradient-to-br from-teal-600 to-teal-700 p-6 text-white shadow-lg sm:p-8" @submit.prevent="form.post('/slogan')">
        <h1 class="flex items-center gap-2 text-2xl font-extrabold"><Quote class="size-6" /> Post a slogan</h1>
        <p class="mt-1 text-sm text-teal-100">It will show up at random on the albums page, with your name.</p>
        <input
            v-model="form.sloganPhrase"
            type="text"
            maxlength="255"
            placeholder="Example: Zeccagay XD"
            class="mt-5 h-12 w-full rounded-xl border-0 bg-white/95 px-4 text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-white focus:outline-none"
        />
        <p v-if="form.errors.sloganPhrase" class="mt-1 text-sm text-red-200">{{ form.errors.sloganPhrase }}</p>
        <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 font-semibold transition hover:bg-black disabled:opacity-50" :disabled="form.processing || !form.sloganPhrase.trim()">
            <Loader2 v-if="form.processing" class="size-4 animate-spin" /> Post it
        </button>
    </form>
</template>

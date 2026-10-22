<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Loader2 } from 'lucide-vue-next';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FormField from '../../components/FormField.vue';

defineOptions({ layout: GuestLayout });

const page = usePage();
const form = useForm({ email: '' });
</script>

<template>
    <Head :title="t('Forgot password')" />

    <h1 class="text-2xl font-bold">{{ t('Forgot your password?') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ t("Write your email: we'll send you a link to choose a new one.") }}</p>

    <p v-if="page.props.flash?.status" class="mt-4 rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
        {{ page.props.flash.status }}
    </p>

    <form class="mt-6 space-y-4" @submit.prevent="form.post('/forgot-password')">
        <FormField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50"
            :disabled="form.processing"
        >
            <Loader2 v-if="form.processing" class="size-5 animate-spin" /> {{ t('Send the link') }}
        </button>
    </form>

    <Link href="/login" class="mt-6 flex items-center justify-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
        <ArrowLeft class="size-4" /> {{ t('Back to login') }}
    </Link>
</template>

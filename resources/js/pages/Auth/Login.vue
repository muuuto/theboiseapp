<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FormField from '../../components/FormField.vue';
import RequiredNote from '../../components/RequiredNote.vue';

defineOptions({ layout: GuestLayout });

const status = usePage().props.flash?.status;
const form = useForm({ email: '', password: '', remember: true });

function submit() {
    form.post('/users/authenticate', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('Login')" />

    <h1 class="text-2xl font-bold">{{ t('Welcome back') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ t('Log into your account') }}</p>

    <p v-if="status" class="mt-4 rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">{{ status }}</p>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <FormField v-model="form.email" required label="Email" type="email" autocomplete="email" :error="form.errors.email" />
        <FormField v-model="form.password" required label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2">
                <input v-model="form.remember" type="checkbox" class="size-4 accent-brand" />
                {{ t('Remember me') }}
            </label>
            <Link href="/forgot-password" class="font-medium text-brand hover:underline">{{ t('Forgot password?') }}</Link>
        </div>

        <RequiredNote />

        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50"
            :disabled="form.processing"
        >
            <Loader2 v-if="form.processing" class="size-5 animate-spin" /> {{ t('Sign in') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        {{ t("Don't have an account? TheBoise is invite only: ask a friend for an invite link.") }}
    </p>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FormField from '../../components/FormField.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps({ token: String });
const form = useForm({ invite: props.token, name: '', email: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Create your account" />

    <h1 class="text-2xl font-bold">You've been invited 🎉</h1>
    <p class="mt-1 text-sm text-slate-500">Create your account on TheBoise</p>

    <p v-if="form.errors.invite" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ form.errors.invite }}</p>

    <form class="mt-6 space-y-4" @submit.prevent="form.post('/users', { onFinish: () => form.reset('password', 'password_confirmation') })">
        <FormField v-model="form.name" label="Name" autocomplete="name" :error="form.errors.name" />
        <FormField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
        <FormField v-model="form.password" label="Password" type="password" autocomplete="new-password" hint="At least 6 characters" :error="form.errors.password" />
        <FormField v-model="form.password_confirmation" label="Repeat the password" type="password" autocomplete="new-password" />
        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50"
            :disabled="form.processing"
        >
            <Loader2 v-if="form.processing" class="size-5 animate-spin" /> Create account
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        Already have an account? <Link href="/login" class="font-medium text-brand hover:underline">Login</Link>
    </p>
</template>

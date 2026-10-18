<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FormField from '../../components/FormField.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <Head title="New password" />

    <h1 class="text-2xl font-bold">Choose a new password</h1>

    <form class="mt-6 space-y-4" @submit.prevent="form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })">
        <FormField v-model="form.email" label="Email" type="email" autocomplete="email" :error="form.errors.email" />
        <FormField v-model="form.password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters" :error="form.errors.password" />
        <FormField v-model="form.password_confirmation" label="Repeat the new password" type="password" autocomplete="new-password" />
        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50"
            :disabled="form.processing"
        >
            <Loader2 v-if="form.processing" class="size-5 animate-spin" /> Save the new password
        </button>
    </form>
</template>

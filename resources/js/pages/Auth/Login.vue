<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Fingerprint, Loader2 } from 'lucide-vue-next';
import { t } from '../../i18n.js';
import { passkeysAvailable, signInWithPasskey } from '../../passkeys.js';
import GuestLayout from '../../layouts/GuestLayout.vue';
import FormField from '../../components/FormField.vue';
import RequiredNote from '../../components/RequiredNote.vue';

defineOptions({ layout: GuestLayout });

const status = usePage().props.flash?.status;
const form = useForm({ email: '', password: '', remember: true });

function submit() {
    form.post('/users/authenticate', { onFinish: () => form.reset('password') });
}

// Phones: Face ID / fingerprint, for who turned it on in Account (the password still works)
const passkeys = ref(false);
const passkeyBusy = ref(false);
const passkeyError = ref('');
onMounted(async () => (passkeys.value = await passkeysAvailable()));
async function passkeyLogin() {
    passkeyBusy.value = true;
    passkeyError.value = '';
    try {
        router.visit(await signInWithPasskey());
    } catch (error) {
        // Closing the Face ID prompt isn't an error worth a message
        if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') passkeyError.value = error.message;
        passkeyBusy.value = false;
    }
}
</script>

<template>
    <Head :title="t('Login')" />

    <h1 class="text-2xl font-bold">{{ t('Welcome back') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ t('Log into your account') }}</p>

    <p v-if="status" class="mt-4 rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">{{ status }}</p>

    <div v-if="passkeys" class="mt-6">
        <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
            :disabled="passkeyBusy"
            @click="passkeyLogin"
        >
            <Loader2 v-if="passkeyBusy" class="size-5 animate-spin" /><Fingerprint v-else class="size-5" /> {{ t('Sign in with Face ID / fingerprint') }}
        </button>
        <p v-if="passkeyError" class="mt-2 text-sm text-red-600">{{ passkeyError }}</p>
        <p class="mt-4 flex items-center gap-3 text-xs text-slate-400 before:h-px before:flex-1 before:bg-slate-200 after:h-px after:flex-1 after:bg-slate-200 dark:before:bg-slate-700 dark:after:bg-slate-700">{{ t('or with your password') }}</p>
    </div>

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

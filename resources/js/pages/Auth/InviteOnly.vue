<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { MailQuestion } from 'lucide-vue-next';
import GuestLayout from '../../layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });
</script>

<template>
    <Head :title="t('Invite only')" />

    <div class="text-center">
        <MailQuestion class="mx-auto size-12 text-brand" />
        <h1 class="mt-4 text-2xl font-bold">{{ t('Invite only') }}</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">
            {{ t('TheBoise is private: you can register only with an invite link. If you received one, it may be expired or already used: ask for a new one.') }}
        </p>
        <p class="mt-6 text-sm text-slate-500">
            {{ t('Already have an account?') }} <Link href="/login" class="font-medium text-brand hover:underline">{{ t('Login') }}</Link>
        </p>
    </div>
</template>

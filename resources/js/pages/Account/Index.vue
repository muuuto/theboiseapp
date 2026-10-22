<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Cake, Camera, ImagePlus, KeyRound, Loader2, Monitor, Moon, Sun, Wallet } from 'lucide-vue-next';
import FormField from '../../components/FormField.vue';
import RequiredNote from '../../components/RequiredNote.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import AppNotifications from '../../components/AppNotifications.vue';
import LanguageSwitch from '../../components/LanguageSwitch.vue';
import OwnAlbumsToggle from '../../components/OwnAlbumsToggle.vue';

const props = defineProps({ profile: Object, wallet: Object, push: Object, ownFilter: Object });

const user = computed(() => usePage().props.auth.user);
const readOnly = computed(() => user.value.isDemo);

// Profile: name, photo, birthday
const profile = useForm({ name: props.profile.name, birthday: props.profile.birthday ?? '', profilePicture: null });
const avatarPreview = ref(props.profile.avatar);
function onAvatar(event) {
    const file = event.target.files[0];
    profile.profilePicture = file ?? null;
    if (file) avatarPreview.value = URL.createObjectURL(file);
}
function saveProfile() {
    profile.transform((data) => ({ ...data, _method: 'put' })).post('/account/update', { forceFormData: true, preserveScroll: true });
}

// Password
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
function savePassword() {
    password.put('/account/password', { preserveScroll: true, onSuccess: () => password.reset() });
}

// Theme (same choice as the button in the top bar, saved on the account)
const themes = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'Same as device', icon: Monitor },
];
const theme = computed(() => (user.value.darkMode === null ? 'system' : user.value.darkMode ? 'dark' : 'light'));
function setTheme(value) {
    const dark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    try {
        localStorage.setItem('theme', value);
    } catch {}
    router.put('/account/theme', { theme: value }, { preserveScroll: true });
}

// Wallet: one personal photo with a name
const wallet = useForm({ mediaName: props.wallet?.name ?? '', addWallet: null });
const walletPreview = ref(props.wallet?.url ?? null);
function onWallet(event) {
    const file = event.target.files[0];
    wallet.addWallet = file ?? null;
    if (file) walletPreview.value = URL.createObjectURL(file);
}
function saveWallet() {
    wallet.transform((data) => ({ ...data, _method: 'put' })).post('/account/addWallet', { forceFormData: true, preserveScroll: true });
}

const cardClass = 'rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6 dark:bg-slate-900 dark:ring-slate-800';
const buttonClass = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-dark disabled:opacity-50';
</script>

<template>
    <Head :title="t('Account')" />

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ t('Your account') }}</h1>
    <p v-if="readOnly" class="mb-6 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
        {{ t('This is a demo account: it can only look around.') }}
    </p>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Profile -->
        <form :class="cardClass" class="space-y-4" @submit.prevent="saveProfile">
            <h2 class="text-lg font-bold">{{ t('Profile') }}</h2>
            <div class="flex items-center gap-4">
                <label class="group relative size-20 shrink-0 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800" :class="!readOnly && 'cursor-pointer'">
                    <img v-if="avatarPreview" :src="avatarPreview" alt="" class="size-full object-cover" />
                    <span v-else class="flex size-full items-center justify-center text-2xl font-bold text-slate-500">{{ profile.name.charAt(0).toUpperCase() }}</span>
                    <span v-if="!readOnly" class="absolute inset-0 flex items-center justify-center bg-black/40 text-white opacity-0 transition group-hover:opacity-100">
                        <Camera class="size-6" />
                    </span>
                    <input v-if="!readOnly" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onAvatar" />
                </label>
                <div class="min-w-0 text-sm">
                    <p class="truncate font-semibold">{{ props.profile.email }}</p>
                    <p v-if="!readOnly" class="text-slate-500">{{ t('Click the photo to change it') }}</p>
                </div>
            </div>
            <p v-if="profile.errors.profilePicture" class="text-sm text-red-600">{{ profile.errors.profilePicture }}</p>

            <fieldset :disabled="readOnly" class="space-y-4">
                <FormField v-model="profile.name" required label="Name" autocomplete="nickname" :error="profile.errors.name" />
                <FormField v-model="profile.birthday" type="date" label="Birthday" hint="For the birthday email 🎂" :error="profile.errors.birthday" />
            </fieldset>
            <RequiredNote v-if="!readOnly" />
            <button v-if="!readOnly" type="submit" :class="buttonClass" :disabled="profile.processing">
                <Loader2 v-if="profile.processing" class="size-4 animate-spin" />
                <Cake v-else class="size-4" /> {{ t('Save profile') }}
            </button>
        </form>

        <div class="space-y-6">
            <!-- Preferences: theme, language, which albums -->
            <section :class="cardClass" class="space-y-5">
                <h2 class="text-lg font-bold">{{ t('Preferences') }}</h2>
                <div>
                <h3 class="mb-2 text-sm font-semibold">{{ t('Theme') }}</h3>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="option in themes"
                        :key="option.value"
                        type="button"
                        class="flex flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-sm font-medium ring-1 transition"
                        :class="theme === option.value
                            ? 'bg-brand/10 text-brand ring-brand'
                            : 'ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800'"
                        :disabled="readOnly"
                        @click="setTheme(option.value)"
                    >
                        <component :is="option.icon" class="size-5" />
                        {{ t(option.label) }}
                    </button>
                </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="block text-sm font-semibold">{{ t('Language') }}</span>
                        <span class="text-xs text-slate-500">{{ t('The site, the emails and the notifications') }}</span>
                    </span>
                    <LanguageSwitch />
                </div>

                <div v-if="ownFilter?.available" class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="block text-sm font-semibold">{{ t('Album list and map') }}</span>
                        <span class="text-xs text-slate-500">{{ t('Every album you\'re in, or only the ones you posted') }}</span>
                    </span>
                    <OwnAlbumsToggle :on="ownFilter.on" saves />
                </div>
            </section>

            <!-- Password -->
            <form v-if="!readOnly" :class="cardClass" class="space-y-4" @submit.prevent="savePassword">
                <h2 class="text-lg font-bold">{{ t('Change password') }}</h2>
                <FormField v-model="password.current_password" required type="password" label="Current password" autocomplete="current-password" :error="password.errors.current_password" />
                <FormField v-model="password.password" required type="password" label="New password" autocomplete="new-password" hint="At least 8 characters" :error="password.errors.password" />
                <FormField v-model="password.password_confirmation" required type="password" label="Repeat the new password" autocomplete="new-password" />
                <RequiredNote v-if="!readOnly" />
                <button type="submit" :class="buttonClass" :disabled="password.processing">
                    <Loader2 v-if="password.processing" class="size-4 animate-spin" />
                    <KeyRound v-else class="size-4" /> {{ t('Change password') }}
                </button>
            </form>
        </div>

        <!-- The app on the phone and notifications (not for the demo account; null before the migration) -->
        <section v-if="push" :class="cardClass" class="lg:col-span-2">
            <h2 class="mb-4 text-lg font-bold">{{ t('App & notifications') }}</h2>
            <AppNotifications v-bind="push" />
        </section>

        <!-- Wallet -->
        <form :class="cardClass" class="space-y-4 lg:col-span-2" @submit.prevent="saveWallet">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold"><Wallet class="size-5 text-accent" /> {{ t('Your wallet') }}</h2>
                <p class="text-sm text-slate-500">{{ t('One personal photo on your profile: a new one replaces the old one.') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-[16rem_minmax(0,1fr)]">
                <label class="block overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700" :class="!readOnly && 'cursor-pointer hover:ring-accent'">
                    <img v-if="walletPreview" :src="walletPreview" alt="" class="max-h-72 w-full object-contain" />
                    <span v-else class="flex h-40 flex-col items-center justify-center gap-2 text-sm text-slate-500"><ImagePlus class="size-7" /> <span>{{ readOnly ? t('No photo') : t('Choose a photo') }}<RequiredMark v-if="!readOnly" /></span></span>
                    <input v-if="!readOnly" type="file" accept="image/*" class="sr-only" @change="onWallet" />
                </label>
                <div class="space-y-4">
                    <fieldset :disabled="readOnly">
                        <FormField v-model="wallet.mediaName" required label="Name of the photo" :error="wallet.errors.mediaName" />
                    </fieldset>
                    <p v-if="wallet.errors.addWallet" class="text-sm text-red-600">{{ wallet.errors.addWallet }}</p>
                    <RequiredNote v-if="!readOnly" />
                    <button v-if="!readOnly" type="submit" :class="buttonClass" :disabled="wallet.processing || !wallet.addWallet">
                        <Loader2 v-if="wallet.processing" class="size-4 animate-spin" /> {{ t('Save to wallet') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

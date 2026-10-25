<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Cake, Camera, Fingerprint, Home, ImagePlus, KeyRound, Loader2, MapPin, Monitor, Moon, Search, Smartphone, Sun, Trash2, Wallet } from 'lucide-vue-next';
import { addPasskey, isPhone, passkeysAvailable } from '../../passkeys.js';
import { ago } from '../../time.js';
import { t } from '../../i18n.js';
import FormField from '../../components/FormField.vue';
import RequiredNote from '../../components/RequiredNote.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import AppNotifications from '../../components/AppNotifications.vue';
import LanguageSwitch from '../../components/LanguageSwitch.vue';
import OwnAlbumsToggle from '../../components/OwnAlbumsToggle.vue';

const props = defineProps({ profile: Object, wallet: Object, push: Object, ownFilter: Object, home: Object, passkeys: Array });

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

// Hometown: where Wrapped counts the kilometres from. Searched on OpenStreetMap on button press
// (their rules forbid search-as-you-type), like the places of an album
const homeSearch = ref(null); // null = closed, otherwise the text searched
const homeResults = ref([]);
const homeError = ref('');
const homeSearching = ref(false);
async function searchHome() {
    const query = (homeSearch.value ?? '').trim();
    if (!query) return;
    homeSearching.value = true;
    homeError.value = '';
    try {
        const params = new URLSearchParams({ q: query, format: 'jsonv2', limit: '5', 'accept-language': 'it,en' });
        homeResults.value = await (await fetch(`https://nominatim.openstreetmap.org/search?${params}`)).json();
        if (!homeResults.value.length) homeError.value = t('Nothing found: try a nearby town.');
    } catch {
        homeError.value = t('Search not available right now, try again later.');
    } finally {
        homeSearching.value = false;
    }
}
function pickHome(result) {
    const name = (result.name || result.display_name.split(',')[0]).slice(0, 100);
    router.put('/account/home', { name, latitude: +(+result.lat).toFixed(7), longitude: +(+result.lon).toFixed(7) }, {
        preserveScroll: true,
        onSuccess: () => {
            homeSearch.value = null;
            homeResults.value = [];
        },
    });
}
const resetHome = () => router.put('/account/home', { reset: true }, { preserveScroll: true });

// Face ID / fingerprint login: turned on from the phone itself (computers keep the password)
const canAddPasskey = ref(false);
const onPhone = isPhone();
const passkeyBusy = ref(false);
const passkeyError = ref('');
onMounted(async () => (canAddPasskey.value = await passkeysAvailable()));
async function turnOnPasskey() {
    passkeyBusy.value = true;
    passkeyError.value = '';
    try {
        await addPasskey();
        router.reload({ only: ['passkeys', 'flash'] });
    } catch (error) {
        if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') passkeyError.value = error.message;
    } finally {
        passkeyBusy.value = false;
    }
}
function removePasskey(passkey) {
    if (window.confirm(t('Stop logging in with Face ID / fingerprint on ":name"? The password keeps working.', { name: passkey.name }))) {
        router.delete(`/account/passkeys/${passkey.id}`, { preserveScroll: true });
    }
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

    <!-- Two rows on large screens: profile | preferences, password | wallet. Each row starts at the
         same height and each card keeps its own height (no empty space inside a card) -->
    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
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

            <div v-if="home">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="block text-sm font-semibold">{{ t('Hometown') }}</span>
                        <span class="text-xs text-slate-500">{{ t('Where your trips start: Wrapped counts the kilometres from here') }}</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium dark:bg-slate-800">
                            <Home class="size-3.5" /> {{ home.name }}<span v-if="!home.custom" class="text-xs text-slate-500">&nbsp;{{ t('(default)') }}</span>
                        </span>
                        <button v-if="!readOnly && homeSearch === null" type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 dark:ring-slate-700 dark:hover:bg-slate-800" @click="homeSearch = ''">{{ t('Change') }}</button>
                    </span>
                </div>
                <form v-if="homeSearch !== null" class="mt-3 space-y-2" @submit.prevent="searchHome">
                    <div class="flex gap-2">
                        <input
                            v-model="homeSearch"
                            type="search"
                            :placeholder="t('Your town, e.g. Milano')"
                            :aria-label="t('Hometown')"
                            class="h-10 min-w-0 flex-1 rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-950 dark:ring-slate-700"
                        />
                        <button type="submit" :disabled="homeSearching || !homeSearch.trim()" class="inline-flex items-center gap-1.5 rounded-xl px-3 text-sm font-semibold ring-1 ring-slate-200 hover:bg-slate-100 disabled:opacity-50 dark:ring-slate-700 dark:hover:bg-slate-800">
                            <Loader2 v-if="homeSearching" class="size-4 animate-spin" /><Search v-else class="size-4" /> {{ t('Search') }}
                        </button>
                    </div>
                    <ul v-if="homeResults.length" class="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200 dark:divide-slate-800 dark:ring-slate-700">
                        <li v-for="result in homeResults" :key="result.place_id">
                            <button type="button" class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="pickHome(result)">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-accent" /> {{ result.display_name }}
                            </button>
                        </li>
                    </ul>
                    <p v-if="homeError" class="text-sm text-red-600">{{ homeError }}</p>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <button type="button" class="font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white" @click="homeSearch = null; homeResults = []; homeError = ''">{{ t('Cancel') }}</button>
                        <button v-if="home.custom" type="button" class="font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white" @click="resetHome">{{ t('Back to Bolzano') }}</button>
                    </div>
                </form>
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

            <!-- Face ID / fingerprint on the phone (password as the backup) -->
            <div v-if="passkeys" class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <div>
                    <h3 class="flex items-center gap-2 font-bold"><Fingerprint class="size-5 text-accent" /> {{ t('Face ID / fingerprint on the phone') }}</h3>
                    <p class="text-sm text-slate-500">{{ t('Log in on your phone with a glance or a touch. The password keeps working everywhere.') }}</p>
                </div>
                <ul v-if="passkeys.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="passkey in passkeys" :key="passkey.id" class="flex items-center gap-3 py-2">
                        <Smartphone class="size-5 shrink-0 text-slate-400" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ passkey.name }}</span>
                            <span class="text-xs text-slate-500">{{ passkey.lastUsedAt ? t('Last used :when', { when: ago(passkey.lastUsedAt) }) : t('Added :when', { when: ago(passkey.createdAt) }) }}</span>
                        </span>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" :aria-label="t('Remove :name', { name: passkey.name })" @click="removePasskey(passkey)">
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>
                <button v-if="canAddPasskey" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-accent ring-1 ring-slate-200 hover:bg-teal-50 disabled:opacity-50 dark:ring-slate-700 dark:hover:bg-slate-800" :disabled="passkeyBusy" @click="turnOnPasskey">
                    <Loader2 v-if="passkeyBusy" class="size-4 animate-spin" /><Fingerprint v-else class="size-4" /> {{ t('Turn on for this phone') }}
                </button>
                <p v-else-if="!onPhone" class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800/60">{{ t('Open this page on your phone to turn it on there.') }}</p>
                <p v-else class="text-xs text-slate-500">{{ t('This phone has no Face ID, fingerprint or screen lock to use.') }}</p>
                <p v-if="passkeyError" class="text-sm text-red-600">{{ passkeyError }}</p>
            </div>
        </form>

        <!-- Wallet -->
        <form :class="cardClass" class="space-y-4" @submit.prevent="saveWallet">
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

        <!-- The app on the phone and notifications (not for the demo account; null before the migration) -->
        <section v-if="push" :class="cardClass" class="lg:col-span-2">
            <h2 class="mb-4 text-lg font-bold">{{ t('App & notifications') }}</h2>
            <AppNotifications v-bind="push" />
        </section>
    </div>
</template>

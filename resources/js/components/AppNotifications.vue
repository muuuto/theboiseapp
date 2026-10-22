<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, BellOff, Download, Loader2, Share, Smartphone, SquarePlus, Trash2 } from 'lucide-vue-next';
import { endpointHash, installPrompt, isInstalled, isIos, onInstallPrompt, pushState, turnOff, turnOn } from '../push.js';
import { ago } from '../time.js';

// Account page: the app on the home screen, notifications on this device, and what to receive
const props = defineProps({
    publicKey: String,
    settings: Array, // [{ key, label, email (null = no email for it), push }]
    devices: Array,
});

const installed = isInstalled();
const ios = isIos();
const canInstall = ref(!!installPrompt);
onInstallPrompt(() => (canInstall.value = true));
async function install() {
    installPrompt?.prompt();
    const choice = await installPrompt?.userChoice;
    if (choice?.outcome === 'accepted') canInstall.value = false;
}

// This device
const state = ref(pushState());
const thisDevice = ref(null); // its endpoint hash when notifications are on here
const devices = ref(props.devices);
const busy = ref(false);
const error = ref('');
onMounted(async () => (thisDevice.value = await endpointHash().catch(() => null)));
const onHere = computed(() => !!thisDevice.value && devices.value.some((device) => device.endpointHash === thisDevice.value));

async function enable() {
    busy.value = true;
    error.value = '';
    try {
        devices.value = await turnOn(props.publicKey);
        thisDevice.value = await endpointHash();
    } catch (e) {
        error.value = e.message;
        state.value = pushState();
    } finally {
        busy.value = false;
    }
}
async function disable() {
    busy.value = true;
    error.value = '';
    try {
        devices.value = (await turnOff()) ?? devices.value;
        thisDevice.value = null;
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
function forget(device) {
    router.delete(`/push/subscriptions/${device.id}`, {
        preserveScroll: true,
        onSuccess: () => (devices.value = devices.value.filter((other) => other.id !== device.id)),
    });
}
const testing = ref(false);
function test() {
    testing.value = true;
    router.post('/push/test', {}, { preserveScroll: true, onFinish: () => (testing.value = false) });
}

// What to receive: saved at each click
const choices = ref(props.settings.map((row) => ({ ...row })));
function save() {
    const settings = Object.fromEntries(choices.value.map((row) => [row.key, row.email === null ? { push: row.push } : { push: row.push, email: row.email }]));
    router.put('/push/settings', { settings }, { preserveScroll: true, preserveState: true });
}

const buttonClass = 'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-50';
</script>

<template>
    <div class="space-y-6">
        <!-- The app -->
        <div class="space-y-3">
            <h3 class="flex items-center gap-2 font-semibold"><Smartphone class="size-5 text-accent" /> {{ t('TheBoise on your home screen') }}</h3>
            <p v-if="installed" class="rounded-xl bg-teal-50 px-3 py-2 text-sm text-teal-800 dark:bg-teal-950/40 dark:text-teal-300">{{ t('You\'re using the app. 🎉') }}</p>
            <template v-else-if="ios">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('On iPhone, add it from Safari (notifications work only in the app):') }}</p>
                <ol class="space-y-2 text-sm">
                    <li class="flex items-center gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold dark:bg-slate-800">1</span> {{ t('Tap') }} <Share class="inline size-4 text-sky-600" /> <strong>{{ t('Share') }}</strong> {{ t('at the bottom of Safari') }}</li>
                    <li class="flex items-center gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold dark:bg-slate-800">2</span> {{ t('Choose') }} <SquarePlus class="inline size-4" /> <strong>{{ t('Add to Home Screen') }}</strong></li>
                    <li class="flex items-center gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold dark:bg-slate-800">3</span> {{ t('Open TheBoise from the new icon and come back here') }}</li>
                </ol>
            </template>
            <template v-else>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('Its own icon, full screen, like a real app.') }}</p>
                <button v-if="canInstall" type="button" :class="buttonClass" class="bg-slate-900 text-white hover:bg-black dark:bg-white dark:text-slate-900" @click="install">
                    <Download class="size-4" /> {{ t('Install the app') }}
                </button>
                <p v-else class="text-sm text-slate-500">{{ t('Use your browser\'s menu:') }} <strong>{{ t('Install app') }}</strong> {{ t('or') }} <strong>{{ t('Add to Home screen') }}</strong>.</p>
            </template>
        </div>

        <!-- Notifications on this device -->
        <div class="space-y-3">
            <h3 class="flex items-center gap-2 font-semibold"><Bell class="size-5 text-accent" /> {{ t('Notifications on this device') }}</h3>
            <p v-if="state === 'install-first'" class="text-sm text-slate-600 dark:text-slate-400">{{ t('Add TheBoise to your home screen first (above), then turn them on from the app.') }}</p>
            <p v-else-if="state === 'unsupported'" class="text-sm text-slate-600 dark:text-slate-400">{{ t('This browser can\'t receive notifications.') }}</p>
            <p v-else-if="state === 'denied'" class="text-sm text-slate-600 dark:text-slate-400">{{ t('Notifications are blocked for TheBoise: allow them in this browser\'s or phone\'s settings, then reload.') }}</p>
            <div v-else class="flex flex-wrap items-center gap-2">
                <button v-if="!onHere" type="button" :class="buttonClass" class="bg-brand text-white hover:bg-brand-dark" :disabled="busy" @click="enable">
                    <component :is="busy ? Loader2 : Bell" class="size-4" :class="busy && 'animate-spin'" /> {{ t('Turn on notifications') }}
                </button>
                <template v-else>
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-800 dark:bg-teal-950/40 dark:text-teal-300"><Bell class="size-4" /> {{ t('On for this device') }}</span>
                    <button type="button" :class="buttonClass" class="ring-1 ring-slate-200 hover:bg-slate-50 dark:ring-slate-700 dark:hover:bg-slate-800" :disabled="busy" @click="disable">
                        <BellOff class="size-4" /> {{ t('Turn off') }}
                    </button>
                </template>
            </div>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

            <div v-if="devices.length" class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/60">
                <div class="mb-1 flex items-center gap-2">
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ t('Your devices with notifications') }}</p>
                    <button type="button" class="ml-auto text-sm font-semibold text-accent hover:underline disabled:opacity-50" :disabled="testing" @click="test">
                        {{ testing ? t('Sending…') : t('Send a test') }}
                    </button>
                </div>
                <ul>
                    <li v-for="device in devices" :key="device.id" class="flex items-center gap-2 py-1 text-sm">
                        <Smartphone class="size-4 shrink-0 text-slate-400" />
                        <span class="flex-1">{{ device.device }}<span v-if="device.endpointHash === thisDevice" class="ml-1 text-slate-500">{{ t('(this one)') }}</span></span>
                        <span class="text-xs text-slate-500">{{ t('since :when', { when: ago(device.since) }) }}</span>
                        <button type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" :title="`Remove ${device.device}`" @click="forget(device)">
                            <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove :name', { name: device.device }) }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- What to receive -->
        <div class="space-y-2">
            <h3 class="font-semibold">{{ t('What you receive') }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-slate-500">
                            <th class="py-1 pr-2 text-left font-medium"></th>
                            <th class="w-16 py-1 text-center font-medium">{{ t('Phone') }}</th>
                            <th class="w-16 py-1 text-center font-medium">{{ t('Email') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in choices" :key="row.key">
                            <td class="py-2 pr-2">{{ row.label }}</td>
                            <td class="text-center"><input v-model="row.push" type="checkbox" class="size-5 accent-teal-600" :aria-label="t(':what on the phone', { what: row.label })" @change="save" /></td>
                            <td class="text-center">
                                <input v-if="row.email !== null" v-model="row.email" type="checkbox" class="size-5 accent-teal-600" :aria-label="t(':what by email', { what: row.label })" @change="save" />
                                <span v-else class="text-slate-400" :title="t('No email for this')">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500">{{ t('Phone notifications arrive only on the devices where you turned them on.') }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);

function initialTheme() {
    if (user.value && !user.value.isDemo && user.value.darkMode !== null) {
        return user.value.darkMode ? 'dark' : 'light';
    }
    try {
        return localStorage.getItem('theme') || 'system';
    } catch {
        return 'system';
    }
}

// Cycles light → dark → follow the device
const theme = ref(initialTheme());
const next = { light: 'dark', dark: 'system', system: 'light' };
const icons = { light: Sun, dark: Moon, system: Monitor };
const labels = { light: 'Light', dark: 'Dark', system: 'Same as device' };

const media = window.matchMedia('(prefers-color-scheme: dark)');
function apply() {
    const dark = theme.value === 'dark' || (theme.value === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
}
onMounted(() => media.addEventListener('change', apply));
onBeforeUnmount(() => media.removeEventListener('change', apply));

function toggle() {
    theme.value = next[theme.value];
    apply();
    try {
        localStorage.setItem('theme', theme.value);
    } catch {}
    // Saved on the account so it follows you on every device (not for the shared demo account)
    if (!user.value.isDemo) {
        router.put('/account/theme', { theme: theme.value }, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <button
        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
        :title="t('Theme: :name', { name: t(labels[theme]) })"
        @click="toggle"
    >
        <component :is="icons[theme]" class="size-5" />
        <span class="sr-only">{{ t('Theme: :name', { name: t(labels[theme]) }) }}</span>
    </button>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Images, LogOut, Map, Menu, MessagesSquare, Monitor, PanelLeftClose, PanelLeftOpen, PlusCircle, User, Wallet, Wrench, X } from 'lucide-vue-next';
import NotificationsBell from '../components/NotificationsBell.vue';
import ThemeToggle from '../components/ThemeToggle.vue';
import FlashMessage from '../components/FlashMessage.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const drawerOpen = ref(false);

// Desktop only: the sidebar can shrink to icons. The choice is kept in this browser
// (same key as the Blade layout, so old and new pages agree)
const collapsed = ref(readCollapsed());
function readCollapsed() {
    try {
        return localStorage.getItem('sidebar-collapsed') === '1';
    } catch {
        return false;
    }
}
function toggleCollapsed() {
    collapsed.value = !collapsed.value;
    try {
        localStorage.setItem('sidebar-collapsed', collapsed.value ? '1' : '0');
    } catch {}
}

// Every page is a Vue page: links navigate instantly (the "inertia" flag stays for any future
// link that needs a normal page load)
const navigation = computed(() => {
    const items = [
        { label: 'Albums', href: '/', icon: Images, inertia: true },
        { label: 'Map', href: '/map', icon: Map, inertia: true },
    ];
    if (!user.value?.isDemo) {
        items.push({ label: 'Forum', href: '/forum', icon: MessagesSquare, inertia: true });
        items.push({ label: 'Expenses', href: '/expenses', icon: Wallet, inertia: true });
    }
    items.push({ label: 'Account', href: '/account', icon: User, inertia: true });
    return items;
});

const adminNavigation = [
    { label: 'Post album', href: '/listings/create', icon: PlusCircle, inertia: true },
    { label: 'Monitor', href: '/monitor', icon: Monitor, inertia: true },
    { label: 'Maintenance', href: '/admin/maintenance', icon: Wrench, inertia: true },
];

function isActive(href) {
    const url = page.url.split('?')[0];
    return href === '/' ? url === '/' : url.startsWith(href);
}

function logout() {
    router.post('/logout');
}

// Close the drawer when navigating or pressing Escape
const removeListener = router.on('navigate', () => (drawerOpen.value = false));
const onKeydown = (event) => event.key === 'Escape' && (drawerOpen.value = false);
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    removeListener();
    window.removeEventListener('keydown', onKeydown);
});
watch(drawerOpen, (open) => document.body.classList.toggle('overflow-hidden', open));

const itemClass = 'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition-colors';
const idleClass = 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white';
</script>

<template>
    <div class="min-h-screen">
        <!-- Drawer backdrop (phones) -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="drawerOpen = false" />
        </Transition>

        <!-- Sidebar: always visible on large screens (full or icons only), a drawer on phones -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-[width,translate] duration-200 dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0"
            :class="[drawerOpen ? 'translate-x-0' : '-translate-x-full', collapsed && 'lg:w-20']"
        >
            <div class="flex h-16 items-center justify-between px-5" :class="collapsed && 'lg:justify-center lg:px-0'">
                <Link href="/" class="flex items-center gap-2">
                    <img src="/images/logo.png" alt="" class="h-9 w-auto" />
                    <span class="text-lg font-extrabold uppercase tracking-tight" :class="collapsed && 'lg:hidden'">
                        The<span class="text-brand">Boise</span>
                    </span>
                </Link>
                <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden" @click="drawerOpen = false">
                    <X class="size-5" />
                    <span class="sr-only">Close menu</span>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" :class="collapsed && 'lg:overflow-visible'">
                <component
                    :is="item.inertia ? Link : 'a'"
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    :class="[itemClass, isActive(item.href) ? 'bg-brand/10 text-brand' : idleClass, collapsed && 'lg:justify-center']"
                >
                    <component :is="item.icon" class="size-5 shrink-0" />
                    <span :class="collapsed && 'lg:sr-only'">{{ item.label }}</span>
                    <span v-if="collapsed" class="sidebar-tooltip">{{ item.label }}</span>
                </component>

                <template v-if="user?.isAdmin">
                    <p class="px-3 pt-6 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400" :class="collapsed && 'lg:hidden'">Admin</p>
                    <hr v-if="collapsed" class="mx-2 my-4 hidden border-slate-200 dark:border-slate-800 lg:block" />
                    <component
                        :is="item.inertia ? Link : 'a'"
                        v-for="item in adminNavigation"
                        :key="item.href"
                        :href="item.href"
                        :class="[itemClass, isActive(item.href) ? 'bg-brand/10 text-brand' : idleClass, collapsed && 'lg:justify-center']"
                    >
                        <component :is="item.icon" class="size-5 shrink-0" />
                        <span :class="collapsed && 'lg:sr-only'">{{ item.label }}</span>
                        <span v-if="collapsed" class="sidebar-tooltip">{{ item.label }}</span>
                    </component>
                </template>
            </nav>

            <div class="space-y-1 border-t border-slate-200 p-3 dark:border-slate-800">
                <button
                    class="w-full max-lg:hidden"
                    :class="[itemClass, idleClass, collapsed && 'lg:justify-center']"
                    :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleCollapsed"
                >
                    <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="size-5 shrink-0" />
                    <span :class="collapsed && 'lg:sr-only'">Collapse</span>
                    <span v-if="collapsed" class="sidebar-tooltip">Expand</span>
                </button>
                <button
                    class="w-full hover:text-brand"
                    :class="[itemClass, idleClass, collapsed && 'lg:justify-center']"
                    @click="logout"
                >
                    <LogOut class="size-5 shrink-0" />
                    <span :class="collapsed && 'lg:sr-only'">Logout</span>
                    <span v-if="collapsed" class="sidebar-tooltip">Logout</span>
                </button>
            </div>
        </aside>

        <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-20' : 'lg:pl-64'">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80 sm:px-6">
                <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden" @click="drawerOpen = true">
                    <Menu class="size-5" />
                    <span class="sr-only">Open menu</span>
                </button>
                <Link href="/" class="lg:hidden">
                    <img src="/images/logo.png" alt="TheBoise" class="h-8 w-auto" />
                </Link>

                <div class="ml-auto flex items-center gap-1">
                    <NotificationsBell />
                    <ThemeToggle />
                    <Link href="/account" class="ml-2 flex items-center gap-2 rounded-full py-1 pr-1 pl-3 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <span class="hidden text-sm font-semibold text-accent sm:inline">Hi {{ user.name }}</span>
                        <img v-if="user.avatar" :src="user.avatar" alt="" class="size-8 rounded-full object-cover" />
                        <span v-else class="flex size-8 items-center justify-center rounded-full bg-accent text-sm font-bold text-white">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                    </Link>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>

        <FlashMessage />
    </div>
</template>

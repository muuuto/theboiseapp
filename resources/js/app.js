import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './layouts/AppLayout.vue';
import { registerServiceWorker } from './push.js';
import { locale, t, tc } from './i18n.js';
import { router } from '@inertiajs/vue3';

// The app on the phone: offline page and notifications (https only, or localhost)
registerServiceWorker();

createInertiaApp({
    title: (title) => (title ? `${title} | TheBoise` : 'TheBoise'),
    // Each page is its own file, downloaded when first opened: the editor, the maps and the emoji
    // picker are only loaded by the pages that use them
    resolve: async (name) => {
        const pages = import.meta.glob('./pages/**/*.vue');
        const page = await pages[`./pages/${name}.vue`]();
        // Every page uses the app layout unless it sets its own (null = the page picks it itself)
        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        // The language follows every page (it changes when someone switches it)
        const setLocale = (value) => {
            locale.value = value || 'en';
            document.documentElement.lang = locale.value;
        };
        setLocale(props.initialPage.props.locale);
        router.on('navigate', (event) => setLocale(event.detail.page.props.locale));
        // Switching language reloads the same page: "navigate" may not fire, "success" does
        router.on('success', (event) => setLocale(event.detail.page.props.locale));

        const app = createApp({ render: () => h(App, props) });
        app.config.globalProperties.t = t;
        app.config.globalProperties.tc = tc;
        app.use(plugin).mount(el);
    },
    progress: { color: '#ef3b2d' },
});

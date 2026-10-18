import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './layouts/AppLayout.vue';

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
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#ef3b2d' },
});

import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Sortable from 'sortablejs';

// "Reorder" mode of a list (admins): drag the cards by their handle, with the mouse or a finger,
// or move them with the arrows. Nothing changes until "Save order".
// container: put ref="container" on the element whose children are the cards
export function useReorder(source) {
    const active = ref(false);
    const items = ref([]);
    const container = ref(null);
    let sortable = null;

    function move(from, to) {
        if (to < 0 || to >= items.value.length) return;
        const [item] = items.value.splice(from, 1);
        items.value.splice(to, 0, item);
    }

    watch(active, async (on) => {
        sortable?.destroy();
        sortable = null;
        if (!on) return;
        await nextTick();
        sortable = Sortable.create(container.value, {
            handle: '[data-drag-handle]', // the rest of the card still scrolls the page on phones
            animation: 150,
            ghostClass: 'opacity-40',
            onEnd({ item, from, oldIndex, newIndex }) {
                if (oldIndex === newIndex) return;
                // Sortable moved the card itself: put it back and let Vue move it, or Vue gets lost
                from.removeChild(item);
                from.insertBefore(item, from.children[oldIndex] ?? null);
                move(oldIndex, newIndex);
            },
        });
    });
    onBeforeUnmount(() => sortable?.destroy());

    return {
        active,
        items,
        container,
        move,
        start: () => {
            items.value = [...source()];
            active.value = true;
        },
        stop: () => (active.value = false),
        ids: () => items.value.map((item) => item.id),
    };
}

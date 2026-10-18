<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Loader2 } from 'lucide-vue-next';
import TagsInput from '../../components/TagsInput.vue';
import PeoplePicker from '../../components/PeoplePicker.vue';

const props = defineProps({
    category: Object, // null when creating
    users: Array,
});

const editing = computed(() => !!props.category);

const form = useForm({
    title: props.category?.title ?? '',
    description: props.category?.description ?? '',
    tags: props.category?.tags ?? [],
    logo: null,
    hideFrom: props.category?.hideFrom ?? [],
});

const logoPreview = ref(props.category?.logo ?? null);
function onLogo(event) {
    const file = event.target.files[0];
    form.logo = file ?? null;
    if (file) logoPreview.value = URL.createObjectURL(file);
}

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    if (editing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/forum/${props.category.id}`, options);
    } else {
        form.post('/forum/category/store', options);
    }
}

const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
</script>

<template>
    <Head :title="editing ? `Edit ${category.title}` : 'New category'" />

    <Link
        :href="editing ? `/forum/${category.id}` : '/forum'"
        class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800"
    >
        <ArrowLeft class="size-4" /> {{ editing ? category.title : 'Forum' }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? 'Edit category' : 'New category' }}</h1>

    <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
        <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex items-start gap-4">
                <label class="block size-24 shrink-0 cursor-pointer overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-800 dark:ring-slate-700">
                    <img v-if="logoPreview" :src="logoPreview" alt="" class="size-full object-cover" />
                    <span v-else class="flex size-full flex-col items-center justify-center gap-1 text-xs text-slate-500"><ImagePlus class="size-6" /> Logo</span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onLogo" />
                </label>
                <div class="min-w-0 flex-1">
                    <label for="title" :class="labelClass">Title</label>
                    <input id="title" v-model="form.title" type="text" :class="inputClass" />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                    <p v-if="form.errors.logo" class="mt-1 text-sm text-red-600">{{ form.errors.logo }}</p>
                </div>
            </div>
            <div>
                <label for="description" :class="labelClass">Description</label>
                <textarea id="description" v-model="form.description" rows="3" class="w-full rounded-xl border-0 bg-white px-3 py-2 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700" />
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
            </div>
            <div>
                <span :class="labelClass">Tags</span>
                <TagsInput v-model="form.tags" />
                <p v-if="form.errors.tags" class="mt-1 text-sm text-red-600">{{ form.errors.tags }}</p>
            </div>
        </section>

        <section class="space-y-2">
            <span :class="labelClass">Hidden from <span class="font-normal text-slate-500">(they won't see the category or its posts)</span></span>
            <PeoplePicker v-model="form.hideFrom" :users="users" />
        </section>

        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-forum px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-50"
            :disabled="form.processing"
        >
            <Loader2 v-if="form.processing" class="size-5 animate-spin" />
            {{ editing ? 'Save changes' : 'Create category' }}
        </button>
    </form>
</template>

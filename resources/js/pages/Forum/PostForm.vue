<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, ImagePlus, Loader2, Paperclip, X } from 'lucide-vue-next';
import RichTextEditor from '../../components/RichTextEditor.vue';
import RequiredMark from '../../components/RequiredMark.vue';
import RequiredNote from '../../components/RequiredNote.vue';
import TagsInput from '../../components/TagsInput.vue';
import PeoplePicker from '../../components/PeoplePicker.vue';

const props = defineProps({
    category: Object,
    post: Object, // null when creating
    canManage: Boolean,
    users: Array,
});

const editing = computed(() => !!props.post);

const form = useForm({
    title: props.post?.title ?? '',
    description: props.post?.description ?? '',
    tags: props.post?.tags ?? [],
    content: props.post?.content ?? '',
    cover: null,
    attachments: [],
    keepAttachments: props.post?.attachments.map((attachment) => attachment.path) ?? [],
    is_private: props.post?.isPrivate ?? false,
    canEdit: props.post?.canEdit ?? false,
    hideFrom: props.post?.hideFrom ?? [],
});

const coverPreview = ref(props.post?.cover ?? null);
function onCover(event) {
    const file = event.target.files[0];
    form.cover = file ?? null;
    if (file) coverPreview.value = URL.createObjectURL(file);
}

// Attachments already saved (removable one by one) and new ones to upload
const existing = computed(() => (props.post?.attachments ?? []).filter((attachment) => form.keepAttachments.includes(attachment.path)));
function removeExisting(path) {
    form.keepAttachments = form.keepAttachments.filter((kept) => kept !== path);
}
function onAttachments(event) {
    form.attachments = [...form.attachments, ...event.target.files];
    event.target.value = '';
}
function removeNew(index) {
    form.attachments = form.attachments.filter((_, position) => position !== index);
}
const isPdf = (file) => file.type === 'application/pdf';
const preview = (file) => URL.createObjectURL(file);

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    const base = `/forum/${props.category.id}`;
    if (editing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`${base}/${props.post.id}/update`, options);
    } else {
        form.post(`${base}/store`, options);
    }
}

const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
</script>

<template>
    <Head :title="editing ? t('Edit :title', { title: post.title }) : t('New post')" />

    <Link
        :href="editing ? `/forum/${category.id}/${post.id}` : `/forum/${category.id}`"
        class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800"
    >
        <ArrowLeft class="size-4" /> {{ editing ? t('Back to the post') : category.title }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? t('Edit post') : t('New post in :category', { category: category.title }) }}</h1>

    <form class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" @submit.prevent="submit">
        <div class="min-w-0 space-y-6">
            <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <label for="title" :class="labelClass">{{ t('Title') }}<RequiredMark /></label>
                    <input id="title" v-model="form.title" type="text" :class="inputClass" />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label for="description" :class="labelClass">{{ t('Short description') }}<RequiredMark /></label>
                    <textarea id="description" v-model="form.description" rows="2" class="w-full rounded-xl border-0 bg-white px-3 py-2 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700" />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                </div>
                <div>
                    <span :class="labelClass">{{ t('Tags') }}<RequiredMark /></span>
                    <TagsInput v-model="form.tags" />
                    <p v-if="form.errors.tags" class="mt-1 text-sm text-red-600">{{ form.errors.tags }}</p>
                </div>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">{{ t('Content') }}<RequiredMark /></span>
                <RichTextEditor v-model="form.content" />
                <p v-if="form.errors.content" class="text-sm text-red-600">{{ form.errors.content }}</p>
            </section>

            <section class="space-y-3">
                <span :class="labelClass">{{ t('Attachments (photos and PDFs)') }}</span>
                <ul v-if="existing.length || form.attachments.length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <li v-for="attachment in existing" :key="attachment.path" class="relative overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                        <span v-if="attachment.isPdf" class="flex aspect-square flex-col items-center justify-center gap-1 text-sm text-slate-500"><FileText class="size-8 text-red-600" /> PDF</span>
                        <img v-else :src="attachment.url" alt="" class="aspect-square w-full object-cover" />
                        <button type="button" class="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-red-600" @click="removeExisting(attachment.path)">
                            <X class="size-3.5" /><span class="sr-only">{{ t('Remove') }}</span>
                        </button>
                    </li>
                    <li v-for="(file, index) in form.attachments" :key="`new-${index}`" class="relative overflow-hidden rounded-xl bg-white ring-2 ring-accent dark:bg-slate-900">
                        <span v-if="isPdf(file)" class="flex aspect-square flex-col items-center justify-center gap-1 px-2 text-center text-xs break-all text-slate-500"><FileText class="size-8 text-red-600" /> {{ file.name }}</span>
                        <img v-else :src="preview(file)" alt="" class="aspect-square w-full object-cover" />
                        <button type="button" class="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-red-600" @click="removeNew(index)">
                            <X class="size-3.5" /><span class="sr-only">{{ t('Remove') }}</span>
                        </button>
                    </li>
                </ul>
                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900">
                    <Paperclip class="size-4" /> {{ t('Add photos or PDFs') }}
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" class="sr-only" @change="onAttachments" />
                </label>
                <p v-for="(message, key) in form.errors" v-show="key.startsWith('attachments')" :key="key" class="text-sm text-red-600">{{ message }}</p>
            </section>
        </div>

        <div class="space-y-6">
            <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <span :class="labelClass">{{ t('Cover photo') }} <span class="font-normal text-slate-500">{{ t('(optional)') }}</span></span>
                <label class="block cursor-pointer overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-800 dark:ring-slate-700">
                    <img v-if="coverPreview" :src="coverPreview" alt="" class="max-h-60 w-full object-contain" />
                    <span v-else class="flex h-32 flex-col items-center justify-center gap-2 text-sm text-slate-500"><ImagePlus class="size-7" /> {{ t('Choose a photo') }}</span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onCover" />
                </label>
                <p v-if="form.errors.cover" class="text-sm text-red-600">{{ form.errors.cover }}</p>
            </section>

            <!-- Only the author and admins decide who sees and edits the post -->
            <section v-if="canManage" class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <span :class="labelClass">{{ t('Who can see and edit it') }}</span>
                <label class="flex items-start gap-3">
                    <input v-model="form.is_private" type="checkbox" class="mt-1 size-4 accent-brand" />
                    <span class="text-sm"><strong>{{ t('Private') }}</strong><br /><span class="text-slate-500">{{ t('Only you can see it') }}</span></span>
                </label>
                <label class="flex items-start gap-3">
                    <input v-model="form.canEdit" type="checkbox" class="mt-1 size-4 accent-brand" />
                    <span class="text-sm"><strong>{{ t('Others can edit it') }}</strong><br /><span class="text-slate-500">{{ t('Anyone who can see it can change the content') }}</span></span>
                </label>
                <div v-if="!form.is_private">
                    <span class="mb-1.5 block text-sm"><strong>{{ t('Hidden from') }}</strong> <span class="text-slate-500">{{ t('(optional)') }}</span></span>
                    <PeoplePicker v-model="form.hideFrom" :users="users" />
                </div>
            </section>

            <RequiredNote class="text-center" />
            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-forum px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-green-700 disabled:opacity-50"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                {{ editing ? t('Save changes') : t('Publish') }}
            </button>
            <p v-if="Object.keys(form.errors).length" class="text-center text-sm text-red-600">{{ t('Some fields need a look: see the messages above.') }}</p>
        </div>
    </form>
</template>

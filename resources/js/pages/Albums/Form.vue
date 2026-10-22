<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Loader2, Plus, Search, Trash2, UserCog, X } from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';
import RichTextEditor from '../../components/RichTextEditor.vue';
import TagsInput from '../../components/TagsInput.vue';
import PeoplePicker from '../../components/PeoplePicker.vue';
import { t } from '../../i18n.js';

const props = defineProps({
    album: Object, // null when creating
    users: Array,
    knownTags: Array,
    // Admins only: different link/description for someone (the others never see this section)
    canPersonalize: Boolean,
    // Not admins: the poster is always in their own album
    alwaysIn: { type: Number, default: null },
    // Made from a planned trip: title, dates, place and people already filled in
    fromTrip: { type: Object, default: null },
});

const editing = computed(() => !!props.album);

const form = useForm({
    title: props.album?.title ?? props.fromTrip?.title ?? '',
    dateFrom: props.album?.dateFrom ?? props.fromTrip?.dateFrom ?? '',
    dateTo: props.album?.dateTo ?? props.fromTrip?.dateTo ?? '',
    location: props.album?.location ?? props.fromTrip?.location ?? '',
    // Pins on the map: none, one or more (e.g. "Londra e Dublino")
    places: props.album?.places.map((place) => ({ ...place })) ?? [],
    albumLink: props.album?.albumLink ?? '',
    tags: props.album?.tags.filter((tag) => tag !== 'Youtube') ?? [],
    description: props.album?.description ?? '',
    videoLinks: props.album?.videoLinks.length ? [...props.album.videoLinks] : [],
    people: props.album?.people ?? props.fromTrip?.people ?? (props.alwaysIn ? [props.alwaysIn] : []),
    // Own photos link and/or description for some of the people (empty = the general one)
    personal: props.album?.personal.map((version) => ({ ...version })) ?? [],
    cover: null,
    trip_id: props.fromTrip?.id ?? null,
});

// Place search on OpenStreetMap (on button press: their rules forbid search-as-you-type)
const placeQuery = ref('');
const results = ref([]);
const searching = ref(false);
const searchError = ref('');
async function searchPlace() {
    const query = (placeQuery.value || form.location).trim();
    if (!query) return;
    searching.value = true;
    searchError.value = '';
    try {
        const params = new URLSearchParams({ q: query, format: 'jsonv2', limit: '5', 'accept-language': 'it,en' });
        const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`);
        results.value = await response.json();
        if (!results.value.length) searchError.value = t('Nothing found: try a nearby town, or click on the map.');
    } catch {
        searchError.value = t('Search not available right now: click on the map instead.');
    } finally {
        searching.value = false;
    }
}
function addPlace(result) {
    form.places = [...form.places, {
        latitude: +(+result.lat).toFixed(7),
        longitude: +(+result.lon).toFixed(7),
        placeName: result.display_name.slice(0, 255),
    }];
    results.value = [];
    placeQuery.value = '';
}
function removePlace(index) {
    form.places = form.places.filter((_, position) => position !== index);
}

// Personal versions: only for people in the album, one each
const peopleInAlbum = computed(() => props.users.filter((user) => form.people.includes(user.id)));
const available = (current) => peopleInAlbum.value.filter((user) => user.id === current || !form.personal.some((version) => version.userId === user.id));
const canAddVersion = computed(() => form.personal.length < peopleInAlbum.value.length);
function addVersion() {
    const first = available(null)[0];
    if (first) form.personal.push({ userId: first.id, albumLink: '', description: '' });
}
// Someone removed from the album loses their version
watch(() => form.people, (people) => {
    form.personal = form.personal.filter((version) => people.includes(version.userId));
});

// Cover preview before uploading
const coverPreview = ref(props.album?.cover ?? null);
function onCover(event) {
    const file = event.target.files[0];
    form.cover = file ?? null;
    if (file) coverPreview.value = URL.createObjectURL(file);
}

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    if (editing.value) {
        // Files can't travel in a PUT: POST with _method
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/listings/${props.album.id}`, options);
    } else {
        form.post('/listings', options);
    }
}

const inputClass = 'h-11 w-full rounded-xl border-0 bg-white px-3 ring-1 ring-slate-200 focus:ring-2 focus:ring-accent focus:outline-none dark:bg-slate-900 dark:ring-slate-700';
const labelClass = 'mb-1.5 block text-sm font-semibold';
</script>

<template>
    <Head :title="editing ? t('Edit :title', { title: album.title }) : t('New album')" />

    <Link :href="editing ? `/listings/${album.id}` : '/'" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> {{ editing ? t('Back to the album') : t('Albums') }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? t('Edit album') : t('New album') }}</h1>
    <p v-if="fromTrip" class="-mt-4 mb-6 rounded-2xl bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:bg-teal-950/40 dark:text-teal-300">
        ✈️ {{ t('From the trip :title: title, dates, place and people are filled in. Add the photos link, a preview photo and a description.', { title: fromTrip.title }) }}
    </p>

    <form class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]" @submit.prevent="submit">
        <div class="min-w-0 space-y-6">
            <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <label for="title" :class="labelClass">{{ t('Title') }}</label>
                    <input id="title" v-model="form.title" type="text" :placeholder="t('Example: Vacanzeyney in Toscania')" :class="inputClass" />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="dateFrom" :class="labelClass">{{ t('From') }}</label>
                        <input id="dateFrom" v-model="form.dateFrom" type="date" :class="inputClass" />
                        <p v-if="form.errors.dateFrom" class="mt-1 text-sm text-red-600">{{ form.errors.dateFrom }}</p>
                    </div>
                    <div>
                        <label for="dateTo" :class="labelClass">{{ t('To') }}</label>
                        <input id="dateTo" v-model="form.dateTo" type="date" :min="form.dateFrom" :class="inputClass" />
                        <p v-if="form.errors.dateTo" class="mt-1 text-sm text-red-600">{{ form.errors.dateTo }}</p>
                    </div>
                </div>
                <div>
                    <label for="albumLink" :class="labelClass">{{ t('Photos link (Amazon Photos)') }}</label>
                    <input id="albumLink" v-model="form.albumLink" type="url" placeholder="https://www.amazon.com/photos/shared/..." :class="inputClass" />
                    <p v-if="form.errors.albumLink" class="mt-1 text-sm text-red-600">{{ form.errors.albumLink }}</p>
                </div>
                <div>
                    <span :class="labelClass">{{ t('Tags') }}</span>
                    <TagsInput v-model="form.tags" :suggestions="knownTags" />
                    <p v-if="form.errors.tags" class="mt-1 text-sm text-red-600">{{ form.errors.tags }}</p>
                </div>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">{{ t('Description') }}</span>
                <RichTextEditor v-model="form.description" />
                <p v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</p>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">{{ t('People in the album') }}</span>
                <p v-if="alwaysIn" class="text-xs text-slate-500">{{ t("You're always in the albums you post.") }}</p>
                <PeoplePicker v-model="form.people" :users="users" />
                <p v-if="form.errors.people" class="text-sm text-red-600">{{ form.errors.people }}</p>
            </section>

            <section v-if="canPersonalize" class="space-y-3">
                <div>
                    <span :class="labelClass">{{ t('Different version for someone') }} <span class="font-normal text-slate-500">{{ t('(optional)') }}</span></span>
                    <p class="text-xs text-slate-500">
                        {{ t('These people see their own photos link and/or description instead of the general ones. Leave a field empty to keep the general one.') }}
                    </p>
                </div>

                <div
                    v-for="(version, index) in form.personal"
                    :key="index"
                    class="space-y-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800"
                >
                    <div class="flex items-center gap-2">
                        <UserCog class="size-5 shrink-0 text-accent" />
                        <select v-model="version.userId" class="h-10 flex-1 rounded-xl border-0 bg-white px-3 text-sm font-semibold ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700">
                            <option v-for="user in available(version.userId)" :key="user.id" :value="user.id">{{ user.name }}{{ user.isDemo ? ' ' + t('(demo, for recruiters)') : '' }}</option>
                        </select>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" @click="form.personal.splice(index, 1)">
                            <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove this version') }}</span>
                        </button>
                    </div>
                    <div>
                        <span class="mb-1 block text-xs font-semibold">{{ t('Their photos link') }}</span>
                        <input v-model="version.albumLink" type="url" :placeholder="form.albumLink || 'https://...'" :class="inputClass" />
                        <p v-if="form.errors[`personal.${index}.albumLink`]" class="mt-1 text-sm text-red-600">{{ form.errors[`personal.${index}.albumLink`] }}</p>
                    </div>
                    <div>
                        <span class="mb-1 block text-xs font-semibold">{{ t('Their description') }}</span>
                        <RichTextEditor v-model="version.description" />
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-white disabled:opacity-40 dark:ring-slate-700 dark:hover:bg-slate-900"
                    :disabled="!canAddVersion"
                    @click="addVersion"
                >
                    <Plus class="size-4" /> {{ t('Add a version for someone') }}
                </button>
                <p v-if="!form.people.length" class="text-xs text-slate-500">{{ t('Choose the people in the album first.') }}</p>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">{{ t('Videos (YouTube links are shown as players)') }}</span>
                <div v-for="(link, index) in form.videoLinks" :key="index" class="flex gap-2">
                    <input v-model="form.videoLinks[index]" type="url" placeholder="https://youtu.be/..." :class="inputClass" />
                    <button type="button" class="rounded-xl px-3 text-slate-500 ring-1 ring-slate-200 hover:text-red-600 dark:ring-slate-700" @click="form.videoLinks.splice(index, 1)">
                        <Trash2 class="size-4" /><span class="sr-only">{{ t('Remove video') }}</span>
                    </button>
                </div>
                <p v-for="(message, key) in form.errors" v-show="key.startsWith('videoLinks')" :key="key" class="text-sm text-red-600">{{ message }}</p>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="form.videoLinks.push('')">
                    <Plus class="size-4" /> {{ t('Add a video') }}
                </button>
            </section>
        </div>

        <!-- Right column: cover and location -->
        <div class="space-y-6">
            <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <span :class="labelClass">{{ t('Cover photo') }}</span>
                <label class="block cursor-pointer overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-800 dark:ring-slate-700">
                    <img v-if="coverPreview" :src="coverPreview" alt="" class="max-h-72 w-full object-contain" />
                    <span v-else class="flex h-40 flex-col items-center justify-center gap-2 text-sm text-slate-500">
                        <ImagePlus class="size-8" /> {{ t('Choose a photo') }}
                    </span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onCover" />
                </label>
                <p class="text-xs text-slate-500">{{ t('Any photo from the phone is fine: the site resizes it and keeps its shape.') }}</p>
                <p v-if="form.errors.cover" class="text-sm text-red-600">{{ form.errors.cover }}</p>
            </section>

            <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <label for="location" :class="labelClass">{{ t('Location label') }}</label>
                    <input id="location" v-model="form.location" type="text" :placeholder="t('Example: Terrazzina theboise, Pineta BZ')" :class="inputClass" />
                    <p class="mt-1 text-xs text-slate-500">{{ t('Shown on the album, write it however you like.') }}</p>
                    <p v-if="form.errors.location" class="mt-1 text-sm text-red-600">{{ form.errors.location }}</p>
                </div>

                <div>
                    <span :class="labelClass">{{ t('Places on the map') }} <span class="font-normal text-slate-500">{{ t('(optional, one or more)') }}</span></span>
                    <div class="flex gap-2">
                        <input
                            v-model="placeQuery"
                            type="search"
                            :placeholder="form.location || t('Search a place')"
                            :class="inputClass"
                            @keydown.enter.prevent="searchPlace"
                        />
                        <button type="button" class="rounded-xl bg-accent px-3 text-white hover:bg-teal-700" :disabled="searching" @click="searchPlace">
                            <Loader2 v-if="searching" class="size-4 animate-spin" />
                            <Search v-else class="size-4" />
                            <span class="sr-only">{{ t('Search') }}</span>
                        </button>
                    </div>
                    <p v-if="searchError" class="mt-1 text-sm text-slate-500">{{ searchError }}</p>
                    <ul v-if="results.length" class="mt-2 overflow-hidden rounded-xl ring-1 ring-slate-200 dark:ring-slate-700">
                        <li v-for="result in results" :key="result.place_id">
                            <button type="button" class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" @click="addPlace(result)">
                                <Plus class="mt-0.5 size-4 shrink-0 text-accent" /> {{ result.display_name }}
                            </button>
                        </li>
                    </ul>
                </div>

                <LeafletMap v-model="form.places" picker class="h-64" />
                <p class="text-xs text-slate-500">{{ t('Click on the map to add a pin, drag a pin to move it.') }}</p>

                <ol v-if="form.places.length" class="space-y-2">
                    <li v-for="(place, index) in form.places" :key="index" class="flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">{{ index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p v-if="place.placeName" class="truncate">{{ place.placeName }}</p>
                            <p class="text-xs text-slate-500">{{ place.latitude }}, {{ place.longitude }}</p>
                            <p v-if="place.suggested" class="mt-1 text-xs font-medium text-amber-600">
                                {{ t('Guessed from the location text: saving the album confirms it.') }}
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" @click="removePlace(index)">
                            <X class="size-4" /><span class="sr-only">{{ t('Remove this place') }}</span>
                        </button>
                    </li>
                </ol>
                <p v-for="(message, key) in form.errors" v-show="key.startsWith('places')" :key="key" class="text-sm text-red-600">{{ message }}</p>
            </section>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                {{ editing ? t('Save changes') : t('Create album') }}
            </button>
            <p v-if="Object.keys(form.errors).length" class="text-center text-sm text-red-600">{{ t('Some fields need a look: see the messages above.') }}</p>
        </div>
    </form>
</template>

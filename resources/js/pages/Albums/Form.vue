<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Loader2, MapPin, Plus, Search, Trash2, X } from 'lucide-vue-next';
import LeafletMap from '../../components/LeafletMap.vue';
import RichTextEditor from '../../components/RichTextEditor.vue';
import TagsInput from '../../components/TagsInput.vue';
import PeoplePicker from '../../components/PeoplePicker.vue';

const props = defineProps({
    album: Object, // null when creating
    users: Array,
    knownTags: Array,
});

const editing = computed(() => !!props.album);

const form = useForm({
    title: props.album?.title ?? '',
    dateFrom: props.album?.dateFrom ?? '',
    dateTo: props.album?.dateTo ?? '',
    location: props.album?.location ?? '',
    latitude: props.album?.latitude ?? null,
    longitude: props.album?.longitude ?? null,
    placeName: props.album?.placeName ?? '',
    albumLink: props.album?.albumLink ?? '',
    tags: props.album?.tags.filter((tag) => tag !== 'Youtube') ?? [],
    description: props.album?.description ?? '',
    videoLinks: props.album?.videoLinks.length ? [...props.album.videoLinks] : [],
    people: props.album?.people ?? [],
    cover: null,
});

// The map point, separate from the free-text label
const point = computed({
    get: () => (form.latitude !== null && form.longitude !== null ? { latitude: form.latitude, longitude: form.longitude } : null),
    set: (value) => {
        form.latitude = value?.latitude ?? null;
        form.longitude = value?.longitude ?? null;
        if (value && !placeChosen.value) form.placeName = '';
        placeChosen.value = false;
    },
});
const placeChosen = ref(false);
const suggested = ref(props.album?.coordinatesSuggested ?? false);

// Place search on OpenStreetMap (on button press: their rules forbid search-as-you-type)
const placeQuery = ref('');
const places = ref([]);
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
        places.value = await response.json();
        if (!places.value.length) searchError.value = 'Nothing found: try a nearby town, or click on the map.';
    } catch {
        searchError.value = 'Search not available right now: click on the map instead.';
    } finally {
        searching.value = false;
    }
}
function choosePlace(place) {
    placeChosen.value = true;
    form.placeName = place.display_name.slice(0, 255);
    point.value = { latitude: +(+place.lat).toFixed(7), longitude: +(+place.lon).toFixed(7) };
    places.value = [];
    suggested.value = false;
}
function clearPoint() {
    point.value = null;
    form.placeName = '';
    suggested.value = false;
}

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
    <Head :title="editing ? `Edit ${album.title}` : 'New album'" />

    <Link :href="editing ? `/listings/${album.id}` : '/'" class="mb-4 inline-flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-200/60 dark:text-slate-300 dark:hover:bg-slate-800">
        <ArrowLeft class="size-4" /> {{ editing ? 'Back to the album' : 'Albums' }}
    </Link>

    <h1 class="mb-6 text-3xl font-extrabold tracking-tight">{{ editing ? 'Edit album' : 'New album' }}</h1>

    <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]" @submit.prevent="submit">
        <div class="min-w-0 space-y-6">
            <section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <label for="title" :class="labelClass">Title</label>
                    <input id="title" v-model="form.title" type="text" placeholder="Example: Vacanzeyney in Toscania" :class="inputClass" />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="dateFrom" :class="labelClass">From</label>
                        <input id="dateFrom" v-model="form.dateFrom" type="date" :class="inputClass" />
                        <p v-if="form.errors.dateFrom" class="mt-1 text-sm text-red-600">{{ form.errors.dateFrom }}</p>
                    </div>
                    <div>
                        <label for="dateTo" :class="labelClass">To</label>
                        <input id="dateTo" v-model="form.dateTo" type="date" :min="form.dateFrom" :class="inputClass" />
                        <p v-if="form.errors.dateTo" class="mt-1 text-sm text-red-600">{{ form.errors.dateTo }}</p>
                    </div>
                </div>
                <div>
                    <label for="albumLink" :class="labelClass">Photos link (Amazon Photos)</label>
                    <input id="albumLink" v-model="form.albumLink" type="url" placeholder="https://www.amazon.com/photos/shared/..." :class="inputClass" />
                    <p v-if="form.errors.albumLink" class="mt-1 text-sm text-red-600">{{ form.errors.albumLink }}</p>
                </div>
                <div>
                    <span :class="labelClass">Tags</span>
                    <TagsInput v-model="form.tags" :suggestions="knownTags" />
                    <p v-if="form.errors.tags" class="mt-1 text-sm text-red-600">{{ form.errors.tags }}</p>
                </div>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">Description</span>
                <RichTextEditor v-model="form.description" />
                <p v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</p>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">People in the album</span>
                <PeoplePicker v-model="form.people" :users="users" />
                <p v-if="form.errors.people" class="text-sm text-red-600">{{ form.errors.people }}</p>
            </section>

            <section class="space-y-2">
                <span :class="labelClass">Videos (YouTube links are shown as players)</span>
                <div v-for="(link, index) in form.videoLinks" :key="index" class="flex gap-2">
                    <input v-model="form.videoLinks[index]" type="url" placeholder="https://youtu.be/..." :class="inputClass" />
                    <button type="button" class="rounded-xl px-3 text-slate-500 ring-1 ring-slate-200 hover:text-red-600 dark:ring-slate-700" @click="form.videoLinks.splice(index, 1)">
                        <Trash2 class="size-4" /><span class="sr-only">Remove video</span>
                    </button>
                </div>
                <p v-for="(message, key) in form.errors" v-show="key.startsWith('videoLinks')" :key="key" class="text-sm text-red-600">{{ message }}</p>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-white dark:ring-slate-700 dark:hover:bg-slate-900" @click="form.videoLinks.push('')">
                    <Plus class="size-4" /> Add a video
                </button>
            </section>
        </div>

        <!-- Right column: cover and location -->
        <div class="space-y-6">
            <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <span :class="labelClass">Cover photo</span>
                <label class="block cursor-pointer overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200 transition hover:ring-accent dark:bg-slate-800 dark:ring-slate-700">
                    <img v-if="coverPreview" :src="coverPreview" alt="" class="max-h-72 w-full object-contain" />
                    <span v-else class="flex h-40 flex-col items-center justify-center gap-2 text-sm text-slate-500">
                        <ImagePlus class="size-8" /> Choose a photo
                    </span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onCover" />
                </label>
                <p class="text-xs text-slate-500">Any photo from the phone is fine: the site resizes it and keeps its shape.</p>
                <p v-if="form.errors.cover" class="text-sm text-red-600">{{ form.errors.cover }}</p>
            </section>

            <section class="space-y-3 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div>
                    <label for="location" :class="labelClass">Location label</label>
                    <input id="location" v-model="form.location" type="text" placeholder="Example: Terrazzina theboise, Pineta BZ" :class="inputClass" />
                    <p class="mt-1 text-xs text-slate-500">Shown on the album, write it however you like.</p>
                    <p v-if="form.errors.location" class="mt-1 text-sm text-red-600">{{ form.errors.location }}</p>
                </div>

                <div>
                    <span :class="labelClass">Place on the map <span class="font-normal text-slate-500">(optional)</span></span>
                    <div class="flex gap-2">
                        <input
                            v-model="placeQuery"
                            type="search"
                            :placeholder="form.location || 'Search a place'"
                            :class="inputClass"
                            @keydown.enter.prevent="searchPlace"
                        />
                        <button type="button" class="rounded-xl bg-accent px-3 text-white hover:bg-teal-700" :disabled="searching" @click="searchPlace">
                            <Loader2 v-if="searching" class="size-4 animate-spin" />
                            <Search v-else class="size-4" />
                            <span class="sr-only">Search</span>
                        </button>
                    </div>
                    <p v-if="searchError" class="mt-1 text-sm text-slate-500">{{ searchError }}</p>
                    <ul v-if="places.length" class="mt-2 overflow-hidden rounded-xl ring-1 ring-slate-200 dark:ring-slate-700">
                        <li v-for="place in places" :key="place.place_id">
                            <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" @click="choosePlace(place)">
                                {{ place.display_name }}
                            </button>
                        </li>
                    </ul>
                </div>

                <LeafletMap v-model="point" picker class="h-64" />
                <p class="text-xs text-slate-500">Click on the map to put the pin, drag it to adjust.</p>

                <div v-if="point" class="flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                    <MapPin class="mt-0.5 size-4 shrink-0 text-brand" />
                    <div class="min-w-0 flex-1">
                        <p v-if="form.placeName" class="truncate">{{ form.placeName }}</p>
                        <p class="text-xs text-slate-500">{{ point.latitude }}, {{ point.longitude }}</p>
                        <p v-if="suggested" class="mt-1 text-xs font-medium text-amber-600">
                            Guessed from the location text: saving the album confirms it.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-slate-400 hover:text-red-600" @click="clearPoint">
                        <X class="size-4" /><span class="sr-only">Remove the place</span>
                    </button>
                </div>
                <p v-if="form.errors.latitude || form.errors.longitude" class="text-sm text-red-600">{{ form.errors.latitude || form.errors.longitude }}</p>
            </section>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50"
                :disabled="form.processing"
            >
                <Loader2 v-if="form.processing" class="size-5 animate-spin" />
                {{ editing ? 'Save changes' : 'Create album' }}
            </button>
            <p v-if="Object.keys(form.errors).length" class="text-center text-sm text-red-600">Some fields need a look: see the messages above.</p>
        </div>
    </form>
</template>

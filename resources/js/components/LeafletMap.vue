<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import L from '../leaflet.js';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

// Two modes:
// - markers: show pins ({ id, albumId?, latitude, longitude, title, cover, dateFrom, suggested }),
//   the popup links to the album (albumId, or id when the pin is the album itself)
// - picker: v-model is the list of places ({ latitude, longitude, placeName }): clicking the map
//   adds one, dragging a pin moves it
const props = defineProps({
    markers: { type: Array, default: () => [] },
    picker: { type: Boolean, default: false },
    zoom: { type: Number, default: 13 },
});
const places = defineModel({ type: Array, default: () => [] });

const element = ref(null);
let map;
let layer;
const markersById = new Map();
let pickerMarkers = [];

// Red pin drawn with CSS: no image files to bundle. Suggested places are grey and dashed
function pin(suggested = false, label = '') {
    return L.divIcon({
        className: '',
        html: `<span class="map-pin ${suggested ? 'map-pin-suggested' : ''}"><span>${label}</span></span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        popupAnchor: [0, -26],
    });
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' });

// Popup built from DOM nodes (album titles are text, never HTML)
function popup(marker) {
    const link = document.createElement('a');
    link.href = `/listings/${marker.albumId ?? marker.id}`;
    link.className = 'map-popup';
    link.addEventListener('click', (event) => {
        event.preventDefault();
        router.visit(link.href);
    });

    const image = document.createElement('img');
    image.src = marker.cover;
    image.alt = '';
    const title = document.createElement('strong');
    title.textContent = marker.title;
    const date = document.createElement('span');
    date.textContent = dateFormatter.format(new Date(marker.dateFrom));
    link.append(image, title, date);

    if (marker.suggested) {
        const note = document.createElement('em');
        note.textContent = 'Approximate location';
        link.append(note);
    }
    return link;
}

function drawMarkers() {
    layer.clearLayers();
    markersById.clear();
    const points = props.markers
        .filter((marker) => marker.latitude !== null && marker.longitude !== null)
        .map((marker) => {
            const leafletMarker = L.marker([marker.latitude, marker.longitude], { icon: pin(marker.suggested), title: marker.title })
                .bindPopup(() => popup(marker), { minWidth: 200, maxWidth: 240 });
            layer.addLayer(leafletMarker);
            markersById.set(marker.id, leafletMarker);
            return [marker.latitude, marker.longitude];
        });
    fit(points);
}

function fit(points) {
    if (points.length === 1) {
        map.setView(points[0], props.zoom);
    } else if (points.length > 1) {
        map.fitBounds(points, { padding: [40, 40], maxZoom: 12 });
    }
}

// Picker: one numbered, draggable pin per place
function drawPicker() {
    pickerMarkers.forEach((marker) => marker.remove());
    pickerMarkers = places.value.map((place, index) => {
        const marker = L.marker([place.latitude, place.longitude], { icon: pin(place.suggested, index + 1), draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const { lat, lng } = marker.getLatLng();
            places.value = places.value.map((existing, position) =>
                position === index ? { latitude: +lat.toFixed(7), longitude: +lng.toFixed(7), placeName: '' } : existing,
            );
        });
        return marker;
    });
}

onMounted(() => {
    // Scroll-wheel zoom only while picking: on normal pages the wheel must scroll the page.
    // Until something is set: South Tyrol, where most albums are
    map = L.map(element.value, { scrollWheelZoom: props.picker }).setView([46.5, 11.35], 8);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    if (props.picker) {
        map.on('click', ({ latlng }) => {
            places.value = [...places.value, { latitude: +latlng.lat.toFixed(7), longitude: +latlng.lng.toFixed(7), placeName: '' }];
        });
        drawPicker();
        fit(places.value.map((place) => [place.latitude, place.longitude]));
    } else {
        // Nearby pins merge into a bubble with their number, zooming in splits them
        layer = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 45,
            iconCreateFunction: (cluster) => L.divIcon({
                className: '',
                html: `<span class="map-cluster">${cluster.getChildCount()}</span>`,
                iconSize: [40, 40],
            }),
        }).addTo(map);
        drawMarkers();
    }
});

watch(() => props.markers, () => layer && drawMarkers(), { deep: true });
watch(places, (value, old) => {
    if (!props.picker) return;
    drawPicker();
    // A place was added from outside (place search): bring it into view
    if (value.length > (old?.length ?? 0)) {
        const added = value[value.length - 1];
        map.setView([added.latitude, added.longitude], Math.max(map.getZoom(), props.zoom));
    }
});

onBeforeUnmount(() => map?.remove());

defineExpose({
    // Zooms in until the pin is out of its cluster, then opens it
    focus: (id) => {
        const marker = markersById.get(id);
        if (marker) layer.zoomToShowLayer(marker, () => marker.openPopup());
    },
});
</script>

<template>
    <div ref="element" class="isolate z-0 overflow-hidden rounded-2xl ring-1 ring-slate-200 dark:ring-slate-800" />
</template>

<style>
.map-pin {
    display: block;
    width: 28px;
    height: 28px;
    border-radius: 50% 50% 50% 0;
    background: #ef3b2d;
    border: 3px solid white;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.35);
    transform: rotate(-45deg);
}
/* Number of the place in the album form */
.map-pin > span {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transform: rotate(45deg);
    color: white;
    font-size: 11px;
    font-weight: 700;
}
.map-cluster {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    background: #ef3b2d;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgb(0 0 0 / 0.35);
    color: white;
    font-weight: 700;
    font-size: 14px;
}
.map-pin-suggested {
    background: #94a3b8;
    border-style: dashed;
}
.map-popup {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: #1e293b !important;
    text-decoration: none;
}
.map-popup img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 6px;
}
.map-popup span,
.map-popup em {
    color: #64748b;
    font-size: 12px;
}
</style>

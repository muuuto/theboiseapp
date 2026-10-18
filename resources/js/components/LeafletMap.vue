<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import L from '../leaflet.js';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

// Two modes:
// - markers: show albums ({ id, latitude, longitude, title, cover, dateFrom, suggested })
// - picker: v-model is { latitude, longitude } and the admin sets it by clicking or dragging the pin
const props = defineProps({
    markers: { type: Array, default: () => [] },
    picker: { type: Boolean, default: false },
    zoom: { type: Number, default: 13 },
});
const point = defineModel({ type: Object, default: null });

const element = ref(null);
let map;
let layer;
let pickerMarker;
const markersById = new Map();

// Red pin drawn with CSS: no image files to bundle. Suggested locations are grey and dashed
function pin(suggested = false) {
    return L.divIcon({
        className: '',
        html: `<span class="map-pin ${suggested ? 'map-pin-suggested' : ''}"></span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
        popupAnchor: [0, -26],
    });
}

const dateFormatter = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' });

// Popup built from DOM nodes (album titles are text, never HTML)
function popup(album) {
    const link = document.createElement('a');
    link.href = `/listings/${album.id}`;
    link.className = 'map-popup';
    link.addEventListener('click', (event) => {
        event.preventDefault();
        router.visit(link.href);
    });

    const image = document.createElement('img');
    image.src = album.cover;
    image.alt = '';
    const title = document.createElement('strong');
    title.textContent = album.title;
    const date = document.createElement('span');
    date.textContent = dateFormatter.format(new Date(album.dateFrom));
    link.append(image, title, date);

    if (album.suggested) {
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
        .filter((album) => album.latitude !== null && album.longitude !== null)
        .map((album) => {
            const marker = L.marker([album.latitude, album.longitude], { icon: pin(album.suggested), title: album.title })
                .bindPopup(() => popup(album), { minWidth: 200, maxWidth: 240 });
            layer.addLayer(marker);
            markersById.set(album.id, marker);
            return [album.latitude, album.longitude];
        });

    if (points.length === 1) {
        map.setView(points[0], props.zoom);
    } else if (points.length > 1) {
        map.fitBounds(points, { padding: [40, 40], maxZoom: 12 });
    }
}

function setPickerPoint(latlng) {
    point.value = { latitude: +latlng.lat.toFixed(7), longitude: +latlng.lng.toFixed(7) };
}

function drawPicker() {
    if (!point.value) {
        pickerMarker?.remove();
        pickerMarker = null;
        return;
    }
    const latlng = [point.value.latitude, point.value.longitude];
    if (!pickerMarker) {
        pickerMarker = L.marker(latlng, { icon: pin(), draggable: true }).addTo(map);
        pickerMarker.on('dragend', () => setPickerPoint(pickerMarker.getLatLng()));
    } else {
        pickerMarker.setLatLng(latlng);
    }
}

onMounted(() => {
    // Until something is set: South Tyrol, where most albums are
    // Scroll-wheel zoom only while picking: on normal pages the wheel must scroll the page
    map = L.map(element.value, { scrollWheelZoom: props.picker }).setView([46.5, 11.35], 8);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    if (props.picker) {
        map.on('click', (event) => setPickerPoint(event.latlng));
        drawPicker();
        if (point.value) map.setView([point.value.latitude, point.value.longitude], props.zoom);
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
watch(point, (value, old) => {
    if (!props.picker) return;
    drawPicker();
    // Moved from outside (place search): bring it into view
    if (value && (!old || Math.abs(value.latitude - old.latitude) > 0.01 || Math.abs(value.longitude - old.longitude) > 0.01)) {
        map.setView([value.latitude, value.longitude], Math.max(map.getZoom(), props.zoom));
    }
});

onBeforeUnmount(() => map?.remove());

defineExpose({
    // Zooms in until the album's pin is out of its cluster, then opens it
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

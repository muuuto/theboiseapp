// Leaflet plugins (marker clustering) expect Leaflet as the global "L": set it before importing them
import L from 'leaflet';

window.L = L;

export default L;

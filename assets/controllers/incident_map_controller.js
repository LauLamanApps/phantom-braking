import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import { enableWheelZoom } from '../lib/map_wheel_zoom.js';

/*
 * Plots reports on a map. Either fetches points from `url` (home page) or
 * takes them inline via `points` (detail page). Collisions use the reserved
 * status colour and are explained in the legend next to the map.
 */
export default class extends Controller {
    static values = {
        url: String,
        points: Array,
        showUrl: String, // contains __ID__
        centre: { type: Array, default: [52.2, 5.3] },
        zoom: { type: Number, default: 7 },
        messages: Object,
    };

    async connect() {
        this.map = L.map(this.element, { scrollWheelZoom: false }).setView(this.centreValue, this.zoomValue);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);
        enableWheelZoom(this.map, this.messagesValue.wheel_hint ?? '');

        let points = this.pointsValue;
        if (this.hasUrlValue && this.urlValue) {
            try {
                points = await (await fetch(this.urlValue, { headers: { Accept: 'application/json' } })).json();
            } catch {
                points = [];
            }
        }
        this.plot(points);
    }

    disconnect() {
        this.map?.remove();
    }

    plot(points) {
        const css = getComputedStyle(document.documentElement);
        const token = (name) => css.getPropertyValue(name).trim();

        const markers = points.map((p) => {
            const marker = L.circleMarker([p.latitude, p.longitude], {
                radius: p.collision ? 8 : 6,
                weight: 2,
                color: token('--surface-1'),
                fillColor: p.collision ? token('--status-critical') : token('--series-1'),
                fillOpacity: 0.9,
            });

            if (this.hasShowUrlValue && this.showUrlValue && p.incidentId) {
                const popup = document.createElement('div');
                const title = document.createElement('strong');
                title.textContent = `${p.make} ${p.model}`;
                const link = document.createElement('a');
                link.href = this.showUrlValue.replace('__ID__', p.incidentId);
                link.textContent = this.messagesValue.open ?? 'Open';
                link.className = 'block mt-1 underline';
                popup.append(title, link);
                marker.bindPopup(popup);
            }

            return marker.addTo(this.map);
        });

        if (markers.length === 1) {
            this.map.setView(markers[0].getLatLng(), 14);
        } else if (markers.length > 1) {
            this.map.fitBounds(L.featureGroup(markers).getBounds().pad(0.1), { maxZoom: 12 });
        }
    }
}

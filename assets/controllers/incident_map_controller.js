import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet.markercluster'; // adds L.markerClusterGroup (needs the global L that Leaflet sets)
import { enableWheelZoom } from '../lib/map_wheel_zoom.js';
import { enableFullscreen } from '../lib/map_fullscreen.js';

/*
 * Plots reports on a map. Either fetches points from `url` (home page) or
 * takes them inline via `points` (detail page). Collisions use the reserved
 * status colour and are explained in the legend next to the map.
 *
 * Nearby points are grouped into a cluster showing how many reports it holds
 * (a red ring when one of them was a collision), so hundreds of points stay
 * fast and readable; zooming in or clicking a cluster splits it up.
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
        enableFullscreen(this.map, { enter: this.messagesValue.fullscreen, exit: this.messagesValue.exit_fullscreen });
        // The container can grow after load (the statistics card stretches to the charts beside it).
        this.resizeObserver = new ResizeObserver(() => this.map.invalidateSize());
        this.resizeObserver.observe(this.element);
        this.repaint = () => this.markers?.forEach((marker) => marker.setStyle(this.markerStyle(marker.options.collision)));
        window.addEventListener('theme:change', this.repaint);

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
        this.resizeObserver?.disconnect();
        window.removeEventListener('theme:change', this.repaint);
        this.map?.remove();
    }

    /* Colours from the CSS tokens, so they follow the light/dark theme. */
    markerStyle(collision) {
        const css = getComputedStyle(document.documentElement);
        const token = (name) => css.getPropertyValue(name).trim();

        return {
            color: token('--surface-1'),
            fillColor: collision ? token('--status-critical') : token('--series-1'),
        };
    }

    plot(points) {
        const markers = points.map((p) => {
            const marker = L.circleMarker([p.latitude, p.longitude], {
                collision: p.collision, // read by clusterIcon()
                radius: p.collision ? 8 : 6,
                weight: 2,
                ...this.markerStyle(p.collision),
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

            return marker;
        });

        const clusters = L.markerClusterGroup({
            chunkedLoading: true, // adds markers in batches, so the page stays responsive
            showCoverageOnHover: false,
            disableClusteringAtZoom: 15, // street level: every report on its own
            iconCreateFunction: (cluster) => this.clusterIcon(cluster),
        });
        clusters.addLayers(markers);
        this.markers = markers;
        this.map.addLayer(clusters);

        if (markers.length === 1) {
            this.map.setView(markers[0].getLatLng(), 14);
        } else if (markers.length > 1) {
            this.map.fitBounds(clusters.getBounds().pad(0.1), { maxZoom: 12 });
        }
    }

    clusterIcon(cluster) {
        const count = cluster.getChildCount();
        const size = count < 10 ? 32 : count < 100 ? 40 : 48;
        const collision = cluster.getAllChildMarkers().some((marker) => marker.options.collision);

        return L.divIcon({
            html: `<span>${count}</span>`,
            className: collision ? 'map-cluster map-cluster-collision' : 'map-cluster',
            iconSize: [size, size],
        });
    }
}

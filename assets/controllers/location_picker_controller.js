import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import { enableWheelZoom } from '../lib/map_wheel_zoom.js';
import { enableFullscreen } from '../lib/map_fullscreen.js';

/*
 * Click the map (or use GPS) to mark where the car braked. The road at that
 * point is looked up (OpenStreetMap, via our server) to suggest country, road
 * number, road type, situation and speed limit. Suggestions only fill fields
 * the reporter hasn't filled in themselves.
 */
export default class extends Controller {
    static targets = ['map', 'latitude', 'longitude', 'status', 'country', 'roadType', 'roadSituation', 'roadNumber', 'speedLimit'];
    static values = { messages: Object, lookupUrl: String };

    connect() {
        const lat = parseFloat(this.latitudeTarget.value);
        const lng = parseFloat(this.longitudeTarget.value);
        const hasPoint = !Number.isNaN(lat) && !Number.isNaN(lng);

        this.map = L.map(this.mapTarget, { scrollWheelZoom: false })
            .setView(hasPoint ? [lat, lng] : [52.2, 5.3], hasPoint ? 14 : 7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);
        enableWheelZoom(this.map, this.messagesValue.wheel_hint ?? '');
        enableFullscreen(this.map, { enter: this.messagesValue.fullscreen, exit: this.messagesValue.exit_fullscreen });

        this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng));
        if (hasPoint) {
            this.place(lat, lng, false);
        }

        // Anything the reporter changes by hand is theirs: never overwrite it with a suggestion.
        this.fieldTargets().forEach((field) => {
            field.addEventListener('input', () => delete field.dataset.autofilled);
            field.addEventListener('change', (event) => {
                if (event.isTrusted) delete field.dataset.autofilled;
            });
        });
    }

    disconnect() {
        this.map?.remove();
        this.abort?.abort();
    }

    locate(event) {
        event.preventDefault();
        if (!navigator.geolocation) {
            this.status(this.messagesValue.unsupported);
            return;
        }
        this.status(this.messagesValue.locating);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.place(pos.coords.latitude, pos.coords.longitude);
                this.map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            },
            () => this.status(this.messagesValue.denied),
            { enableHighAccuracy: true, timeout: 10000 },
        );
    }

    clear(event) {
        event.preventDefault();
        this.abort?.abort();
        this.marker?.remove();
        this.marker = null;
        this.latitudeTarget.value = '';
        this.longitudeTarget.value = '';
        this.status(this.messagesValue.empty);
    }

    place(lat, lng, lookup = true) {
        const style = getComputedStyle(document.documentElement);
        const latlng = [Number(lat.toFixed(5)), Number(lng.toFixed(5))];

        if (!this.marker) {
            this.marker = L.circleMarker(latlng, {
                radius: 9,
                weight: 2,
                color: style.getPropertyValue('--surface-1').trim(),
                fillColor: style.getPropertyValue('--status-critical').trim(),
                fillOpacity: 1,
            }).addTo(this.map);
        } else {
            this.marker.setLatLng(latlng);
        }

        this.latitudeTarget.value = latlng[0];
        this.longitudeTarget.value = latlng[1];
        this.status(`${this.messagesValue.set} ${latlng[0]}, ${latlng[1]}`);

        if (lookup && this.hasLookupUrlValue) {
            this.lookup(latlng[0], latlng[1]);
        }
    }

    async lookup(latitude, longitude) {
        this.abort?.abort();
        this.abort = new AbortController();
        this.status(this.messagesValue.looking_up);

        let road;
        try {
            const response = await fetch(this.lookupUrlValue, {
                method: 'POST',
                signal: this.abort.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ latitude, longitude }),
            });
            if (!response.ok) throw new Error(String(response.status));
            road = await response.json();
        } catch (e) {
            if (e.name !== 'AbortError') this.status(`${this.messagesValue.set} ${latitude}, ${longitude}`);
            return;
        }

        const filled = [
            this.suggest(this.hasCountryTarget && this.countryTarget, road.country),
            this.suggest(this.hasRoadNumberTarget && this.roadNumberTarget, road.roadNumber),
            this.suggest(this.hasRoadTypeTarget && this.roadTypeTarget, road.roadType),
            this.suggest(this.hasRoadSituationTarget && this.roadSituationTarget, road.roadSituation),
            this.suggest(this.hasSpeedLimitTarget && this.speedLimitTarget, road.speedLimit),
        ].filter(Boolean);

        const summary = [road.roadNumber ?? road.name, this.label(this.hasRoadTypeTarget && this.roadTypeTarget, road.roadType), this.label(this.hasCountryTarget && this.countryTarget, road.country)]
            .filter(Boolean).join(' · ');
        this.status(summary ? `${this.messagesValue.found} ${summary}` : this.messagesValue.nothing_found);

        filled.forEach((field) => {
            field.classList.add('ring-2', 'ring-emerald-500/60');
            setTimeout(() => field.classList.remove('ring-2', 'ring-emerald-500/60'), 2000);
        });
    }

    /** Fills a field if it is empty or was filled by a previous suggestion. Returns the field when changed. */
    suggest(field, value) {
        if (!field || value === null || value === undefined || value === '') return null;
        const empty = field.value === '' || field.value === null;
        if (!empty && field.dataset.autofilled !== '1') return null;
        if (field.tagName === 'SELECT' && ![...field.options].some((o) => o.value === String(value))) return null;
        if (field.value === String(value)) return null;

        field.value = String(value);
        field.dataset.autofilled = '1';
        field.dispatchEvent(new Event('change', { bubbles: true }));
        return field;
    }

    label(select, value) {
        if (!select || !value || select.tagName !== 'SELECT') return null;
        return [...select.options].find((o) => o.value === String(value))?.textContent.trim() ?? null;
    }

    fieldTargets() {
        return ['country', 'roadType', 'roadSituation', 'roadNumber', 'speedLimit']
            .filter((name) => this[`has${name[0].toUpperCase()}${name.slice(1)}Target`])
            .map((name) => this[`${name}Target`]);
    }

    status(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}

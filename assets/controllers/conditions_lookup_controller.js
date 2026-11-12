import { Controller } from '@hotwired/stimulus';

/*
 * Once the time and the place are known, looks up the measured weather
 * (Open-Meteo, via our server) and the sun at that moment to suggest the
 * weather and light. Like the road lookup, a suggestion only fills a field
 * the reporter hasn't chosen themselves, and the hint says where it came from.
 */
export default class extends Controller {
    static targets = ['occurredAt', 'latitude', 'longitude', 'country', 'weather', 'light', 'hint'];
    static values = { url: String, messages: Object };

    connect() {
        // Anything the reporter picks by hand is theirs: never overwrite it with a suggestion.
        // (A suggestion sets the value without a change event, so every change event is the reporter's.)
        [this.weatherTarget, this.lightTarget].forEach((field) => {
            field.addEventListener('change', () => delete field.dataset.autofilled);
        });
        this.schedule();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.abort?.abort();
    }

    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.lookup(), 400);
    }

    async lookup() {
        const query = {
            latitude: parseFloat(this.latitudeTarget.value),
            longitude: parseFloat(this.longitudeTarget.value),
            occurredAt: this.occurredAtTarget.value.slice(0, 16),
            country: this.countryTarget.value,
        };
        if (Number.isNaN(query.latitude) || Number.isNaN(query.longitude) || query.occurredAt.length !== 16 || !query.country) return;

        const key = JSON.stringify(query);
        if (key === this.lastKey) return;
        this.lastKey = key;

        this.abort?.abort();
        this.abort = new AbortController();
        let conditions;
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                signal: this.abort.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(query),
            });
            if (!response.ok) throw new Error(String(response.status));
            conditions = await response.json();
        } catch (e) {
            if (e.name !== 'AbortError') this.lastKey = null; // try again on the next change
            return;
        }

        const filled = [this.suggest(this.weatherTarget, conditions.weather), this.suggest(this.lightTarget, conditions.light)].filter(Boolean);
        filled.forEach((field) => {
            field.classList.add('ring-2', 'ring-emerald-500/60');
            setTimeout(() => field.classList.remove('ring-2', 'ring-emerald-500/60'), 2000);
        });
        this.showHint(conditions);
    }

    showHint({ measured, sun }) {
        const m = this.messagesValue;
        const number = (value) => new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 1 }).format(value);
        const parts = [];
        if (measured) {
            parts.push(`${number(measured.temperature)} °C`);
            parts.push(measured.snowfall > 0 ? m.snow.replace('{cm}', number(measured.snowfall))
                : measured.precipitation > 0 ? m.rain.replace('{mm}', number(measured.precipitation)) : m.dry);
            parts.push(m.clouds.replace('{pct}', measured.cloudCover));
            if (measured.visibility !== null && measured.visibility < 5000) parts.push(m.visibility.replace('{km}', number(measured.visibility / 1000)));
        }
        if (sun?.elevation !== null && sun?.elevation !== undefined) {
            parts.push(sun.elevation > 0 ? m.sun.replace('{deg}', Math.round(sun.elevation)) : m.sun_down);
        }
        if (parts.length === 0) {
            this.hintTarget.hidden = true;
            return;
        }

        this.hintTarget.querySelector('[data-text]').textContent = `${measured ? m.measured : m.calculated} ${parts.join(' · ')}`;
        this.hintTarget.hidden = false;
    }

    /** Fills a field if it is empty or was filled by a previous suggestion. Returns the field when changed. */
    suggest(field, value) {
        if (!field || !value) return null;
        if (field.value !== '' && field.dataset.autofilled !== '1') return null;
        if (![...field.options].some((o) => o.value === value) || field.value === value) return null;

        field.value = value;
        field.dataset.autofilled = '1';
        return field;
    }
}

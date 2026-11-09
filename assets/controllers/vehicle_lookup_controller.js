import { Controller } from '@hotwired/stimulus';

/*
 * Looks the plate up in the national registry (via our API) while typing,
 * so the reporter can confirm it's the right car before submitting.
 */
export default class extends Controller {
    static targets = ['input', 'country', 'result'];
    static values = {
        url: String, // POST endpoint; the plate goes in the body, never in the URL
        messages: Object,
    };

    connect() {
        this.timeout = null;
        if (this.inputTarget.value) {
            this.lookup();
        }
    }

    disconnect() {
        clearTimeout(this.timeout);
        this.abort?.abort();
    }

    schedule() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.lookup(), 400);
    }

    async lookup() {
        const plate = this.inputTarget.value.replace(/[^a-z0-9]/gi, '').toUpperCase();
        if (plate.length < 4) {
            this.render('idle');
            return;
        }

        this.abort?.abort();
        this.abort = new AbortController();
        this.render('loading');

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                signal: this.abort.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ country: this.hasCountryTarget ? this.countryTarget.value : 'NL', plate }),
            });
            const body = await response.json();
            response.ok ? this.render('found', body) : this.render(body.error ?? 'error');
        } catch (e) {
            if (e.name !== 'AbortError') {
                this.render('error');
            }
        }
    }

    render(state, vehicle = null) {
        if (state !== 'loading') {
            // Lets other fields react to the make, e.g. make-specific assist systems.
            this.dispatch('changed', { detail: { make: state === 'found' ? vehicle.make : null } });
        }

        const box = this.resultTarget;
        box.dataset.state = state;
        box.replaceChildren();

        if (state === 'idle') {
            box.hidden = true;
            return;
        }
        box.hidden = false;

        if (state !== 'found') {
            box.textContent = this.messagesValue[state] ?? this.messagesValue.error;
            return;
        }

        const title = document.createElement('p');
        title.className = 'font-semibold';
        title.textContent = `${vehicle.make} ${vehicle.model}`;

        const details = document.createElement('p');
        details.className = 'text-sm text-stone-600 dark:text-stone-400';
        details.textContent = [
            vehicle.firstAdmissionYear ? `${this.messagesValue.year} ${vehicle.firstAdmissionYear}` : null,
            vehicle.fuel,
            vehicle.hybridClass,
            vehicle.vehicleKind,
        ].filter(Boolean).join(' · ');

        box.append(title, details);
    }
}

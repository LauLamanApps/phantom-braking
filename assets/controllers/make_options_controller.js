import { Controller } from '@hotwired/stimulus';

/*
 * On a <select>: options with data-make (e.g. Tesla Assisted Driving) only
 * show when the plate lookup found that make. The server checks it too.
 */
export default class extends Controller {
    filter({ detail: { make } }) {
        const current = (make ?? '').trim().toUpperCase();

        this.element.querySelectorAll('option[data-make]').forEach((option) => {
            const available = option.dataset.make === current;
            option.hidden = !available;
            option.disabled = !available;
            if (!available && option.selected) {
                this.element.value = '';
            }
        });
    }
}

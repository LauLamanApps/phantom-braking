import { Controller } from '@hotwired/stimulus';

/*
 * Filter bar: applies on every change and keeps the URL tidy by only
 * sending the criteria that are set.
 */
export default class extends Controller {
    static targets = ['make', 'model', 'period', 'custom'];

    connect() {
        // Fields disabled by clean() may come back from the bfcache.
        this.element.querySelectorAll('[data-filter-cleaned]').forEach((el) => {
            el.disabled = false;
            delete el.dataset.filterCleaned;
        });
        if (this.hasModelTarget && this.hasMakeTarget) {
            this.modelTarget.disabled = this.makeTarget.value === '';
        }
    }

    apply(event) {
        if (this.hasMakeTarget && event.target === this.makeTarget && this.hasModelTarget) {
            this.modelTarget.value = ''; // a model belongs to the previous make
        }
        if (this.hasPeriodTarget && event.target === this.periodTarget) {
            const custom = this.periodTarget.value === 'custom';
            this.customTarget.hidden = !custom;
            if (custom) {
                return; // wait for the dates
            }
        }
        this.element.requestSubmit();
    }

    clean() {
        const custom = this.hasPeriodTarget && this.periodTarget.value === 'custom';
        this.element.querySelectorAll('select, input').forEach((el) => {
            const isDate = el.name === 'from' || el.name === 'to';
            const isDefaultPeriod = el === this.periodTarget && el.value === 'all';
            if (el.value === '' || isDefaultPeriod || (isDate && !custom)) {
                el.disabled = true;
                el.dataset.filterCleaned = '1';
            }
        });
    }
}

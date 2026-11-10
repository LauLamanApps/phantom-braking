import { Controller } from '@hotwired/stimulus';

/*
 * On a <video preload="none">: start loading it (first frame and duration) only
 * once the rest of the page has loaded, so a large video doesn't hold up the
 * scripts, map tiles and photos. Pressing play before then loads it right away.
 */
export default class extends Controller {
    connect() {
        if (document.readyState === 'complete') {
            this.load();
        } else {
            this.onLoad = () => this.load();
            window.addEventListener('load', this.onLoad, { once: true });
        }
    }

    disconnect() {
        if (this.onLoad) {
            window.removeEventListener('load', this.onLoad);
        }
    }

    load() {
        this.element.preload = 'metadata';
    }
}

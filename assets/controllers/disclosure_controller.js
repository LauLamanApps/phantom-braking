import { Controller } from '@hotwired/stimulus';

/* On a <details> used as a dropdown: close it on a click outside or Escape. */
export default class extends Controller {
    closeOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    close() {
        this.element.open = false;
    }
}

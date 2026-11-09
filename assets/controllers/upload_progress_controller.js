import { Controller } from '@hotwired/stimulus';

/*
 * Dashcam files are big. When the form carries files, submit it through XHR
 * so we can show upload progress.
 */
export default class extends Controller {
    static targets = ['progress', 'bar', 'percent', 'submit'];
    static values = { messages: Object };

    submit(event) {
        const hasFiles = [...this.element.querySelectorAll('input[type=file]')].some((input) => input.files.length > 0);
        if (!hasFiles) {
            return; // regular submit, nothing to show progress for
        }
        event.preventDefault();

        const form = this.element;
        const xhr = new XMLHttpRequest();
        xhr.open(form.method || 'POST', form.action || window.location.href);
        xhr.setRequestHeader('Accept', 'text/html');

        this.progressTarget.hidden = false;
        this.submitTargets.forEach((b) => (b.disabled = true));

        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            this.barTarget.style.width = `${pct}%`;
            this.percentTarget.textContent = pct >= 100 ? this.messagesValue.processing : `${pct}%`;
        });

        xhr.addEventListener('load', () => {
            const finalUrl = new URL(xhr.responseURL);
            if (xhr.status < 300 && finalUrl.pathname !== new URL(form.action || window.location.href).pathname) {
                window.location.assign(finalUrl.href); // followed the success redirect
                return;
            }
            // Validation errors: swap in the re-rendered page (Stimulus reconnects).
            const doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
            document.body.replaceWith(doc.body);
            window.scrollTo({ top: 0 });
        });

        xhr.addEventListener('error', () => {
            this.percentTarget.textContent = this.messagesValue.failed;
            this.submitTargets.forEach((b) => (b.disabled = false));
        });

        xhr.send(new FormData(form));
    }
}

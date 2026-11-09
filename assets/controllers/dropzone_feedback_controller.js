import { Controller } from '@hotwired/stimulus';

/*
 * Explains why the dropzone refused files (wrong type, too many) and adds the
 * per-file size limit, which the dropzone itself doesn't check. Listens to the
 * events of the Symfony UX dropzone inside this element.
 */
export default class extends Controller {
    static targets = ['error'];
    static values = { maxBytes: Number, messages: Object };

    connect() {
        this.onReject = this.onReject.bind(this);
        this.onChange = this.onChange.bind(this);
        this.element.addEventListener('dropzone:reject', this.onReject);
        this.element.addEventListener('dropzone:change', this.onChange);
        this.element.addEventListener('dropzone:clear', this.onChange);
    }

    disconnect() {
        this.element.removeEventListener('dropzone:reject', this.onReject);
        this.element.removeEventListener('dropzone:change', this.onChange);
        this.element.removeEventListener('dropzone:clear', this.onChange);
    }

    onReject(event) {
        const reason = event.detail?.reason;
        this.show(reason === 'max_files' ? this.messagesValue.maxFiles : this.messagesValue.type);
    }

    onChange() {
        const input = this.element.querySelector('input[type=file]');
        const tooBig = [...(input?.files ?? [])].some((file) => file.size > this.maxBytesValue);
        const message = tooBig ? this.messagesValue.tooBig : '';
        input?.setCustomValidity(message);
        this.show(message);
    }

    show(message) {
        this.errorTarget.textContent = message ?? '';
        this.errorTarget.hidden = !message;
    }
}

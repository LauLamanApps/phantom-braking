import { Controller } from '@hotwired/stimulus';

/*
 * Report form: after photos are chosen in the dropzone, show each one large
 * with a plate-mask editor, and collect all masks (in file order) into one
 * hidden field. Masks survive adding/removing other photos.
 */
export default class extends Controller {
    static targets = ['editors', 'field'];
    static values = { hint: String, removeLabel: String };

    connect() {
        this.masksByFile = new Map();
        this.urls = [];
        this.rebuild = this.rebuild.bind(this);
        this.element.addEventListener('dropzone:change', this.rebuild);
        this.element.addEventListener('dropzone:clear', this.rebuild);
    }

    disconnect() {
        this.element.removeEventListener('dropzone:change', this.rebuild);
        this.element.removeEventListener('dropzone:clear', this.rebuild);
        this.urls.forEach((url) => URL.revokeObjectURL(url));
    }

    get files() {
        return [...(this.element.querySelector('input[type=file]')?.files ?? [])];
    }

    key(file) {
        return `${file.name}|${file.size}|${file.lastModified}`;
    }

    rebuild() {
        this.urls.forEach((url) => URL.revokeObjectURL(url));
        this.urls = [];
        const files = this.files;
        const keys = new Set(files.map((f) => this.key(f)));
        [...this.masksByFile.keys()].forEach((k) => keys.has(k) || this.masksByFile.delete(k));

        this.editorsTarget.replaceChildren(...files.map((file) => {
            const key = this.key(file);
            const url = URL.createObjectURL(file);
            this.urls.push(url);

            const card = document.createElement('div');
            card.className = 'min-w-0 space-y-1';
            card.dataset.controller = 'plate-mask';
            card.dataset.plateMaskMasksValue = JSON.stringify(this.masksByFile.get(key) ?? []);
            card.dataset.plateMaskRemoveLabelValue = this.removeLabelValue;
            card.dataset.action = 'plate-mask:change->photo-masks#update';
            card.dataset.fileKey = key;

            const layer = document.createElement('div');
            layer.className = 'relative cursor-crosshair touch-none select-none overflow-hidden rounded-lg';
            layer.dataset.plateMaskTarget = 'layer';
            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.draggable = false;
            img.className = 'block w-full';
            img.dataset.plateMaskTarget = 'image';
            layer.append(img);

            const caption = document.createElement('p');
            caption.className = 'truncate text-xs text-stone-500';
            caption.textContent = file.name;

            card.append(layer, caption);
            return card;
        }));
        this.write();
    }

    update(event) {
        this.masksByFile.set(event.currentTarget.dataset.fileKey, event.detail.masks);
        this.write();
    }

    write() {
        this.fieldTarget.value = JSON.stringify(this.files.map((f) => this.masksByFile.get(this.key(f)) ?? []));
        this.element.querySelector('[data-photo-masks-hint]')?.toggleAttribute('hidden', this.files.length === 0);
    }
}

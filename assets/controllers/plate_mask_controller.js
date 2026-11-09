import { Controller } from '@hotwired/stimulus';

/*
 * Draw black boxes over license plates on a photo. Masks are stored as
 * fractions [x, y, width, height] of the image, so they match whatever
 * size the server renders the photo at. Drag to add, × to remove.
 */
export default class extends Controller {
    static targets = ['layer', 'image', 'input'];
    static values = { masks: Array, removeLabel: { type: String, default: 'Remove' } };

    connect() {
        this.masks = this.masksValue.map((m) => m.map(Number));
        this.onDown = this.onDown.bind(this);
        this.layerTarget.addEventListener('pointerdown', this.onDown);
        this.render();
    }

    disconnect() {
        this.layerTarget.removeEventListener('pointerdown', this.onDown);
    }

    onDown(event) {
        if (event.button !== 0 || event.target.closest('[data-mask-remove]')) return;
        event.preventDefault();
        const rect = this.layerTarget.getBoundingClientRect();
        const start = this.point(event, rect);
        const box = this.box([start.x, start.y, 0, 0], true);
        this.layerTarget.append(box);
        this.layerTarget.setPointerCapture(event.pointerId);

        const move = (e) => {
            const p = this.point(e, rect);
            this.place(box, this.normalise(start, p));
        };
        const up = (e) => {
            this.layerTarget.removeEventListener('pointermove', move);
            this.layerTarget.removeEventListener('pointerup', up);
            box.remove();
            const mask = this.normalise(start, this.point(e, rect));
            if (mask[2] > 0.01 && mask[3] > 0.005) {
                this.masks.push(mask);
                this.changed();
            }
        };
        this.layerTarget.addEventListener('pointermove', move);
        this.layerTarget.addEventListener('pointerup', up);
    }

    point(event, rect) {
        return {
            x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
            y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
        };
    }

    normalise(a, b) {
        const round = (v) => Math.round(v * 10000) / 10000;
        return [round(Math.min(a.x, b.x)), round(Math.min(a.y, b.y)), round(Math.abs(a.x - b.x)), round(Math.abs(a.y - b.y))];
    }

    remove(index) {
        this.masks.splice(index, 1);
        this.changed();
    }

    clear(event) {
        event?.preventDefault();
        this.masks = [];
        this.changed();
    }

    changed() {
        this.render();
        this.dispatch('change', { detail: { masks: this.masks } });
    }

    render() {
        this.layerTarget.querySelectorAll('[data-mask-box]').forEach((el) => el.remove());
        this.masks.forEach((mask, i) => {
            const box = this.box(mask, false);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.maskRemove = '';
            remove.className = 'absolute -right-2 -top-2 grid h-5 w-5 place-items-center rounded-full bg-white text-xs font-bold text-stone-900 shadow';
            remove.textContent = '×';
            remove.setAttribute('aria-label', this.removeLabelValue);
            remove.addEventListener('click', () => this.remove(i));
            box.append(remove);
            this.layerTarget.append(box);
        });
        if (this.hasInputTarget) {
            this.inputTarget.value = JSON.stringify(this.masks);
        }
    }

    box(mask, drawing) {
        const el = document.createElement('div');
        el.dataset.maskBox = '';
        el.className = drawing ? 'absolute border-2 border-dashed border-white bg-black/60' : 'absolute bg-black';
        this.place(el, mask);
        return el;
    }

    place(el, [x, y, w, h]) {
        Object.assign(el.style, { left: `${x * 100}%`, top: `${y * 100}%`, width: `${w * 100}%`, height: `${h * 100}%` });
    }
}

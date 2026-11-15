import { Controller } from '@hotwired/stimulus';

/*
 * Lays the driving data the car embedded in its dashcam video (speed, pedals,
 * steering, blinkers, driver assistance, position) over the video, in step
 * with playback. The data is fetched once the video itself starts loading.
 */
const G = 9.81;
// Braking harder than this is what a phantom brake feels like; show it in red.
const HARD_BRAKING_G = -0.3;

export default class extends Controller {
    static targets = ['video', 'hud', 'toggle', 'speed', 'gear', 'automation', 'accelerator', 'brake', 'gforce', 'wheel', 'blinkerLeft', 'blinkerRight', 'position', 'compass', 'rose'];
    static values = { url: String, labels: Object };

    connect() {
        this.samples = [];
        this.visible = true;
        this.number = (digits) => new Intl.NumberFormat(document.documentElement.lang || undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
        this.onLoad = () => this.load();
        this.videoTarget.addEventListener('loadedmetadata', this.onLoad, { once: true });
        this.videoTarget.addEventListener('play', this.onLoad, { once: true });
        this.onTime = () => this.render(this.videoTarget.currentTime);
        ['timeupdate', 'seeked', 'loadeddata'].forEach((type) => this.videoTarget.addEventListener(type, this.onTime));
        this.videoTarget.addEventListener('play', () => this.follow());
    }

    disconnect() {
        ['loadedmetadata', 'play'].forEach((type) => this.videoTarget.removeEventListener(type, this.onLoad));
        ['timeupdate', 'seeked', 'loadeddata'].forEach((type) => this.videoTarget.removeEventListener(type, this.onTime));
        this.following = false;
    }

    async load() {
        if (this.loading) {
            return;
        }
        this.loading = true;
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            this.samples = (await response.json()).samples ?? [];
        } catch {
            return; // no overlay, the video itself still plays
        }
        if (this.samples.length === 0) {
            return;
        }
        this.toggleTarget.hidden = false;
        this.render(this.videoTarget.currentTime);
        this.show();
    }

    toggle() {
        this.visible = !this.visible;
        this.toggleTarget.setAttribute('aria-pressed', String(this.visible));
        this.show();
    }

    show() {
        this.hudTarget.hidden = !this.visible;
        this.compassTarget.toggleAttribute('hidden', !this.visible || !this.hasPosition); // an <svg> has no hidden property
    }

    /* timeupdate fires only a few times a second; follow each frame while playing. */
    follow() {
        if (this.following) {
            return;
        }
        this.following = true;
        const video = this.videoTarget;
        const step = () => {
            if (!this.following || video.paused || video.ended) {
                this.following = false;
                return;
            }
            this.render(video.currentTime);
            if (video.requestVideoFrameCallback) {
                video.requestVideoFrameCallback(step);
            } else {
                requestAnimationFrame(step);
            }
        };
        step();
    }

    render(time) {
        const sample = this.sampleAt(time);
        if (!sample || sample === this.shown) {
            return;
        }
        this.shown = sample;

        this.speedTarget.textContent = Math.round(sample.speed);
        this.gearTarget.textContent = sample.gear ?? '–';

        const assisted = sample.automation !== 'none';
        this.automationTarget.textContent = this.labelsValue.automation?.[sample.automation] ?? sample.automation;
        this.automationTarget.classList.toggle('text-sky-400', assisted);
        this.automationTarget.classList.toggle('text-white/60', !assisted);

        // Pedals light up like in the car: grey at rest, the accelerator brighter the further it's pressed.
        const accelerator = Math.min(100, Math.max(0, sample.accelerator ?? 0));
        this.acceleratorTarget.classList.toggle('text-white/35', accelerator === 0);
        this.acceleratorTarget.classList.toggle('text-emerald-400', accelerator > 0);
        this.acceleratorTarget.style.opacity = accelerator > 0 ? String(0.5 + accelerator / 200) : '';
        this.brakeTarget.classList.toggle('text-red-500', sample.brake);
        this.brakeTarget.classList.toggle('text-white/35', !sample.brake);

        if (sample.accelLong === null || sample.accelLong === undefined) {
            this.gforceTarget.textContent = '–';
        } else {
            const g = sample.accelLong / G;
            this.gforceTarget.textContent = `${g > 0 ? '+' : ''}${this.number(2).format(g)} g`;
            this.gforceTarget.classList.toggle('text-red-400', g <= HARD_BRAKING_G);
            this.gforceTarget.classList.toggle('font-bold', g <= HARD_BRAKING_G);
        }

        const angle = sample.steering ?? 0;
        this.wheelTarget.style.transform = `rotate(${angle}deg)`;
        this.wheelTarget.setAttribute('aria-label', `${this.wheelTarget.querySelector('title')?.textContent ?? ''} ${this.number(0).format(angle)}°`);

        this.blink(this.blinkerLeftTarget, sample.blinkerLeft);
        this.blink(this.blinkerRightTarget, sample.blinkerRight);

        this.hasPosition = sample.lat !== null && sample.lon !== null;
        if (this.hasPosition) {
            const heading = sample.heading ?? 0;
            this.positionTarget.textContent = `${this.number(4).format(sample.lat)}, ${this.number(4).format(sample.lon)}`;
            // Turn the card against the heading: driving south-west (225°) puts N at the bottom right.
            this.roseTarget.setAttribute('transform', `rotate(${-heading} 24 24)`);
            const direction = this.labelsValue.compass?.[Math.round(heading / 45) % 8] ?? '';
            this.compassTarget.setAttribute('aria-label', `${direction} (${this.number(0).format(heading)}°)`);
        } else {
            this.positionTarget.textContent = '';
        }
        this.compassTarget.toggleAttribute('hidden', !this.visible || !this.hasPosition); // an <svg> has no hidden property
    }

    blink(target, on) {
        target.classList.toggle('text-emerald-400', on);
        target.classList.toggle('text-transparent', !on);
    }

    /* The last sample at or before `time` (samples are sorted by time). */
    sampleAt(time) {
        const samples = this.samples;
        if (samples.length === 0) {
            return null;
        }
        let low = 0;
        let high = samples.length - 1;
        while (low < high) {
            const mid = Math.ceil((low + high) / 2);
            if (samples[mid].t <= time) {
                low = mid;
            } else {
                high = mid - 1;
            }
        }

        return samples[low];
    }
}

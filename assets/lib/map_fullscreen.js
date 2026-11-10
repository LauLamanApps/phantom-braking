import L from 'leaflet';

const EXPAND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
const COLLAPSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>';

/*
 * A fullscreen button under the zoom buttons. Uses the browser's Fullscreen API where it
 * works on any element; iPhone Safari only allows it for video, so there the map is laid
 * over the page instead. While fullscreen, the mouse wheel zooms (there is no page to scroll).
 */
export function enableFullscreen(map, { enter = 'Fullscreen', exit = 'Exit fullscreen' } = {}) {
    const container = map.getContainer();
    const native = document.fullscreenEnabled && typeof container.requestFullscreen === 'function';
    let button;

    const isFullscreen = () => (native ? document.fullscreenElement === container : container.classList.contains('map-fullscreen'));

    const update = () => {
        const on = isFullscreen();
        container.classList.toggle('map-is-fullscreen', on);
        button.innerHTML = on ? COLLAPSE : EXPAND;
        button.title = on ? exit : enter;
        button.setAttribute('aria-label', button.title);
        button.setAttribute('aria-pressed', String(on));
        if (on) {
            map.scrollWheelZoom.enable();
        } else {
            map.scrollWheelZoom.disable();
        }
        // Keep the same centre when the map changes size.
        const centre = map.getCenter();
        map.invalidateSize({ animate: false });
        map.setView(centre, map.getZoom(), { animate: false });
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            toggle();
        }
    };

    const toggle = () => {
        if (native) {
            if (isFullscreen()) {
                document.exitFullscreen();
            } else {
                container.requestFullscreen().catch(() => {});
            }
            return; // fullscreenchange calls update()
        }
        const on = !isFullscreen();
        container.classList.toggle('map-fullscreen', on);
        document.documentElement.classList.toggle('overflow-hidden', on);
        document[on ? 'addEventListener' : 'removeEventListener']('keydown', onKey);
        update();
    };

    const Control = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const bar = L.DomUtil.create('div', 'leaflet-bar leaflet-control map-fullscreen-control');
            button = L.DomUtil.create('a', '', bar);
            button.href = '#';
            button.setAttribute('role', 'button');
            L.DomEvent.disableClickPropagation(bar);
            L.DomEvent.on(button, 'click', (event) => {
                L.DomEvent.preventDefault(event);
                toggle();
            });

            return bar;
        },
    });
    map.addControl(new Control());

    if (native) {
        document.addEventListener('fullscreenchange', update);
        map.on('unload', () => document.removeEventListener('fullscreenchange', update));
    }
    map.on('unload', () => {
        document.removeEventListener('keydown', onKey);
        document.documentElement.classList.remove('overflow-hidden');
    });

    button.innerHTML = EXPAND;
    button.title = enter;
    button.setAttribute('aria-label', enter);
    button.setAttribute('aria-pressed', 'false');
}

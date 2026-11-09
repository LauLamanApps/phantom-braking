/*
 * Mouse-wheel zoom for Leaflet maps that doesn't hijack page scrolling:
 *  - plain wheel zooms once the map was clicked/focused, until the pointer leaves it;
 *  - Ctrl/⌘ + wheel (and trackpad pinch, which browsers report as Ctrl + wheel) always zooms;
 *  - otherwise the page scrolls and a short hint explains how to zoom.
 */
export function enableWheelZoom(map, hintText) {
    const container = map.getContainer();
    map.scrollWheelZoom.disable();

    const hint = document.createElement('div');
    hint.className = 'map-wheel-hint';
    hint.textContent = hintText;
    hint.hidden = true;
    container.appendChild(hint);

    let hintTimer;
    let lastStep = 0;

    const activate = () => {
        map.scrollWheelZoom.enable();
        hint.hidden = true;
    };
    map.on('click focus', activate);
    container.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());

    container.addEventListener('wheel', (event) => {
        if (map.scrollWheelZoom.enabled()) {
            return; // Leaflet handles it
        }
        if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            // Pinch gestures fire many small events: step at most every 120 ms.
            const now = performance.now();
            if (now - lastStep < 120 || Math.abs(event.deltaY) < 1) {
                return;
            }
            lastStep = now;
            map.setZoomAround(map.mouseEventToContainerPoint(event), map.getZoom() + (event.deltaY < 0 ? 1 : -1));
            return;
        }
        hint.hidden = false;
        clearTimeout(hintTimer);
        hintTimer = setTimeout(() => (hint.hidden = true), 1500);
    }, { passive: false });
}

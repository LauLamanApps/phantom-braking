import { Controller } from '@hotwired/stimulus';

const BAR_COLOURS = { light: '#ffffff', dark: '#141110' };
const ONE_YEAR = 60 * 60 * 24 * 365;

/*
 * Light, dark or the device's own setting. A chosen theme is kept in the theme
 * cookie, so the server renders it on the next page without a flash; "system"
 * removes the cookie. The switch applies at once and tells charts and maps to
 * repaint (theme:change on window).
 */
export default class extends Controller {
    choose({ params: { choice } }) {
        const root = document.documentElement;
        const secure = location.protocol === 'https:' ? '; Secure' : '';

        if (choice === 'light' || choice === 'dark') {
            root.dataset.theme = choice;
            document.cookie = `theme=${choice}; path=/; max-age=${ONE_YEAR}; SameSite=Lax${secure}`;
        } else {
            delete root.dataset.theme;
            document.cookie = `theme=; path=/; max-age=0; SameSite=Lax${secure}`;
        }

        this.element.querySelectorAll('button[data-theme-switch-choice-param]').forEach((button) => {
            if (button.dataset.themeSwitchChoiceParam === choice) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }
        });
        document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
            const scheme = meta.media.includes('dark') ? 'dark' : 'light';
            meta.content = BAR_COLOURS[root.dataset.theme ?? scheme];
        });

        this.element.open = false;
        window.dispatchEvent(new CustomEvent('theme:change'));
    }
}

import { Controller } from '@hotwired/stimulus';

/* Copies the source field's text and briefly confirms it on the button. */
export default class extends Controller {
    static targets = ['source', 'button'];
    static values = { copiedLabel: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
        } catch {
            // No clipboard access (old browser, insecure context): select the text so it can be copied by hand.
            this.sourceTarget.select();
            return;
        }

        const label = this.buttonTarget.textContent;
        this.buttonTarget.textContent = this.copiedLabelValue;
        setTimeout(() => { this.buttonTarget.textContent = label; }, 2000);
    }
}

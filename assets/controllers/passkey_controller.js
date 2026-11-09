import { Controller } from '@hotwired/stimulus';

/*
 * Runs a WebAuthn ceremony against the webauthn bundle's endpoints:
 * fetch options → let the browser/authenticator sign → post the result.
 * mode "login" logs in with any passkey stored for this site (no e-mail needed);
 * mode "register" adds a passkey to the logged-in admin.
 */
export default class extends Controller {
    static targets = ['button', 'error'];
    static values = {
        mode: String,
        optionsUrl: String,
        resultUrl: String,
        redirectUrl: String,
        messages: Object, // { unsupported, cancelled, failed }
    };

    connect() {
        if (!this.supported) {
            this.buttonTarget.disabled = true;
            this.showError(this.messagesValue.unsupported);
        }
    }

    get supported() {
        return typeof window.PublicKeyCredential?.parseRequestOptionsFromJSON === 'function';
    }

    async run() {
        this.showError(null);
        this.buttonTarget.disabled = true;
        const register = this.modeValue === 'register';

        try {
            const options = await this.post(this.optionsUrlValue, {});
            const credential = register
                ? await navigator.credentials.create({ publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options) })
                : await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) });
            await this.post(this.resultUrlValue, credential.toJSON());
            window.location.assign(this.redirectUrlValue);
        } catch (error) {
            // NotAllowedError: the person cancelled, or the prompt timed out.
            this.showError(error.name === 'NotAllowedError' ? this.messagesValue.cancelled : this.messagesValue.failed);
            this.buttonTarget.disabled = false;
        }
    }

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    showError(message) {
        this.errorTarget.textContent = message ?? '';
        this.errorTarget.hidden = !message;
    }
}

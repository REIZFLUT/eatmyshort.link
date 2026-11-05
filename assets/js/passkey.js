// Passkey (WebAuthn) ceremonies against the endpoints of web-auth/webauthn-symfony-bundle.
// Uses the browser's JSON helpers (parse*OptionsFromJSON / toJSON); no extra library.

const supported = () => typeof window.PublicKeyCredential !== 'undefined'
    && typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function'
    && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function';

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    let data = null;
    try { data = await response.json(); } catch { /* empty body */ }
    if (!response.ok) {
        throw new Error(data?.errorMessage || data?.detail || `HTTP ${response.status}`);
    }
    return data;
}

function showError(element, error) {
    const box = document.querySelector(element.dataset.errorTarget || '#passkey-error');
    if (!box) return;
    box.textContent = error.name === 'NotAllowedError' ? (element.dataset.abortedMessage || error.message) : (element.dataset.errorMessage || error.message);
    box.hidden = false;
}

async function login(button) {
    const options = await postJson(button.dataset.optionsUrl, {});
    const credential = await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) });
    await postJson(button.dataset.resultUrl, credential.toJSON());
    window.location.assign(button.dataset.successUrl || '/');
}

async function register(button) {
    const options = await postJson(button.dataset.optionsUrl, {});
    const credential = await navigator.credentials.create({ publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options) });
    await postJson(button.dataset.resultUrl, credential.toJSON());
    window.location.reload();
}

export function initPasskeys() {
    const buttons = document.querySelectorAll('[data-passkey]');
    if (buttons.length === 0) return;

    if (!supported() || !window.isSecureContext) {
        buttons.forEach((button) => {
            button.disabled = true;
            const hint = document.querySelector(button.dataset.unsupportedTarget || '#passkey-unsupported');
            if (hint) hint.hidden = false;
        });
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                await (button.dataset.passkey === 'register' ? register(button) : login(button));
            } catch (error) {
                showError(button, error);
            } finally {
                button.disabled = false;
            }
        });
    });
}

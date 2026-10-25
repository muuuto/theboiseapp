import { request } from './http.js';
import { isIos } from './push.js';

// Passkeys: log in with Face ID / fingerprint. Only offered on phones and tablets: on computers
// the password stays the way in (the server works the same for both)
export const isPhone = () => isIos() || /Android/i.test(navigator.userAgent);

export async function passkeysAvailable() {
    if (!isPhone() || !window.PublicKeyCredential) return false;
    return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
}

// The server sends binary data as base64url text; the browser wants ArrayBuffers (and back)
function toBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')), (c) => c.charCodeAt(0)).buffer;
}
function toText(buffer) {
    let binary = '';
    for (const byte of new Uint8Array(buffer)) binary += String.fromCharCode(byte);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

const deviceName = () => (/iPad/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) ? 'iPad' : isIos() ? 'iPhone' : 'Android');

// Account page: this phone logs in with Face ID / fingerprint from now on
export async function addPasskey() {
    const { publicKey } = await request('POST', '/account/passkeys/options', {});
    publicKey.challenge = toBuffer(publicKey.challenge);
    publicKey.user.id = toBuffer(publicKey.user.id);
    publicKey.excludeCredentials = (publicKey.excludeCredentials ?? []).map((credential) => ({ ...credential, id: toBuffer(credential.id) }));
    const credential = await navigator.credentials.create({ publicKey });
    await request('POST', '/account/passkeys', {
        clientDataJSON: toText(credential.response.clientDataJSON),
        attestationObject: toText(credential.response.attestationObject),
        name: deviceName(),
    });
}

// Login page: Face ID / fingerprint, then the address to go to
export async function signInWithPasskey() {
    const { publicKey } = await request('POST', '/passkeys/login/options', {});
    publicKey.challenge = toBuffer(publicKey.challenge);
    const credential = await navigator.credentials.get({ publicKey });
    const { redirect } = await request('POST', '/passkeys/login', {
        id: toText(credential.rawId),
        clientDataJSON: toText(credential.response.clientDataJSON),
        authenticatorData: toText(credential.response.authenticatorData),
        signature: toText(credential.response.signature),
        userHandle: credential.response.userHandle ? toText(credential.response.userHandle) : null,
    });
    return redirect;
}

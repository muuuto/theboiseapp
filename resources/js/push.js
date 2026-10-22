// The app on the phone: service worker (notifications, offline page) and this device's notifications
const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

// iPhone/iPad: notifications only inside the app added to the home screen (iOS 16.4+)
export const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
export const isInstalled = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

export function pushState() {
    if (!supported()) return isIos() && !isInstalled() ? 'install-first' : 'unsupported';
    if (Notification.permission === 'denied') return 'denied';
    return 'ok';
}

// Laravel's CSRF protection: the XSRF-TOKEN cookie goes back as a header
async function send(method, url, body) {
    const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error(`Request failed (${response.status})`);
    return response.json();
}

// Android and computers: the browser's own "Install app" question, kept to ask it from the account page
export let installPrompt = null;
const installListeners = new Set();
export const onInstallPrompt = (listener) => installListeners.add(listener);
window.addEventListener('beforeinstallprompt', (event) => {
    installPrompt = event;
    installListeners.forEach((listener) => listener(event));
});
window.addEventListener('appinstalled', () => (installPrompt = null));

let registration = null;
export function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return Promise.resolve(null);
    registration ??= navigator.serviceWorker.register('/sw.js').catch(() => null);
    return registration;
}

export async function currentSubscription() {
    if (!supported()) return null;
    const worker = await registerServiceWorker();
    return worker ? worker.pushManager.getSubscription() : null;
}

function keyBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}

const payload = (subscription) => ({
    ...subscription.toJSON(),
    contentEncoding: (PushManager.supportedContentEncodings ?? ['aes128gcm']).includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
});

// Asks permission (the browser shows its own question), then tells the server. Returns the devices list
export async function turnOn(publicKey) {
    const worker = await registerServiceWorker();
    if (!worker) throw new Error('This browser can\'t receive notifications');
    if ((await Notification.requestPermission()) !== 'granted') throw new Error('Notifications are blocked for TheBoise in this browser\'s settings');
    await navigator.serviceWorker.ready;
    let subscription = await worker.pushManager.getSubscription();
    // Made with other keys (e.g. a copy of the site): start again
    if (subscription && subscription.options?.applicationServerKey) {
        const current = new Uint8Array(subscription.options.applicationServerKey);
        const wanted = keyBytes(publicKey);
        if (current.length !== wanted.length || current.some((byte, i) => byte !== wanted[i])) {
            await subscription.unsubscribe();
            subscription = null;
        }
    }
    try {
        subscription ??= await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });
    } catch (error) {
        throw new Error(`This browser couldn't turn on notifications (${error.message}). Try again, or from another browser.`);
    }
    return (await send('POST', '/push/subscriptions', payload(subscription))).devices;
}

export async function turnOff() {
    const subscription = await currentSubscription();
    if (!subscription) return null;
    const result = await send('DELETE', '/push/subscriptions', { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
    return result.devices;
}

// Each time the app opens: the server gets this device's address again (it can change)
export async function syncDevice() {
    try {
        const subscription = await currentSubscription();
        if (subscription && Notification.permission === 'granted') await send('POST', '/push/subscriptions', payload(subscription));
    } catch {
        // Not important: it's sent again next time
    }
}

// Logging out: this device stops receiving the notifications of this account
export async function forgetDevice() {
    try {
        const subscription = await currentSubscription();
        if (subscription) await send('DELETE', '/push/subscriptions', { endpoint: subscription.endpoint });
    } catch {
        // Logging out goes on anyway
    }
}

export async function endpointHash() {
    const subscription = await currentSubscription();
    if (!subscription) return null;
    const bytes = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(subscription.endpoint)));
    return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

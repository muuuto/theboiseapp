// A request outside Inertia (autosave, the push subscription), with Laravel's CSRF protection:
// the XSRF-TOKEN cookie goes back as a header. body: an object (sent as JSON) or FormData (files)
export async function request(method, url, body, { keepalive = false } = {}) {
    const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    const form = body instanceof FormData;
    const response = await fetch(url, {
        method,
        headers: { ...(form ? {} : { 'Content-Type': 'application/json' }), Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: form ? body : JSON.stringify(body),
        keepalive,
    });
    if (!response.ok) {
        const error = new Error(`Request failed (${response.status})`);
        error.status = response.status;
        error.errors = (await response.json().catch(() => null))?.errors ?? {};
        throw error;
    }
    return response.json();
}

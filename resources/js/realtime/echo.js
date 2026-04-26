import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

function createAuthorizer(csrfToken) {
    return (channel) => ({
        authorize(socketId, callback) {
            fetch('/broadcasting/auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channel.name,
                }),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Broadcast auth failed');
                    }

                    return response.json();
                })
                .then((data) => callback(false, data))
                .catch((error) => callback(true, error));
        },
    });
}

export function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;
    if (!key) {
        return null;
    }

    const host = import.meta.env.VITE_REVERB_HOST || import.meta.env.VITE_PUSHER_HOST || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || import.meta.env.VITE_PUSHER_PORT || 443);
    const scheme = import.meta.env.VITE_REVERB_SCHEME || import.meta.env.VITE_PUSHER_SCHEME || 'https';
    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster,
        authEndpoint: '/broadcasting/auth',
        authorizer: createAuthorizer(csrfToken),
    });

    return echoInstance;
}

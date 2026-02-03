console.log('Echo.js loaded');
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

console.log('Reverb Config:', {
    host: import.meta.env.VITE_REVERB_HOST,
    port: import.meta.env.VITE_REVERB_PORT,
    scheme: import.meta.env.VITE_REVERB_SCHEME
});

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

console.log('Echo instance created:', window.Echo);

// Log connection events
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ WebSocket Connected!');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('❌ WebSocket Disconnected');
});

/**
 * تجربة استلام الأحداث في الكونسول
 */
const channel = window.Echo.channel('client-change');
console.log('Subscribed to channel:', channel);

channel.listen('.ClientChange', (e) => {
    console.log('🔔 تم تعديل بيانات العميل!', e);
});

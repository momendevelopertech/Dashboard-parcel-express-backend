import Echo from 'laravel-echo';
import axios from 'axios';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'parcel_express_key',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    
    // Custom authorizer for Sanctum authentication
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/api/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                }, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    callback(false, response.data);
                })
                .catch(error => {
                    console.error('Broadcasting auth error:', error);
                    callback(true, error.response);
                });
            }
        };
    },
    
    // Reverb-specific options
    disableStats: true,
    enableLogging: import.meta.env.DEV || false,
    activityTimeout: 30000,
    pongTimeout: 10000,
    disconnectedRetryDelay: 3000,
});

// Handle connection states
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('🔗 WebSocket connected to Reverb server');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('❌ WebSocket disconnected from Reverb server');
});

window.Echo.connector.pusher.connection.bind('error', (error) => {
    console.error('⚠️ WebSocket error:', error);
});

window.Echo.connector.pusher.connection.bind('auth_error', (error) => {
    console.error('🔐 WebSocket authentication error:', error);
});

// Expose Pusher merchant globally for Laravel Echo (used by Reverb)

export default window.Echo;

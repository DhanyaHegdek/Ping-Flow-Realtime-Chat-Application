import "./bootstrap"; // Axios + CSRF (already in Laravel)

import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;
console.log("APP.JS LOADED");
/**
 * Laravel Echo — Reverb broadcaster
 *
 * KEY CHANGE from React frontend:
 * - React used JWT Bearer token in Authorization header for /api/broadcasting/auth
 * - Blade uses session cookies → /broadcasting/auth (registered by BroadcastServiceProvider)
 * - No token needed — browser sends session cookie automatically
 *
 * channels.php uses PresenceChannel for conversation.{id}
 * which returns { id, name } for the presence user list (online status)
 */
window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "http") === "https",
    enabledTransports: ["ws", "wss"],
    // No authEndpoint or auth headers — session cookie handles this
});
console.log("WINDOW ECHO =", window.Echo);

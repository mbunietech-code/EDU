import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // livekit-client (the live classroom's WebRTC library, ~150 kB gzipped) is a
    // lazily loaded chunk — only downloaded when someone joins a live class.
    build: { chunkSizeWarningLimit: 700 },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});

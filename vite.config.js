import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command, mode }) => {
    // VITE_PORT lives in .env, which compose.yaml also reads for the port mapping,
    // so the container's listener and Docker's published port can't disagree.
    const port = Number(loadEnv(mode, process.cwd(), '').VITE_PORT || 5299);

    // The dev server is only supported inside laravel.test: WSL's localhost relay never
    // forwarded 5173 to Windows on this machine, so a Vite started in WSL is unreachable
    // from the browser. Fail here rather than write a public/hot nothing can load.
    if (command === 'serve' && !process.env.LARAVEL_SAIL) {
        throw new Error('Run the dev server through Sail: ./vendor/bin/sail npm run dev (see CLAUDE.md §2).');
    }

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/shell.css',
                    'resources/js/shell.js',
                    'resources/css/home.css',
                    'resources/css/player.css',
                    'resources/js/player.js',
                    'resources/css/projects.css',
                    'resources/js/projects.js',
                    'resources/css/photos.css',
                    'resources/js/photos.js',
                    'resources/css/music.css',
                    'resources/js/music.js',
                    'resources/css/diary.css',
                    'resources/js/diary.js',
                    'resources/css/extra-notes.css',
                    'resources/js/extra-notes.js',
                    'resources/css/future-updates.css',
                    'resources/js/future-updates.js',
                    'resources/css/videos.css',
                    'resources/js/videos.js',
                    'resources/css/login.css',
                    'resources/js/login.js',
                ],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                    // Display face for the login page's title only.
                    bunny('Gloock', {
                        weights: [400],
                    }),
                ],
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            // What the browser connects to — Docker's published port on the Windows side.
            // The Laravel plugin writes this into public/hot.
            hmr: {
                host: 'localhost',
                clientPort: port,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});

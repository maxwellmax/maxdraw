import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: [
            'resources/js/canvas/**/*.test.ts',
            'resources/js/prancheta/**/*.test.ts',
        ],
        passWithNoTests: true,
    },
});

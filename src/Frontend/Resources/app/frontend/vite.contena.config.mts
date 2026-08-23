import path from 'node:path';
import { defineConfig } from 'vite';

/**
 * Vite build config for the Contena runtime module.
 *
 * Produces a single ES module at Resources/public/frontend/contena/contena.js that:
 * - Exports ContenaComponent and Contena as named ES module exports.
 * - Assigns both to window as the public frontend component runtime API.
 */
export default defineConfig({
    resolve: {
        alias: {
            src: path.resolve(import.meta.dirname, 'src'),
        },
    },
    build: {
        outDir: '../../public/frontend/contena',
        emptyOutDir: true,
        lib: {
            entry: './src/contena.ts',
            formats: ['es'],
            fileName: () => 'contena.js',
        },
    },
});

import type { Alias, Plugin } from 'vite';
import { ensureDir, ensureFile, emptyDirSync, writeFile } from 'fs-extra';
import path from 'path';

/**
 * @private
 *
 * This plugin adds aliases pointing to temp files that export the global Vue, Vue I18n, and Vue Router instances.
 * It's only used for Contena plugins. This solves the problem of having multiple instances in the same project.
 *
 * Inspired by: https://github.com/crcong/vite-plugin-externals/
 */
export default function viteExternalsPlugin(): Plugin {
    return {
        name: 'contena-vite-plugin-vue-globals',

        // Add a vue alias to the config pointing to a temp file
        async config(config) {
            const aliasResult: Alias[] = [];
            const configAlias = config.resolve?.alias ?? {};

            // Is alias object?
            if (Object.prototype.toString.call(configAlias) === '[object Object]') {
                Object.keys(configAlias).forEach((aliasKey) => {
                    aliasResult.push({ find: aliasKey, replacement: (configAlias as Record<string, string>)[aliasKey] });
                });
            } else if (Array.isArray(configAlias)) {
                aliasResult.push(...configAlias);
            }

            // Create cache directory
            const cachePath = path.join(process.cwd(), 'node_modules', '.contena-vite-plugin-vue-globals');
            await ensureDir(cachePath);
            await emptyDirSync(cachePath);

            // Add new alias for Vue
            const vueJsCachePath = path.join(cachePath, `vue.js`);
            aliasResult.push({ find: /^vue$/, replacement: vueJsCachePath });

            // Write temp vue.js file
            await ensureFile(vueJsCachePath);
            await writeFile(vueJsCachePath, `module.exports = window['Contena']?.['Vue']`);

            // Add a vue-i18n alias using the same module instance as the Administration.
            const vueI18nJsCachePath = path.join(cachePath, `vue-i18n.js`);
            aliasResult.push({ find: /^vue-i18n$/, replacement: vueI18nJsCachePath });

            await ensureFile(vueI18nJsCachePath);
            await writeFile(vueI18nJsCachePath, `module.exports = window['Contena']?.['VueI18n']`);

            // Add a vue-router alias using the same module instance as the Administration.
            const vueRouterJsCachePath = path.join(cachePath, `vue-router.js`);
            aliasResult.push({ find: /^vue-router$/, replacement: vueRouterJsCachePath });

            await ensureFile(vueRouterJsCachePath);
            await writeFile(vueRouterJsCachePath, `module.exports = window['Contena']?.['VueRouter']`);

            return {
                resolve: {
                    alias: aliasResult,
                },
            };
        },
    };
}

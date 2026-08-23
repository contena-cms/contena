import ExternalsPlugin from './index';

describe('build/vite-plugins/externals-plugin', () => {
    it('should be a function with 0 arguments', () => {
        expect(typeof ExternalsPlugin).toBe('function');

        // check that the function has 0 arguments
        expect(ExternalsPlugin.length).toBe(0);
    });

    it('should return an object with a name and config property', () => {
        const plugin = ExternalsPlugin();

        // Identify plugin by name
        expect(plugin).toHaveProperty('name');
        expect(plugin.name).toBe('contena-vite-plugin-vue-globals');

        // Check if the plugin has a transform method
        expect(plugin).toHaveProperty('config');
    });

    it('should add vue alias for config without own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = { resolve: {} };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult).toHaveLength(3);

        const vueAlias = aliasResult[0];
        expect(vueAlias.find).toStrictEqual(/^vue$/);
        expect(vueAlias.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue.js')).toBe(true);

        const vueI18nAlias = aliasResult[1];
        expect(vueI18nAlias.find).toStrictEqual(/^vue-i18n$/);
        expect(vueI18nAlias.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue-i18n.js')).toBe(true);

        const vueRouterAlias = aliasResult[2];
        expect(vueRouterAlias.find).toStrictEqual(/^vue-router$/);
        expect(vueRouterAlias.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue-router.js')).toBe(
            true,
        );
    });

    it('should add vue alias for config with own alias', async () => {
        const plugin = ExternalsPlugin();
        const config = {
            resolve: {
                alias: [
                    {
                        find: /@/,
                        replacement: './src/',
                    },
                ],
            },
        };

        const result = await plugin.config(config);

        const aliasResult = result.resolve.alias;
        expect(aliasResult).toHaveLength(4);

        const srcAlias = aliasResult[0];
        expect(srcAlias.find).toStrictEqual(/@/);
        expect(srcAlias.replacement).toBe('./src/');

        const vue = aliasResult[1];
        expect(vue.find).toStrictEqual(/^vue$/);
        expect(vue.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue.js')).toBe(true);

        const vueI18n = aliasResult[2];
        expect(vueI18n.find).toStrictEqual(/^vue-i18n$/);
        expect(vueI18n.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue-i18n.js')).toBe(true);

        const vueRouter = aliasResult[3];
        expect(vueRouter.find).toStrictEqual(/^vue-router$/);
        expect(vueRouter.replacement.endsWith('node_modules/.contena-vite-plugin-vue-globals/vue-router.js')).toBe(true);
    });
});

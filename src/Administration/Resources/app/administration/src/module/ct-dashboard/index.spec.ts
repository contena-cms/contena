import enGB from './snippet/en.json';
import zhCN from './snippet/zh.json';

describe('src/module/ct-dashboard', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers dashboard translations for every supported locale', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-dashboard');

        expect(module?.manifest.snippets).toEqual({
            'en-GB': enGB,
            'zh-CN': zhCN,
        });
    });
});

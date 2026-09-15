import './index';

describe('src/module/ct-oauth-authorize', () => {
    it('registers the module with a single core route', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-oauth-authorize');
        expect(module).toBeTruthy();

        const routes = module?.routes;
        expect(routes?.size).toBe(1);

        const indexRoute = routes?.get('ct.oauth.authorize.index');
        expect(indexRoute).toBeTruthy();
        expect(indexRoute?.path).toBe('/oauth/authorize');
        expect(indexRoute?.coreRoute).toBe(true);
    });

    it('registers the page component', () => {
        const components = Contena.Component.getComponentRegistry();
        expect(components.has('ct-oauth-authorize-index')).toBe(true);
    });
});

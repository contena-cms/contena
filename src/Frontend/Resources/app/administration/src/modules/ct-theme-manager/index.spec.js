import PrivilegesService from 'src/app/service/privileges.service';

describe('ct-theme-manager module', () => {
    beforeAll(() => {
        Contena.Application.addServiceProvider('privileges', () => new PrivilegesService());

        jest.isolateModules(() => {
            require('./index');
        });
    });

    it('registers module with routes and navigation', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-theme-manager');

        expect(module).toBeDefined();
        expect(module.manifest.navigation).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 'ct-theme-manager',
                    path: 'ct.theme.manager.index',
                    parent: 'ct-content',
                }),
            ]),
        );

        const routes = module.routes;
        expect(routes.get('ct.theme.manager.index').components.default).toBe('ct-theme-manager-list');
        expect(routes.get('ct.theme.manager.detail').components.default).toBe('ct-theme-manager-detail');
    });

    it('adds channel detail theme route when missing', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-theme-manager');
        const routeMiddleware = module.manifest.routeMiddleware;
        const next = jest.fn();
        const currentRoute = {
            name: 'ct.channel.detail',
            children: [],
        };

        routeMiddleware(next, currentRoute);

        expect(currentRoute.children).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    name: 'ct.channel.detail.theme',
                    component: 'ct-channel-detail-theme',
                }),
                expect.objectContaining({
                    name: 'ct.channel.detail.contentLayouts',
                    component: 'ct-channel-detail-content-layouts',
                }),
            ]),
        );
        expect(next).toHaveBeenCalledWith(currentRoute);
    });

    it('does not duplicate channel detail theme route', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-theme-manager');
        const routeMiddleware = module.manifest.routeMiddleware;
        const currentRoute = {
            name: 'ct.channel.detail',
            children: [
                { name: 'ct.channel.detail.theme' },
                { name: 'ct.channel.detail.contentLayouts' },
            ],
        };

        routeMiddleware(jest.fn(), currentRoute);

        expect(currentRoute.children).toHaveLength(2);
    });
});

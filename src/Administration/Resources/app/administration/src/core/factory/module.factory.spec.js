/* eslint-disable ct-test-rules/test-file-max-lines-warning */

const { Module, Application } = Contena;
const ModuleFactory = Module;
const register = ModuleFactory.register;

describe('core/factory/module.factory.js', () => {
    // We're clearing the modules registry to register the same module multiple times throughout the test suite
    beforeEach(async () => {
        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();
    });

    it('should not register a module when no unique identifier is specified', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const module = register('', {});

        expect(module).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Module has no unique identifier "id". Abort registration.',
            expect.any(Object),
        );
    });

    it('should not register a module with same name twice', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const moduleDefinition = {
            routes: {
                index: {
                    component: 'ct-foo-bar',
                    path: 'index',
                },
            },
        };

        const moduleOne = register('ct-foo', moduleDefinition);
        const moduleTwo = register('ct-foo', moduleDefinition);

        expect(typeof moduleOne).toBe('object');
        expect(moduleTwo).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'A module with the identifier "ct-foo" is registered already. Abort registration.',
            expect.any(Object),
        );
    });

    it('should not register a module when the unique identifier does not have a namespace', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const module = register('foo', {
            routes: {
                index: {
                    component: 'ct-foo-bar',
                    path: 'index',
                },
            },
        });

        expect(module).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Module identifier does not match the necessary format "[namespace]-[name]":',
            'foo',
            'Abort registration.',
        );
    });

    it('should not register a module without a route definition', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const module = register('ct-foo', {
            name: 'Test',
        });

        expect(module).toBe(false);
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Module "ct-foo" has no configured routes or a routeMiddleware.',
            'The module will not be accessible in the administration UI.',
            'Abort registration.',
            expect.objectContaining({
                display: true,
            }),
        );
    });

    it('should be possible to register a module with a valid route definition', () => {
        const module = register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });

        expect(typeof module).toBe('object');
        expect(module.routes).toBeInstanceOf(Map);
        expect(typeof module.manifest).toBe('object');
        expect(typeof module.type).toBe('string');
        expect(module.type).toBe('plugin');
        expect(module.navigation).toBeUndefined();
    });

    it('should be possible to register a module with two components per route', () => {
        const module = register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    components: {
                        default: 'ct-foo-bar-index',
                        it: 'ct-foo-test',
                    },
                },
            },
        });

        const route = module.routes.get('ct.foo.index');

        expect(module.routes.has('ct.foo.index')).toBe(true);
        expect(typeof module).toBe('object');
        expect(module.routes).toBeInstanceOf(Map);
        expect(typeof module.manifest).toBe('object');
        expect(typeof module.type).toBe('string');
        expect(module.type).toBe('plugin');
        expect(module.navigation).toBeUndefined();

        expect(typeof route.components.it).toBe('string');
        expect(route.components.it).toBe('ct-foo-test');
        expect(typeof route.components.default).toBe('string');
        expect(route.components.default).toBe('ct-foo-bar-index');
    });

    it('should be possible to register a module with a navigation entry', () => {
        const module = register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
            navigation: [
                {
                    icon: 'box',
                    color: '#f00',
                    label: 'FooIndex',
                    path: 'ct.foo.index',
                },
            ],
        });

        expect(module.navigation).toBeInstanceOf(Array);
        const navigationEntry = module.navigation[0];
        expect(typeof navigationEntry).toBe('object');
    });

    it('should be possible to register a module with multiple navigation entries', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const module = register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
            navigation: [
                {
                    id: 'ct.foo.index',
                    icon: 'box',
                    color: '#f00',
                    label: 'FooIndex',
                },
                {
                    link: 'http://de.contena.cn',
                    label: 'ExternalLink',
                    parent: 'ct.foo.index',
                },
                {
                    label: 'InvalidEntry',
                },
            ],
        });

        expect(module.navigation).toBeInstanceOf(Array);
        expect(module.navigation).toHaveLength(2);
        const routerNavigationEntry = module.navigation[0];
        const externalLinkNavigation = module.navigation[1];
        expect(typeof routerNavigationEntry).toBe('object');
        expect(routerNavigationEntry.label).toBe('FooIndex');

        expect(typeof externalLinkNavigation).toBe('object');
        expect(externalLinkNavigation.link).toBe('http://de.contena.cn');
    });

    it('should be possible to get all registered modules', () => {
        register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });

        const modules = ModuleFactory.getModuleRegistry();

        expect(modules.size).toBe(1);
        expect(modules.has('ct-foo')).toBe(true);
    });

    it('should be possible to get all registered module routes', () => {
        register('ct-foo', {
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });

        const routes = ModuleFactory.getModuleRoutes();

        expect(routes).toBeInstanceOf(Array);
        expect(typeof routes[0]).toBe('object');
        expect(routes[0].name).toBe('ct.foo.index');
        expect(routes[0].type).toBe('plugin');
    });

    it('should be possible to get an module by its entity name', () => {
        const registeredModule = register('ct-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });

        const module = ModuleFactory.getModuleByEntityName('foo');

        expect(typeof module).toBe('object');
        expect(module).toEqual(registeredModule);
        expect(module.manifest.entity).toBe('foo');
    });

    it('should return first module when entity isn`t unique', () => {
        const registeredModule = register('ct-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });

        register('ct-another-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'ct-another-foo-bar-index',
                },
            },
        });

        const module = ModuleFactory.getModuleByEntityName('foo');

        expect(typeof module).toBe('object');
        expect(module).toEqual(registeredModule);
        expect(module.manifest.entity).toBe('foo');
    });

    it('should return undefined if module with that entity is not found', () => {
        register('ct-foo', {
            entity: 'foo',

            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
        });
        const module = ModuleFactory.getModuleByEntityName('bar');

        expect(typeof module).toBe('undefined');
    });

    it('merges snippets from more than two modules', () => {
        register('ct-foo', {
            entity: 'foo',
            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            foo: 'foo',
                        },
                    },
                },
            },
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-index',
                },
            },
        });
        register('ct-bar', {
            entity: 'bar',

            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            bar: 'bar',
                        },
                    },
                },
            },
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-index',
                },
            },
        });
        register('ct-baz', {
            entity: 'bar2',

            snippets: {
                'de-DE': {
                    global: {
                        snippets: {
                            foo: 'no foo',
                        },
                    },
                },
            },

            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-index',
                },
            },
        });

        const moduleFactory = Application.getContainer('factory').module;
        expect(moduleFactory.getModuleSnippets()).toEqual({
            'de-DE': {
                global: {
                    snippets: {
                        foo: 'no foo',
                        bar: 'bar',
                    },
                },
            },
        });
    });

    it('should add settings item if feature flag is active', () => {
        global.activeFeatureFlags = ['testFlag'];
        Contena.Store.get('settingsItems').settingsGroups = {};

        register('ct-foo', {
            name: 'fooBar',
            title: 'barFoo',
            settingsItem: {
                group: 'fooGroup',
                to: 'foo.bar',
                icon: 'fooIcon',
            },
            flag: 'testFlag',
            routes: {
                index: {
                    component: 'ct-foo-bar',
                    path: 'index',
                },
            },
        });

        const expectedSettingsItem = {
            fooGroup: [
                {
                    group: 'fooGroup',
                    icon: 'fooIcon',
                    id: 'ct-foo',
                    label: 'barFoo',
                    name: 'fooBar',
                    to: 'foo.bar',
                },
            ],
        };
        expect(Contena.Store.get('settingsItems').settingsGroups).toEqual(expectedSettingsItem);
    });

    it('should not add settings item if feature flag is deactivated', () => {
        global.activeFeatureFlags = [];
        Contena.Store.get('settingsItems').settingsGroups = {};

        register('ct-foo', {
            name: 'fooBar',
            title: 'barFoo',
            settingsItem: {
                group: 'fooGroup',
                to: 'foo.bar',
                icon: 'fooIcon',
            },
            flag: 'testFlag',
            routes: {
                index: {
                    component: 'ct-foo-bar',
                    path: 'index',
                },
            },
        });

        expect(Contena.Store.get('settingsItems').settingsGroups).toEqual({});
    });

    it('should not allow plugin modules to create menu entries on first level', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const pluginModule = register('ct-foo', {
            type: 'plugin',
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foo-bar-index',
                },
            },
            navigation: [
                {
                    icon: 'box',
                    color: '#f00',
                    label: 'FooIndex',
                    path: 'ct.foo.index',
                },
            ],
        });

        // Register a module of type plugin without a "parent" in the navigation object
        expect(pluginModule.type).toBe('plugin');
        expect(pluginModule.navigation).toBeInstanceOf(Array);
        expect(pluginModule.navigation).toHaveLength(0);

        // Check for the warning inside the console
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Navigation entries from plugins are not allowed on the first level.',
            'Set a property "parent" to register your navigation entry',
        );
    });

    it('should require plugin navigation entries to declare an ACL privilege', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        const pluginModule = register('acme-documents', {
            type: 'plugin',
            routes: {
                index: {
                    path: 'index',
                    component: 'acme-documents-index',
                },
            },
            navigation: [
                {
                    label: 'Documents',
                    path: 'acme.documents.index',
                    parent: 'ct-plugins',
                },
            ],
        });

        expect(pluginModule.navigation).toEqual([]);
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Plugin navigation entries need an ACL privilege.',
            'Register the plugin permission and set navigation.privilege.',
        );
    });

    it('should register ACL-controlled plugin navigation and custom settings groups', () => {
        Contena.Store.get('settingsItems').settingsGroups = {};

        const pluginModule = register('acme-documents', {
            type: 'plugin',
            name: 'Documents',
            title: 'Documents',
            routes: {
                index: {
                    path: 'index',
                    component: 'acme-documents-index',
                },
            },
            navigation: [
                {
                    label: 'Documents',
                    path: 'acme.documents.index',
                    parent: 'ct-plugins',
                    privilege: 'acme_documents.viewer',
                },
            ],
            settingsItem: {
                group: 'acme',
                to: 'acme.documents.index',
                icon: 'regular-file',
                privilege: 'acme_documents.viewer',
            },
        });

        expect(pluginModule.navigation).toContainEqual(
            expect.objectContaining({
                path: 'acme.documents.index',
                privilege: 'acme_documents.viewer',
            }),
        );
        expect(Contena.Store.get('settingsItems').settingsGroups.acme).toContainEqual(
            expect.objectContaining({
                group: 'acme',
                privilege: 'acme_documents.viewer',
            }),
        );
    });

    it('should reject plugin settings entries without an ACL privilege', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);
        Contena.Store.get('settingsItems').settingsGroups = {};

        register('acme-documents', {
            type: 'plugin',
            name: 'Documents',
            title: 'Documents',
            routes: {
                index: {
                    path: 'index',
                    component: 'acme-documents-index',
                },
            },
            settingsItem: {
                group: 'acme',
                to: 'acme.documents.index',
                icon: 'regular-file',
            },
        });

        expect(Contena.Store.get('settingsItems').settingsGroups).toEqual({});
        expect(spy).toHaveBeenCalledWith(
            '[ModuleFactory]',
            'Plugin settings entries need an ACL privilege.',
            'Register the plugin permission and set settingsItem.privilege.',
        );
    });

    it('should allow core modules to create menu entries on first level', () => {
        const spy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(spy);

        // Check a core module without a "parent" in the navigation object
        const coreModule = register('ct-foobar', {
            type: 'core',
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foobar-bar-index',
                },
            },
            navigation: [
                {
                    icon: 'box',
                    color: '#f00',
                    label: 'FooIndex',
                    path: 'ct.foobar.index',
                },
            ],
        });

        expect(typeof coreModule.type).toBe('string');
        expect(coreModule.type).toBe('core');
        expect(coreModule.navigation).toBeInstanceOf(Array);
        expect(coreModule.navigation).toHaveLength(1);

        expect(spy).not.toHaveBeenCalled();
    });

    it('should not register a module when display property is false', async () => {
        const module = register('1337-foo-bar', {
            type: 'core',
            display: false,
            routes: {
                index: {
                    path: 'index',
                    component: 'ct-foobar-bar-index',
                },
            },
            navigation: [
                {
                    icon: 'box',
                    color: '#f00',
                    label: 'FooIndex',
                    path: 'ct.foobar.index',
                },
            ],
        });

        expect(module).toBe(false);
    });

    it('should use the alia route', () => {
        const module = register('ct-example-route', {
            routes: {
                index: {
                    components: {
                        default: 'ct-manufacturer-list',
                    },
                    path: 'index',
                    alias: 'foo',
                },
            },
        });

        expect(module.routes.has('ct.example.route.index')).toBe(true);

        const route = module.routes.get('ct.example.route.index');

        expect(module.routes).toBeInstanceOf(Map);
        expect(route.path).toBe('/sw/example/route/index');
        expect(route.alias).toBe('/sw/example/route/foo');
        expect(route.name).toBe('ct.example.route.index');
    });

    it('should use the routePrefixPath even for alias routes', () => {
        const module = register('ct-example-route', {
            routePrefixPath: 'good-route',
            routes: {
                index: {
                    components: {
                        default: 'ct-manufacturer-list',
                    },
                    path: 'index',
                    alias: 'foo',
                },
            },
        });

        expect(module.routes.has('ct.example.route.index')).toBe(true);

        const route = module.routes.get('ct.example.route.index');

        expect(module.routes).toBeInstanceOf(Map);
        expect(route.path).toBe('/good-route/index');
        expect(route.alias).toBe('/good-route/foo');

        expect(route.name).toBe('ct.example.route.index');
    });
});

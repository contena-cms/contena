/**
 * @sw-package framework
 */
import initState from 'src/app/init-pre/state.init';

describe('src/app/init-pre/state.init.ts', () => {
    initState();

    it('should contain all state methods', () => {
        expect(Contena.State._store).toBeDefined();
        expect(Contena.State.list).toBeDefined();
        expect(Contena.State.get).toBeDefined();
        expect(Contena.State.getters).toBeDefined();
        expect(Contena.State.commit).toBeDefined();
        expect(Contena.State.dispatch).toBeDefined();
        expect(Contena.State.watch).toBeDefined();
        expect(Contena.State.subscribe).toBeDefined();
        expect(Contena.State.subscribeAction).toBeDefined();
        expect(Contena.State.registerModule).toBeDefined();
        expect(Contena.State.unregisterModule).toBeDefined();
    });

    it('should initialized all state modules', () => {
        expect(Contena.Store.get('contenaApps')).toBeDefined();
    });

    it('should be able to get cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Contena.State.get("cmsPageState") is deprecated! Use Contena.Store.get instead.';
            },
        });

        Contena.Store.register({
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
        });

        expect(Contena.Store.get('cmsPage').foo).toBe('bar');
        Contena.Store.unregister('cmsPage');
    });

    it('should be able to commit cmsPageState backwards compatible', () => {
        // The cmsPageState is deprecated and causes a warning, therefore ignore it
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (_, msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg === 'Contena.State.get("cmsPageState") is deprecated! Use Contena.Store.get instead.';
            },
        });

        Contena.Store.register({
            id: 'cmsPage',
            state: () => ({
                foo: 'bar',
            }),
            actions: {
                setFoo(foo) {
                    this.foo = foo;
                },
            },
        });

        const store = Contena.Store.get('cmsPage');
        expect(store.foo).toBe('bar');

        store.setFoo('jest');
        expect(store.foo).toBe('jest');

        Contena.Store.unregister('cmsPage');
    });
});

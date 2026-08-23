import { h } from 'vue';
import ComponentFactory from './async-component.factory';

describe('core/factory/async-component.factory', () => {
    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        ComponentFactory._clearComponentHelper();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('registers and builds a native render component', async () => {
        ComponentFactory.register('test-render-component', {
            render: () => h('div', 'content'),
        });

        const component = await ComponentFactory.build('test-render-component');

        expect(component).not.toBe(false);
        expect(component).toMatchObject({ name: 'test-render-component' });
        expect(typeof (typeof component === 'boolean' ? undefined : component.render)).toBe('function');
    });

    it('keeps inline Vue templates without processing them as Twig', async () => {
        ComponentFactory.register('test-inline-template-component', {
            template: '<div>content</div>',
        });

        const component = await ComponentFactory.build('test-inline-template-component');

        expect(component).not.toBe(false);
        expect(typeof component === 'boolean' ? undefined : component.template).toBe('<div>content</div>');
    });

    it('rejects components without a render source', async () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        ComponentFactory.register('test-empty-component', { setup: () => ({}) });

        await expect(ComponentFactory.build('test-empty-component')).rejects.toThrow(
            'The component registry could not build the component with the name "test-empty-component".',
        );
    });

    it('rejects duplicate component names', () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        ComponentFactory.register('test-duplicate-component', { render: () => h('div') });

        expect(ComponentFactory.register('test-duplicate-component', { render: () => h('span') })).toBe(false);
    });

    it('orders component overrides by their explicit index', () => {
        ComponentFactory.register('test-overridden-component', {
            render: () => h('div'),
            methods: {
                getLabel: () => 'base',
            },
        });
        ComponentFactory.override(
            'test-overridden-component',
            {
                methods: {
                    getLabel: () => 'late',
                },
            },
            20,
        );
        ComponentFactory.override(
            'test-overridden-component',
            {
                methods: {
                    getLabel: () => 'early',
                },
            },
            10,
        );

        const overrides = ComponentFactory.getOverrideRegistry().get('test-overridden-component');

        expect(overrides?.map(({ index }) => index)).toEqual([
            10,
            20,
        ]);
    });

    it('registers component helpers once', () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const helper = jest.fn();

        expect(ComponentFactory.registerComponentHelper('mapState', helper)).toBe(true);
        expect(ComponentFactory.registerComponentHelper('mapState', helper)).toBe(false);
        expect(ComponentFactory.getComponentHelper()).toHaveProperty('mapState', helper);
    });

    it('marks synchronous components', () => {
        ComponentFactory.markComponentAsSync('test-sync-component');

        expect(ComponentFactory.isSyncComponent('test-sync-component')).toBe(true);
    });
});

import { computed, createSSRApp, h, ref } from 'vue';
import type { Component } from 'vue';
import { renderToString } from '@vue/server-renderer';
import ComponentFactory from './async-component.factory';

describe('SFC component extensions', () => {
    it('inherits the render function when an extension only changes component behavior', async () => {
        ComponentFactory.register('sfc-render-parent', {
            _renderedBySfcTemplate: true,
            render() {
                return h('div', 'parent');
            },
        });

        ComponentFactory.extend('sfc-behavior-child', 'sfc-render-parent', {
            methods: {
                childMethod() {
                    return 'child';
                },
            },
        });

        const component = await ComponentFactory.build('sfc-behavior-child');

        expect(component).not.toBe(false);
        await expect(renderToString(createSSRApp(component as Component))).resolves.toBe('<div>parent</div>');
    });

    it('inherits an inline SFC setup render when an extension only changes component behavior', async () => {
        ComponentFactory.register('sfc-inline-render-parent', {
            _renderedBySfcTemplate: true,
            setup(_props, { expose }) {
                const parentValue = ref('parent');
                const getLabel = ref(() => parentValue.value);

                expose({ parentValue, getLabel });

                return () => h('div', getLabel.value());
            },
        });

        ComponentFactory.extend('sfc-inline-render-behavior-child', 'sfc-inline-render-parent', {
            setup() {
                const parent = ComponentFactory.getExtensionParentSetup();
                const parentValue = parent.parentValue as ReturnType<typeof ref<string>>;

                return {
                    getLabel: ref(() => `${parentValue.value}-child`),
                };
            },
        });

        const component = await ComponentFactory.build('sfc-inline-render-behavior-child');

        expect(component).not.toBe(false);
        await expect(renderToString(createSSRApp(component as Component))).resolves.toBe('<div>parent-child</div>');
    });

    it('merges parent setup bindings and exposes them to the child setup', async () => {
        ComponentFactory.register('sfc-extension-parent', {
            _renderedBySfcTemplate: true,
            setup() {
                return {
                    parentValue: ref('parent'),
                };
            },
            render() {
                return h('div');
            },
        });

        ComponentFactory.extend('sfc-extension-child', 'sfc-extension-parent', {
            _renderedBySfcTemplate: true,
            setup() {
                const parent = ComponentFactory.getExtensionParentSetup();
                const parentValue = parent.parentValue as ReturnType<typeof ref<string>>;

                return {
                    childValue: computed(() => `${parentValue.value}-child`),
                };
            },
            render(this: { childValue: string }) {
                return h('div', this.childValue);
            },
        });

        const component = await ComponentFactory.build('sfc-extension-child');

        expect(component).not.toBe(false);
        await expect(renderToString(createSSRApp(component as Component))).resolves.toBe('<div>parent-child</div>');
    });

    it('uses exposed bindings in the child render context when production SFC setup functions return render functions', async () => {
        ComponentFactory.register('sfc-inline-template-parent', {
            _renderedBySfcTemplate: true,
            setup(_props, { expose }) {
                const parentValue = ref('parent');
                const getLabel = (suffix: string) => `${parentValue.value}-${suffix}`;

                expose({ parentValue, getLabel });

                return () => h('span', parentValue.value);
            },
        });

        ComponentFactory.extend('sfc-inline-template-child', 'sfc-inline-template-parent', {
            _renderedBySfcTemplate: true,
            setup(_props, { expose }) {
                const parent = ComponentFactory.getExtensionParentSetup();
                const parentValue = parent.parentValue as ReturnType<typeof ref<string>>;
                const childValue = computed(() => `${parentValue.value}-child`);

                expose({ childValue });

                return (renderContext: { parentValue: string; getLabel: (suffix: string) => string }) =>
                    h('div', `${renderContext.parentValue}:${renderContext.getLabel('child')}`);
            },
        });

        const component = await ComponentFactory.build('sfc-inline-template-child');

        expect(component).not.toBe(false);
        await expect(renderToString(createSSRApp(component as Component))).resolves.toBe('<div>parent:parent-child</div>');
    });
});

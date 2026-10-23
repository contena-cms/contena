/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(propsOverride) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-blog-name', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: {
                        content: {
                            source: 'static',
                            value: null,
                        },
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                    },
                },
                defaultConfig: {},
                ...propsOverride,
            },
            global: {
                provide: {
                    cmsService: Contena.Service('cmsService'),
                },
                stubs: {
                    'sw-tabs': {
                        data() {
                            return {
                                active: '',
                            };
                        },
                        template: `
                    <div class="sw-tabs">
                        <slot name="default" v-bind="{ active }"></slot>
                        <slot name="content" v-bind="{ active }"></slot>
                    </div>
                `,
                    },
                    'mt-tabs': true,
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item', {
                        sync: true,
                    }),
                    'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field', { sync: true }),
                    'sw-text-editor': {
                        props: ['value'],
                        emits: ['blur', 'update:value', 'change'],
                        template:
                            '<input type="text" :value="value" @blur="$emit(\'blur\', $event.target.value)" @input="$emit(\'update:value\', $event.target.value)" @change="$emit(\'change\', $event.target.value)"></input>',
                    },
                    'sw-select-field': true,
                    'sw-extension-component-section': true,
                    'router-link': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                },
            },
        },
    );
}

describe('module/sw-cms/elements/blog-name/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Contena.Store.get('cmsPage').resetCmsPageState();
        Contena.Store.get('cmsPage').setCurrentMappingEntity('blog');
        Contena.Store.get('cmsPage').setCurrentPage({
            type: 'blog_detail',
        });
    });

    it('should map to a blog name if the component is in a blog page', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('blog.name');
    });

    it('should not initially map to a blog name if element translated config exists', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Blog',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
                translated: {
                    config: {
                        content: {
                            source: 'static',
                            value: 'Sample Blog',
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Blog');
    });

    it('should not initially map to a blog name if element config exists', async () => {
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Blog 1',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
                translated: {
                    config: null,
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Blog 1');
    });
});

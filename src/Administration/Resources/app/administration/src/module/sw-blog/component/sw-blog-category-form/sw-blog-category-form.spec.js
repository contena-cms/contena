/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-blog/page/sw-blog-detail/store';
import { nextTick } from 'vue';

describe('module/sw-blog/component/sw-blog-category-form', () => {
    const defaultSalesChannelData = {};

    async function createWrapper(blogEntityOverride, parentProductOverride) {
        const blogEntity = {
            metaTitle: 'Blog1',
            id: 'blogId1',
            isNew: () => false,
            ...blogEntityOverride,
        };

        const parentProduct = {
            id: 'blogId',
            ...parentProductOverride,
        };
        const store = Contena.Store.get('swProductDetail');
        store.$reset();
        store.blog = blogEntity;
        store.parentProduct = parentProduct;
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'visibility_structure',
                        label: 'sw-blog.detailBase.cardTitleVisibilityStructure',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-blog.general.textAdvancedMode',
                },
            },
        };

        if (!Contena.Feature.isActive('v6.8.0.0')) {
            store.creationStates = 'is-physical';
        }
        store.creationType = 'physical';

        return mount(await wrapTestComponent('sw-blog-category-form', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.blog.detail.base',
                        params: {
                            id: 1,
                        },
                    },
                },
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-modal': true,
                    'sw-multi-tag-select': true,

                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-category-tree-field': true,
                    'sw-entity-tag-select': true,
                    'sw-blog-visibility-select': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'sw-blog-visibility-detail': true,
                    'sw-ai-copilot-badge': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () =>
                                    Promise.resolve([
                                        {
                                            id: '98432def39fc4624b33213a56b8c944d',
                                            name: 'Headless',
                                        },
                                    ]),
                                create: () => ({}),
                            };
                        },
                    },
                    systemConfigApiService: {
                        getConfig: () => Promise.resolve(),
                        getValues: () => Promise.resolve(defaultSalesChannelData),
                    },
                    feature: {
                        isActive: () => true,
                    },
                },
            },
        });
    }

    let wrapper;

    it('should show Visibility Structure item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const structureFieldsClassName = [
            '.sw-blog-category-form__tag-field-wrapper',
            '.sw-blog-category-form__search-keyword-field',
        ];

        structureFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Visibility Structure item fields when advanced mode is off', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        const advancedModeSetting = Contena.Store.get('swProductDetail').advancedModeSetting;

        Contena.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-blog.general.textAdvancedMode',
                },
            },
        };

        const structureFieldsClassName = [
            '.sw-blog-category-form__tag-field-wrapper',
            '.sw-blog-category-form__search-keyword-field',
        ];

        await nextTick();

        structureFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(false);
        });
    });
});

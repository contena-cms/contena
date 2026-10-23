/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

describe('module/sw-blog/component/sw-blog-deliverability-form', () => {
    async function createWrapper(blogEntityOverride, parentProductOverride) {
        const blogEntity = {
            metaTitle: 'Blog1',
            id: 'blogId1',
            ...blogEntityOverride,
        };

        const parentProduct = {
            id: 'blogId',
            ...parentProductOverride,
        };

        const store = Contena.Store.get('swProductDetail');
        store.blog = blogEntity;
        store.parentProduct = parentProduct;
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'deliverability',
                        label: 'sw-blog.detailBase.cardTitleDeliverabilityInfo',
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

        return mount(
            await wrapTestComponent('sw-blog-deliverability-form', {
                sync: true,
            }),
            {
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
                        'sw-entity-single-select': true,
                        'sw-inheritance-switch': true,
                        'sw-help-text': true,
                    },
                },
            },
        );
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const deliveryFieldsClassName = [
            '.blog-deliverability-form__delivery-time',
            '.sw-blog-deliverability__restock-field',
            '.sw-blog-deliverability__shipping-free',
            '.sw-blog-deliverability__min-purchase',
            '.sw-blog-deliverability__purchase-step',
            '.sw-blog-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
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

        await nextTick();

        const deliveryFieldsClassName = [
            '.blog-deliverability-form__delivery-time',
            '.sw-blog-deliverability__restock-field',
            '.sw-blog-deliverability__shipping-free',
            '.sw-blog-deliverability__min-purchase',
            '.sw-blog-deliverability__purchase-step',
            '.sw-blog-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findByLabel('sw-blog.settingsForm.labelStock').element.value).toBe('0');
    });
});

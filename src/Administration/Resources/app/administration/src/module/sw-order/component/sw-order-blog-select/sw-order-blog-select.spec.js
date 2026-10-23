import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const createWrapper = async () => {
    return mount(await wrapTestComponent('sw-order-blog-select', { sync: true }), {
        props: {
            taxStatus: 'net',
            item: {
                priceDefinition: {
                    isCalculated: false,
                    taxRules: [{ taxRate: 0, percentage: 100 }],
                    price: 0,
                },
                price: {
                    taxRules: [{ taxRate: 0 }],
                    unitPrice: '...',
                    quantity: 1,
                    totalPrice: '...',
                },
                quantity: 1,
                unitPrice: 0,
                totalPrice: 0,
                precision: 2,
                label: '',
            },
            channelId: '1',
        },
        global: {
            stubs: {
                'sw-text-field': true,
                'sw-entity-single-select': true,
                'sw-blog-variant-info': true,
                'sw-select-result': true,
            },
        },
    });
};

describe('src/module/sw-order/component/sw-order-blog-select', () => {
    beforeAll(() => {
        Contena.Service().register('cartStoreService', () => {
            return {
                getLineItemTypes: () => {
                    return Object.freeze({
                        BLOG: 'blog',
                        CREDIT: 'credit',
                        CUSTOM: 'custom',
                        PROMOTION: 'promotion',
                    });
                },

                getLineItemPriceTypes: () => {
                    return Object.freeze({
                        ABSOLUTE: 'absolute',
                        QUANTITY: 'quantity',
                    });
                },
            };
        });

        const mockResponses = global.repositoryFactoryMock.responses;

        mockResponses.addResponse({
            method: 'POST',
            url: '/search/blog',
            status: 200,
            response: {
                data: [
                    {
                        attributes: {
                            id: '1',
                            name: 'Blog test',
                            price: [
                                {
                                    gross: 110,
                                    net: 100,
                                },
                            ],
                            tax: {
                                taxRate: 7,
                            },
                        },
                        id: '1',
                        relationships: [],
                    },
                ],
                meta: {
                    total: 1,
                },
            },
        });
    });

    it('should show blog select if item is new blog', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                _isNew: true,
                type: 'blog',
            },
        });

        const blogSelect = wrapper.find('sw-entity-single-select-stub');

        expect(blogSelect.exists()).toBeTruthy();
    });

    it('should show input select if item is custom item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'custom',
            },
        });

        const textField = wrapper.find('.mt-text-field');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show input select if item is credit item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'credit',
            },
        });

        const textField = wrapper.find('.mt-text-field');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show text if item is existing blog', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                label: 'Existing blog',
                type: 'blog',
            },
        });

        const blogSelect = wrapper.find('sw-entity-single-select-stub');
        const textField = wrapper.find('.mt-text-field');

        expect(blogSelect.exists()).toBeFalsy();
        expect(textField.exists()).toBeFalsy();
        expect(wrapper.text()).toBe('Existing blog');
    });

    it('blog item should have net price if tax status is not gross', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            taxStatus: 'net',
            item: {
                ...wrapper.props().item,
                type: 'blog',
            },
        });

        await wrapper.vm.onItemChanged('1');

        await flushPromises();

        expect(wrapper.vm.item.priceDefinition.price).toBe(100);
    });

    it('blog item should have gross price if tax status is gross', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            taxStatus: 'gross',
            item: {
                ...wrapper.props().item,
                type: 'blog',
            },
        });

        await wrapper.vm.onItemChanged('1');

        await flushPromises();

        expect(wrapper.vm.item.priceDefinition.price).toBe(110);
    });

    it('has correct criteria filters', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.blogCriteria;

        expect(criteria.filters[0].type).toBe('multi');
        expect(criteria.filters[0].operator).toBe('OR');
        expect(criteria.filters[0].queries[0].type).toBe('equals');
        expect(criteria.filters[0].queries[0].field).toBe('childCount');
        expect(criteria.filters[0].queries[0].value).toBe(0);
        expect(criteria.filters[0].queries[1].type).toBe('equals');
        expect(criteria.filters[0].queries[1].field).toBe('childCount');
        expect(criteria.filters[0].queries[1].value).toBeNull();

        expect(criteria.filters[1].type).toBe('equals');
        expect(criteria.filters[1].field).toBe('visibilities.channelId');
        expect(criteria.filters[1].value).toBe('1');
        expect(criteria.filters[2].type).toBe('equals');
        expect(criteria.filters[2].field).toBe('active');
        expect(criteria.filters[2].value).toBe(true);
    });

    it('has correct criteria with total count mode is zero', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.blogCriteria;

        expect(criteria.totalCountMode).toBe(0);
    });
});

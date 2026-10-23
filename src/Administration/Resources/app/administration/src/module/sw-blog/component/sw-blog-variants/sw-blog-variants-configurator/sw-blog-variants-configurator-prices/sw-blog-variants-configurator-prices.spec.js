/*
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const currencySearch = jest.fn(() =>
    Promise.resolve([
        {
            id: 'currencyId',
            name: 'Euro',
            symbol: '€',
        },
    ]),
);

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-blog-variants-configurator-prices', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'currency') {
                                return { search: currencySearch };
                            }

                            return {};
                        },
                    },
                },
                stubs: {
                    'sw-simple-search-field': true,
                    'sw-data-grid': true,
                    'sw-loader': true,
                },
            },
            props: {
                blog: {
                    taxId: 'taxId',
                    configuratorSettings: [],
                },
                selectedGroups: [],
            },
        },
    );
}

describe('src/module/sw-blog/component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-variants-configurator-prices', () => {
    beforeEach(() => {
        currencySearch.mockClear();
    });

    it('should load all currencies for the surcharge columns', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(currencySearch).toHaveBeenCalledTimes(1);
        expect(currencySearch.mock.calls[0][0].getLimit()).toBe(500);
        expect(wrapper.vm.currenciesList).toHaveLength(1);
    });
});

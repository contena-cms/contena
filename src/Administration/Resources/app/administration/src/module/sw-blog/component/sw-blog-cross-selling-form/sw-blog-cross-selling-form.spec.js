/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const { Store } = Contena;

const blogMock = {
    id: 'blogId',
    properties: [],
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-blog-cross-selling-form', {
            sync: true,
        }),
        {
            props: {
                crossSelling: {},
                allowEdit: false,
            },
            global: {
                stubs: {
                    'sw-container': true,
                    'sw-context-button': true,
                    'sw-text-field': true,
                    'sw-context-menu-item': true,
                    'sw-select-field': true,
                    'sw-entity-single-select': true,
                    'sw-blog-cross-selling-assignment': true,
                    'sw-blog-stream-modal-preview': true,
                    'sw-modal': true,
                    'sw-condition-tree': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => {
                                    return Promise.resolve([]);
                                },
                                search: () => {
                                    return Promise.resolve([]);
                                },
                            };
                        },
                    },
                    blogStreamConditionService: {
                        search: () => {},
                    },
                },
            },
        },
    );
}

describe('module/sw-blog/component/sw-blog-cross-selling-form', () => {
    let wrapper;

    beforeAll(() => {
        Store.get('swProductDetail').$reset();
        Store.get('swProductDetail').blog = blogMock;
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should get correct sorting types', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            blogStream: {
                filters: {
                    entity: 'blog',
                    source: 'source',
                },
            },
        });

        expect(wrapper.vm.sortingTypes).toEqual([
            {
                label: 'sw-blog.crossselling.priceDescendingSortingType',
                value: 'cheapestPrice:DESC',
            },
            {
                label: 'sw-blog.crossselling.priceAscendingSortingType',
                value: 'cheapestPrice:ASC',
            },
            {
                label: 'sw-blog.crossselling.nameSortingType',
                value: 'name:ASC',
            },
            {
                label: 'sw-blog.crossselling.releaseDateDescendingSortingType',
                value: 'releaseDate:DESC',
            },
            {
                label: 'sw-blog.crossselling.releaseDateAscendingSortingType',
                value: 'releaseDate:ASC',
            },
        ]);
    });
});

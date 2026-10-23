/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function getError(method, ...args) {
    try {
        await method(...args);

        throw new Error('Method should have thrown an error');
    } catch (error) {
        return error;
    }
}

const blogStreamsMock = [
    {
        id: 1,
        name: 'Low prices',
    },
    {
        id: 2,
        name: 'Standard prices',
    },
    {
        id: 3,
        name: 'High prices',
    },
];
blogStreamsMock.total = 3;

const blogsMock = [
    {
        id: 1,
        name: 'Gaming chair',
    },
    {
        id: 2,
        name: 'Gaming desk',
    },
];

let repositoryFactoryMock;

async function createWrapper() {
    repositoryFactoryMock = {
        search: () => {
            return Promise.resolve();
        },
        get: () => {
            return Promise.resolve();
        },
    };

    return mount(await wrapTestComponent('sw-sales-channel-blogs-assignment-dynamic-blog-groups', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-card-section': true,
                'sw-simple-search-field': true,
                'sw-empty-state': true,
                'sw-entity-listing': true,
                'sw-pagination': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return repositoryFactoryMock;
                    },
                },
            },
        },
        props: {
            channel: {
                id: 1,
                name: 'Headless',
            },
            containerStyle: {},
        },
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-blogs-assignment-dynamic-blog-groups', () => {
    it('should get blog streams when component got created', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.blogStreamColumns).toEqual(
            expect.arrayContaining([expect.objectContaining({ property: 'name' })]),
        );

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get blog streams successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.blogStreamRepository.search = jest.fn(() => {
            return Promise.resolve(blogStreamsMock);
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.blogStreams).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ name: 'Low prices' }),
                expect.objectContaining({ name: 'Standard prices' }),
                expect.objectContaining({ name: 'High prices' }),
            ]),
        );
        expect(wrapper.vm.total).toBe(3);

        wrapper.vm.blogStreamRepository.search.mockRestore();
    });

    it('should get blog streams failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.blogStreamRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.blogStreams).toEqual(expect.arrayContaining([]));
        expect(wrapper.vm.total).toBe(0);

        wrapper.vm.blogStreamRepository.search.mockRestore();
    });

    it('should get blog streams when searching', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onSearch('Standard prices');

        expect(wrapper.vm.term).toBe('Standard prices');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.blogStreamCriteria.term).toBe('Standard prices');

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get blog streams when paginating', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onPaginate({ page: 2, limit: 5 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should open blog stream correctly', async () => {
        const wrapper = await createWrapper();

        window.open = jest.fn();
        wrapper.vm.$router.resolve = jest.fn(() => ({ href: 'href' }));

        wrapper.vm.onOpen(blogStreamsMock[1]);

        expect(wrapper.vm.$router.resolve).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.blog.stream.detail',
                params: expect.objectContaining({ id: 2 }),
            }),
        );
        expect(window.open).toHaveBeenCalledWith('href', '_blank');

        wrapper.vm.$router.resolve.mockRestore();
        window.open.mockClear();
    });

    it('should call to get blogs from blog streams when selecting blog streams', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.resolve(blogsMock);
        });

        await wrapper.vm.onSelect({ 1: blogStreamsMock[0] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted()['selection-change'][0]).toEqual(expect.arrayContaining([blogsMock, 'groupProducts']));

        wrapper.vm.getProductsFromProductStreams.mockRestore();
    });

    it('should call to show error notification when selecting blog streams', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.onSelect({ 1: blogStreamsMock[0] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({ message: 'Whoops!' }));

        wrapper.vm.getProductsFromProductStreams.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should exit the function when selecting blog streams', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onSelect({});

        expect(wrapper.emitted()['selection-change'][0]).toEqual(expect.arrayContaining([[], 'groupProducts']));
    });

    it('should get blogs from blog streams successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve(blogsMock);
        });

        await wrapper.vm.getProductsFromProductStreams({ 1: blogStreamsMock[0] }).then((values) => {
            expect(values.flat()).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' }),
                ]),
            );
        });

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get blogs from blog streams failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        expect(
            (
                await getError(wrapper.vm.getProductsFromProductStreams, {
                    1: blogStreamsMock[0],
                })
            ).message,
        ).toBe('Whoops!');

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get blog stream filter successful', async () => {
        const wrapper = await createWrapper();

        const blogStreamFilterMock = {
            operator: 'OR',
            queries: [],
            type: 'multi',
        };

        wrapper.vm.blogStreamRepository.get = jest.fn(() => {
            return Promise.resolve({
                apiFilter: [blogStreamFilterMock],
            });
        });

        await wrapper.vm.getProductStreamFilter(1);

        expect(wrapper.vm.blogStreamFilter).toEqual(
            expect.arrayContaining([expect.objectContaining(blogStreamFilterMock)]),
        );

        wrapper.vm.blogStreamRepository.get.mockRestore();
    });

    it('should get blog stream filter failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.blogStreamRepository.get = jest.fn(() => {
            throw new Error('Whoops!');
        });

        expect((await getError(wrapper.vm.getProductStreamFilter, 1)).message).toBe('Whoops!');

        expect(wrapper.vm.blogStreamFilter).toEqual(expect.arrayContaining([]));

        wrapper.vm.blogStreamRepository.get.mockRestore();
    });

    it('should get blogs successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.blogRepository.search = jest.fn(() => {
            return Promise.resolve(blogsMock);
        });

        await wrapper.vm.getProducts().then((blogs) => {
            expect(blogs).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' }),
                ]),
            );
        });

        wrapper.vm.blogRepository.search.mockRestore();
    });

    it('should get blogs failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.blogRepository.search = jest.fn(() => {
            throw new Error('Whoops!');
        });

        expect((await getError(wrapper.vm.getProducts)).message).toBe('Whoops!');

        wrapper.vm.blogRepository.search.mockRestore();
    });
});

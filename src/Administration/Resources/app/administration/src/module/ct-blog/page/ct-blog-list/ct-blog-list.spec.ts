import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface BlogListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
    onCreateBlog: (creationType?: 'post' | 'media') => void;
}

async function createWrapper() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                path: '/blogs',
                name: 'ct.blog.index',
                component: { template: '<div />' },
            },
            {
                path: '/blogs/create/base',
                name: 'ct.blog.create.base',
                component: { template: '<div />' },
            },
        ],
    });
    await router.push('/blogs');
    await router.isReady();
    const blogs = Object.assign([], { total: 0 });
    const search = jest.fn(() => Promise.resolve(blogs));

    const wrapper = shallowMount(component, {
        global: {
            plugins: [router],
            provide: {
                repositoryFactory: {
                    create: () => ({ search }),
                },
                acl: { can: () => true },
                searchRankingService: {
                    getSearchFieldsByEntity: () => ({}),
                },
            },
            stubs: {
                'ct-block': true,
                'ct-page': true,
                'mt-data-table': true,
                'mt-empty-state': true,
            },
        },
    }) as unknown as VueWrapper<BlogListVm>;
    await flushPromises();

    return { wrapper, router, search };
}

describe('module/ct-blog/page/ct-blog-list', () => {
    it('loads Blogs with the content associations used by the list', async () => {
        const { wrapper, search } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.criteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'cover' }),
                expect.objectContaining({ association: 'categories' }),
                expect.objectContaining({ association: 'tags' }),
                expect.objectContaining({ association: 'visibilities' }),
            ]),
        );
    });

    it('opens the default Post creation route', async () => {
        const { wrapper, router } = await createWrapper();

        wrapper.vm.onCreateBlog();
        await flushPromises();

        expect(router.currentRoute.value).toMatchObject({
            name: 'ct.blog.create.base',
            query: { creationType: 'post' },
        });
    });

    it('opens the Media creation route from the split action', async () => {
        const { wrapper, router } = await createWrapper();

        wrapper.vm.onCreateBlog('media');
        await flushPromises();

        expect(router.currentRoute.value).toMatchObject({
            name: 'ct.blog.create.base',
            query: { creationType: 'media' },
        });
    });
});

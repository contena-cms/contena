import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

interface BlogDetailVm {
    blog: Entity<'blog'>;
}

async function createWrapper(creationType: 'post' | 'media') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                path: '/blogs/create/base',
                name: 'ct.blog.create.base',
                component: { template: '<div />' },
            },
            {
                path: '/blogs/:id/base',
                name: 'ct.blog.detail.base',
                component: { template: '<div />' },
            },
            {
                path: '/blogs',
                name: 'ct.blog.index',
                component: { template: '<div />' },
            },
        ],
    });
    await router.push('/blogs/create/base');
    await router.isReady();
    const create = jest.fn(() => ({
        id: 'blog-new',
        getEntityName: () => 'blog',
    }));

    const wrapper = shallowMount(component, {
        props: { createMode: true, creationType },
        global: {
            plugins: [router],
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create,
                        hasChanges: () => false,
                    }),
                },
                acl: { can: () => true },
            },
            stubs: {
                'ct-block': true,
                'ct-page': true,
                'router-view': true,
            },
        },
    }) as unknown as VueWrapper<BlogDetailVm>;
    await flushPromises();

    return { wrapper, create };
}

describe('module/ct-blog/page/ct-blog-detail', () => {
    it.each([
        [
            'post',
            'post',
        ],
        [
            'media',
            'media',
        ],
    ] as const)('creates a %s Blog with the selected type', async (creationType, expectedType) => {
        const { wrapper, create } = await createWrapper(creationType);

        expect(create).toHaveBeenCalledWith(Contena.Context.api);
        expect(Contena.Store.get('ctBlogDetail').creationType).toBe(expectedType);
        expect(wrapper.vm.blog.type).toBe(expectedType);
    });
});

import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory, routeLocationKey, routerKey } from 'vue-router';
import 'src/app/component/structure/ct-page';

const blogDetailRoute = {
    name: 'ct.blog.detail',
    path: '/sw/blog/detail/:id?',
    component: {},
    meta: {
        $module: {
            entity: 'blog',
        },
        parentPath: 'ct.blog.list',
    },
};

const router = createRouter({
    routes: [
        {
            name: 'index',
            path: '/',
            component: {},
        },
        {
            name: 'ct.blog.list',
            path: '/sw/blog/list',
            component: {},
            meta: {
                $module: {
                    entity: 'blog',
                },
            },
        },
        blogDetailRoute,
    ],
    history: createWebHashHistory(),
});

async function createWrapper(route = blogDetailRoute, props = {}) {
    if (router.currentRoute.value.name !== route.name) {
        await router.push({ name: route.name, params: { id: '1' } });
    }

    const defaultRouter = config.global.provide[routerKey];
    const defaultRoute = config.global.provide[routeLocationKey];
    delete config.global.provide[routerKey];
    delete config.global.provide[routeLocationKey];

    const wrapper = mount(await wrapTestComponent('ct-page', { sync: true }), {
        props,
        global: {
            stubs: {
                'ct-search-bar': true,
                'ct-notification-center': true,
                'ct-help-center': true,
                'ct-help-center-v2': true,
                'ct-context-button': true,
                'ct-context-menu-item': true,
            },
            plugins: [router],
        },
    });

    config.global.provide[routerKey] = defaultRouter;
    config.global.provide[routeLocationKey] = defaultRoute;

    return wrapper;
}

describe('src/app/component/structure/ct-page', () => {
    it('should reflect the search bar state as a root class, so the head area rows can react to it', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.ct-page').classes()).toContain('has--search-bar');
        expect(wrapper.find('.ct-page__search-bar').exists()).toBe(true);
        expect(wrapper.find('.ct-page__smart-bar').exists()).toBe(true);
    });

    it('should drop the root class without a search bar while both bars keep rendering', async () => {
        const wrapper = await createWrapper(blogDetailRoute, { showSearchBar: false });

        expect(wrapper.get('.ct-page').classes()).not.toContain('has--search-bar');
        expect(wrapper.find('.ct-page__search-bar').exists()).toBe(false);
        expect(wrapper.find('.ct-page__top-bar-actions').exists()).toBe(true);
        expect(wrapper.find('.ct-page__smart-bar').exists()).toBe(true);
    });

    it('should preserve previous path with query params and reuse them when navigating back', async () => {
        let wrapper = await createWrapper();

        expect(wrapper.vm.previousPath).toBeNull();
        expect(wrapper.vm.previousRoute).toBeNull();
        expect(wrapper.vm.parentRoute).toBe('ct.blog.list');
        expect(wrapper.vm.routerBack).toEqual({ name: 'ct.blog.list' });

        await router.push({
            name: 'ct.blog.list',
            query: { limit: '50', page: '3' },
        });

        await router.push({
            name: 'ct.blog.detail',
            params: { id: '1' },
        });

        wrapper.unmount();
        wrapper = await createWrapper();

        expect(wrapper.vm.previousPath).toBe('/sw/blog/list?limit=50&page=3');
        expect(wrapper.vm.previousRoute).toBe('ct.blog.list');
        expect(wrapper.vm.parentRoute).toBe('ct.blog.list');
        expect(wrapper.vm.routerBack).toBe('/sw/blog/list?limit=50&page=3');
    });

    it('should render the smart bar back button as a real link and navigate on click', async () => {
        const wrapper = await createWrapper();
        const push = jest.spyOn(router, 'push').mockResolvedValue(undefined);

        const backButton = wrapper.find('.smart-bar__back-btn');
        expect(backButton.element.tagName).toBe('A');
        expect(backButton.attributes('href')).toContain('/sw/blog/list');

        await backButton.trigger('click');

        expect(push).toHaveBeenCalled();
    });
});

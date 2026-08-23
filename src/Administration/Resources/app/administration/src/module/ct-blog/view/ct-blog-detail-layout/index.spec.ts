import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';

interface BlogDetailLayoutVm {
    blogId: string | null;
    onCreateLayout: () => void;
    onOpenLayout: (contentLayoutId: string) => void;
}

describe('module/ct-blog/view/ct-blog-detail-layout', () => {
    let wrapper: VueWrapper;
    let search: jest.Mock;
    let push: jest.Mock;

    beforeEach(async () => {
        search = jest.fn().mockResolvedValue([]);
        push = jest.fn().mockResolvedValue(undefined);

        wrapper = shallowMount(component, {
            global: {
                provide: {
                    [routeLocationKey]: {
                        name: 'ct.blog.detail.layout',
                        params: { id: 'blog-1' },
                        query: {},
                    },
                    [routerKey]: { push },
                    repositoryFactory: {
                        create: jest.fn(() => ({ search })),
                    },
                    acl: { can: () => true },
                },
            },
        });

        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('loads content layout assignments for the current blog', () => {
        expect(search).toHaveBeenCalledWith(expect.anything(), Contena.Context.api);
        expect((wrapper.vm as unknown as BlogDetailLayoutVm).blogId).toBe('blog-1');
    });

    it('opens Experience Studio with the current blog context', () => {
        (wrapper.vm as unknown as BlogDetailLayoutVm).onCreateLayout();

        expect(push).toHaveBeenCalledWith({
            name: 'ct.experience.studio.create',
            query: {
                rootSource: 'blog',
                entityId: 'blog-1',
            },
        });
    });

    it('opens an assigned content layout in Experience Studio', () => {
        (wrapper.vm as unknown as BlogDetailLayoutVm).onOpenLayout('layout-1');

        expect(push).toHaveBeenCalledWith({
            name: 'ct.experience.studio.detail',
            params: { id: 'layout-1' },
        });
    });
});

import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';

interface LayoutAssignmentVm {
    entityId: string | null;
    onCreateLayout: () => void;
    onOpenLayout: (contentLayoutId: string) => void;
}

interface CriteriaCall {
    filters: Array<{ field: string; value: unknown }>;
}

describe('module/ct-category/component/ct-category-layout-assignment', () => {
    let wrapper: VueWrapper;
    let search: jest.Mock;
    let push: jest.Mock;
    let create: jest.Mock;

    beforeEach(async () => {
        search = jest.fn().mockResolvedValue([]);
        push = jest.fn().mockResolvedValue(undefined);
        create = jest.fn(() => ({ search }));

        wrapper = shallowMount(component, {
            props: { entityType: 'category' },
            global: {
                provide: {
                    [routeLocationKey]: {
                        name: 'ct.category.detail.layout',
                        params: { id: 'category-1' },
                        query: {},
                    },
                    [routerKey]: { push },
                    repositoryFactory: {
                        create,
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

    it.each([
        [
            'category',
            'category_content_layout',
            'categoryId',
        ],
        [
            'landing_page',
            'landing_page_content_layout',
            'landingPageId',
        ],
    ])('loads the %s ContentLayout assignments', async (entityType, repositoryName, entityIdField) => {
        await wrapper.setProps({ entityType });
        await flushPromises();

        expect(create).toHaveBeenCalledWith(repositoryName);
        expect(search).toHaveBeenCalledWith(expect.anything(), Contena.Context.api);
        const criteriaCalls = search.mock.calls as unknown as Array<[CriteriaCall]>;
        expect(
            criteriaCalls.some(([criteria]) =>
                criteria.filters.some((filter) => filter.field === entityIdField && filter.value === 'category-1'),
            ),
        ).toBe(true);
        expect((wrapper.vm as unknown as LayoutAssignmentVm).entityId).toBe('category-1');
    });

    it('opens Experience Studio with the current category context', () => {
        (wrapper.vm as unknown as LayoutAssignmentVm).onCreateLayout();

        expect(push).toHaveBeenCalledWith({
            name: 'ct.experience.studio.create',
            query: {
                rootSource: 'category',
                entityId: 'category-1',
            },
        });
    });

    it('opens an assigned content layout in Experience Studio', () => {
        (wrapper.vm as unknown as LayoutAssignmentVm).onOpenLayout('layout-1');

        expect(push).toHaveBeenCalledWith({
            name: 'ct.experience.studio.detail',
            params: { id: 'layout-1' },
        });
    });

    it('renders the translated layout name', async () => {
        search.mockResolvedValue([
            {
                id: 'assignment-1',
                contentLayoutId: 'layout-1',
                contentLayout: {
                    name: 'Default name',
                    translated: { name: 'Translated name' },
                },
                channel: null,
            },
        ]);

        await (wrapper.vm as unknown as { loadAssignments: () => Promise<void> }).loadAssignments();
        await flushPromises();

        expect(wrapper.text()).toContain('Translated name');
        expect(wrapper.text()).not.toContain('Default name');
    });
});

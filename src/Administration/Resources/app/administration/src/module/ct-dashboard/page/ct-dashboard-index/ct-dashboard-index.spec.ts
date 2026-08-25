import { mount } from '@vue/test-utils';

describe('module/ct-dashboard/page/ct-dashboard-index', () => {
    it('renders an empty home page without loading or displaying data', async () => {
        const component = await wrapTestComponent('ct-dashboard-index', { sync: true });
        const createRepository = jest.fn();
        const wrapper = mount(component, {
            global: {
                stubs: {
                    'ct-page': { template: '<main><slot name="content" /></main>' },
                },
                mocks: {
                    $route: { meta: { $module: {} } },
                },
                provide: {
                    repositoryFactory: { create: createRepository },
                },
            },
        });

        expect(wrapper.find('.ct-dashboard-index').exists()).toBe(true);
        expect(wrapper.text()).toBe('');
        expect(wrapper.find('section, article, table, apexchart, mt-empty-state').exists()).toBe(false);
        expect(createRepository).not.toHaveBeenCalled();
    });
});

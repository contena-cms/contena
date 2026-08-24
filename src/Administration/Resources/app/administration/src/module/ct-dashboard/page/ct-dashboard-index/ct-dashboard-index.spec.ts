import { mount } from '@vue/test-utils';
describe('module/ct-dashboard/page/ct-dashboard-index', () => {
    it('renders only the mock dashboard without loading repositories', async () => {
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
        expect(wrapper.findAll('.ct-dashboard-index__metric')).toHaveLength(4);
        expect(wrapper.find('.ct-dashboard-index__recent-panel').exists()).toBe(true);
        expect(wrapper.find('.ct-dashboard-index__settings-layout').exists()).toBe(false);
        expect(wrapper.find('.ct-dashboard-index__list-surface').exists()).toBe(false);
        expect(createRepository).not.toHaveBeenCalled();

        wrapper.unmount();
    });
});

import { shallowMount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import ctExperienceStudioList from './index';

describe('module/ct-experience-studio/page/ct-experience-studio-list', () => {
    it('renders semantic layout versions as text', async () => {
        const layouts = Object.assign(
            [
                {
                    id: 'layout-1',
                    name: 'Layout',
                    version: '1.0.0',
                },
            ],
            { total: 1 },
        );
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    path: '/',
                    name: 'ct.experience.studio.index',
                    component: { template: '<div />' },
                },
            ],
        });
        await router.push('/');
        const wrapper = shallowMount(ctExperienceStudioList, {
            global: {
                plugins: [router],
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: jest.fn(() => Promise.resolve(layouts)),
                        }),
                    },
                    acl: {
                        can: jest.fn(() => true),
                    },
                    searchRankingService: {
                        getSearchFieldsByEntity: jest.fn(() => ({})),
                    },
                },
                stubs: {
                    'ct-block': {
                        template: '<div><slot /></div>',
                    },
                    'ct-page': {
                        template: '<div><slot name="content" /></div>',
                    },
                    'mt-data-table': {
                        name: 'mt-data-table',
                        props: [
                            'layout',
                            'showStripes',
                            'showOutlines',
                        ],
                        template: '<div />',
                    },
                },
            },
        });

        await flushPromises();

        expect(wrapper.vm.columnConfig).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    property: 'version',
                    renderer: 'text',
                }),
            ]),
        );

        const table = wrapper.findComponent({ name: 'mt-data-table' });

        expect(table.props()).toMatchObject({
            layout: 'full',
            showStripes: false,
            showOutlines: true,
        });
    });
});

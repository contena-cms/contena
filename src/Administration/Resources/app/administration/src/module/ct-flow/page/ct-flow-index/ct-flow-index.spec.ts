import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

const flows = Object.assign([], { total: 1 });
const templates = Object.assign([], { total: 1 });
const flowRepository = {
    search: jest.fn(() => Promise.resolve(flows)),
    create: jest.fn(),
    delete: jest.fn(),
    save: jest.fn(),
};
const templateRepository = {
    search: jest.fn(() => Promise.resolve(templates)),
};
const sequenceRepository = {
    create: jest.fn(),
    save: jest.fn(),
};
const repositoryFactory = {
    create: jest.fn((entity: string) => {
        if (entity === 'flow') return flowRepository;
        if (entity === 'flow_template') return templateRepository;

        return sequenceRepository;
    }),
};

describe('module/ct-flow/page/ct-flow-index', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    beforeEach(() => {
        jest.clearAllMocks();
        flowRepository.search.mockResolvedValue(flows);
        templateRepository.search.mockResolvedValue(templates);
    });

    it('shows flows and flow templates in separate tabs', async () => {
        const wrapper = mount(await wrapTestComponent('ct-flow-index', { sync: true }), {
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    [routerKey as symbol]: { push: jest.fn() },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({ template: '<div><slot name="content" /></div>' }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-tabs': true,
                    'mt-data-table': defineComponent({ name: 'MtDataTable', template: '<div class="mt-data-table" />' }),
                    'mt-empty-state': true,
                },
            },
        });
        await flushPromises();

        const page = wrapper.vm as unknown as {
            activeTab: 'flows' | 'templates';
            onTabChange: (tab: string) => void;
        };

        expect(page.activeTab).toBe('flows');
        expect(wrapper.findAllComponents({ name: 'MtDataTable' })).toHaveLength(1);

        page.onTabChange('templates');
        await wrapper.vm.$nextTick();

        expect(wrapper.findAllComponents({ name: 'MtDataTable' })).toHaveLength(1);
    });
});

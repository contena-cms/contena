import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

const logEntryMock = {
    id: '018dc68776077179b6c51bdf18a4f25d',
    channel: 'business_events',
    message: 'mail.sent',
    level: 200,
    context: {
        additionalData: {
            recipients: [],
            contents: {
                'text/html': '<p>Mail body</p>',
                'text/plain': 'Mail body',
            },
        },
    },
};

async function createWrapper() {
    if (!Contena.Component.getComponentRegistry().has('ct-settings-logging-mail-sent-info')) {
        Contena.Component.register('ct-settings-logging-mail-sent-info', {
            template: '<div />',
        });
    }

    return mount(await wrapTestComponent('ct-settings-logging-list', { sync: true }), {
        global: {
            stubs: {
                'ct-settings-logging-mail-sent-info': {
                    template: '<div><mt-tabs :items="items" /></div>',
                    data: () => ({
                        items: [
                            { label: 'ct-settings-logging.mailInfo.tabHTML' },
                            { label: 'ct-settings-logging.mailInfo.tabPlain' },
                            { label: 'ct-settings-logging.entryInfo.tabRaw' },
                        ],
                    }),
                },
                'ct-page': {
                    template: `<div class="ct-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'ct-search-bar': true,
                'ct-pagination': true,
                'ct-context-menu-item': true,
                'ct-entity-listing': true,
                'mt-data-table': true,
                'ct-sidebar-item': true,
                'ct-sidebar': true,
                'mt-tabs': {
                    props: ['items'],
                    template: '<div class="mt-tabs">{{ items.map((item) => item.label).join(\', \') }}</div>',
                },
                'ct-textarea-field': true,
                'ct-time-ago': true,
            },
            provide: {
                [routeLocationKey]: {
                    name: 'logging-list',
                    query: { page: 1, limit: 25 },
                    meta: {
                        $module: {
                            icon: 'test',
                        },
                    },
                    params: {},
                },
                [routerKey]: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                    }),
                },
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
        },
    });
}

describe('src/module/ct-settings-logging/page/ct-settings-logging-list', () => {
    it('should render refresh in the smart bar without a sidebar', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.ct-sidebar').exists()).toBe(false);
        expect(wrapper.text()).toContain('ct-settings-logging.list.titleSidebarItemRefresh');
    });

    it('should load default modal component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.displayedLog = {
            ...logEntryMock,
            message: 'test'.repeat(10),
        };
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-settings-logging-list__custom-content').exists()).toBe(true);
        expect(wrapper.find('ct-settings-logging-entry-info').exists()).toBe(true);
    });

    it('should load dynamic modal component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.displayedLog = {
            ...logEntryMock,
            message: 'mail.sent',
        };
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.find('.ct-settings-logging-list__custom-content').exists()).toBe(true);
        expect(wrapper.vm.modalNameFromLogEntry).toBe('ct-settings-logging-mail-sent-info');
    });
});

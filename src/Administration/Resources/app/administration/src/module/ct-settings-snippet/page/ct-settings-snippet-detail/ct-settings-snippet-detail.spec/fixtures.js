import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

const route = {
    name: 'ct.settings.snippet.detail',
    meta: { $module: { color: 'blue', icon: 'icon' } },
    query: { page: 1, limit: 25, ids: [] },
    params: { key: 'account.addressCreateBtn' },
};

export const router = { push: jest.fn(), replace: jest.fn(), resolve: jest.fn(() => ({ meta: {} })) };

const pageStub = {
    template: '<div><slot name="smart-bar-actions" /><slot name="content" /></div>',
};

function getSnippetSets() {
    const data = [
        {
            name: 'BASE zh-CN',
            baseFile: 'messages.zh',
            iso: 'zh-CN',
            customFields: null,
            createdAt: '2020-09-09T07:46:37.407+00:00',
            updatedAt: null,
            apiAlias: null,
            id: 'a2f95068665e4498ae98a2318a7963df',
            snippets: [],
            channelDomains: [],
        },
        {
            name: 'BASE en-GB',
            baseFile: 'messages.en-GB',
            iso: 'en-GB',
            customFields: null,
            createdAt: '2020-09-09T07:46:37.407+00:00',
            updatedAt: null,
            apiAlias: null,
            id: 'e54dba2ba96741868e6b6642504c6932',
            snippets: [],
            channelDomains: [],
        },
    ];

    data.total = data.length;

    data.get = () => {
        return false;
    };

    return data;
}

function getSnippets() {
    const data = {
        data: {
            'account.addressCreateBtn': [
                {
                    author: 'Contena',
                    id: null,
                    origin: '新增地址',
                    resetTo: '新增地址',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'account.addressCreateBtn',
                    value: '新增地址',
                },
                {
                    author: 'Contena',
                    id: null,
                    origin: 'Add address',
                    resetTo: 'Add address',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Add address',
                },
            ],
            test1: [
                {
                    author: 'Contena',
                    id: null,
                    origin: 'foo',
                    resetTo: 'foo',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'test1',
                    value: 'foo',
                },
                {
                    author: 'Contena',
                    id: null,
                    origin: 'bar',
                    resetTo: 'bar',
                    setId: 'e54dba2ba96741868e6b6642504c6932',
                    translationKey: 'test1',
                    value: 'bar',
                },
            ],
        },
    };

    const totalAmountOfSnippets = Object.keys(data.data).length;
    data.total = totalAmountOfSnippets;

    return data;
}

export const saveMock = jest.fn(() => Promise.resolve());

export async function createWrapper(privileges = [], repositoryOverrides = {}) {
    return mount(
        await wrapTestComponent('ct-settings-snippet-detail', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                color: 'blue',
                                icon: 'icon',
                            },
                        },
                        query: {
                            page: 1,
                            limit: 25,
                            ids: [],
                        },
                        params: {
                            key: 'account.addressCreateBtn',
                        },
                    },
                },
                provide: {
                    [routeLocationKey]: route,
                    [routerKey]: router,
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(getSnippetSets()),
                            create: () => ({}),
                            save: saveMock,
                            ...repositoryOverrides,
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    userService: {},
                    snippetSetService: {
                        getAuthors: () => {
                            return Promise.resolve();
                        },
                        getCustomList: () => {
                            return Promise.resolve(getSnippets());
                        },
                    },
                    snippetService: {
                        save: () => Promise.resolve(),
                        delete: () => Promise.resolve(),
                        getFilter: () => Promise.resolve(),
                    },
                    validationService: {},
                },
                stubs: {
                    'ct-page': pageStub,
                    'ct-skeleton': true,
                    'ct-search-bar': true,
                    'router-link': true,
                    'ct-app-actions': true,
                    'ct-loader': true,
                    'ct-error-summary': true,
                    'ct-app-topbar-button': true,
                    'ct-app-topbar-sidebar': true,
                    'ct-notification-center': true,
                    'ct-help-center-v2': true,
                    'ct-context-menu-item': true,
                    'ct-context-button': true,
                    'ct-extension-component-section': true,
                    'ct-ai-copilot-badge': true,
                    'ct-field-copyable': true,
                },
            },
        },
    );
}

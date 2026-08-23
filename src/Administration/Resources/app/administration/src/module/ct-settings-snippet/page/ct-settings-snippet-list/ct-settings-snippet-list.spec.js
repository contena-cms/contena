import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

const userConfigService = {
    search: jest.fn(() => Promise.resolve({ data: {} })),
    upsert: jest.fn(() => Promise.resolve()),
};
Contena.Service().register('userConfigService', () => userConfigService);

function getSnippets() {
    const data = {
        data: {
            'account.addressCreateBtn': [
                {
                    author: 'Contena',
                    id: null,
                    origin: 'Neue Adresse hinzufügen',
                    resetTo: 'Neue Adresse hinzufügen',
                    setId: 'a2f95068665e4498ae98a2318a7963df',
                    translationKey: 'account.addressCreateBtn',
                    value: 'Neue Adresse hinzufügen',
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
        },
    };

    const totalAmountOfSnippets = Object.keys(data.data).length;
    data.total = totalAmountOfSnippets;

    return data;
}

function getSnippetSets() {
    const data = [
        {
            baseFile: 'messages.zh',
            id: 'a2f95068665e4498ae98a2318a7963df',
            iso: 'zh-CN',
            name: 'BASE zh-CN',
        },
    ];

    data.total = data.length;

    return data;
}

describe('module/ct-settings-snippet/page/ct-settings-snippet-list', () => {
    let wrapper = null;

    beforeEach(() => {
        jest.spyOn(userConfigService, 'search').mockResolvedValue({ data: {} });
        jest.spyOn(userConfigService, 'upsert').mockResolvedValue();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
        jest.restoreAllMocks();
    });

    async function createWrapper(
        privileges = [],
        query = {},
        getCustomList = jest.fn(() => Promise.resolve(getSnippets())),
    ) {
        const snippetSetSearch = jest.fn((criteria) => {
            if (criteria.term) {
                return Promise.resolve([]);
            }

            return Promise.resolve(getSnippetSets());
        });

        wrapper = mount(
            await wrapTestComponent('ct-settings-snippet-list', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        [routeLocationKey]: {
                            name: 'ct.settings.snippet.list',
                            meta: { $module: { icon: 'test' } },
                            params: {},
                            query: {
                                ids: 'a2f95068665e4498ae98a2318a7963df',
                                ...query,
                            },
                        },
                        [routerKey]: { push: jest.fn(), replace: jest.fn(), resolve: jest.fn(() => ({ meta: {} })) },
                        repositoryFactory: {
                            create: () => ({ search: snippetSetSearch }),
                        },
                        acl: {
                            can: (identifier) => {
                                if (!identifier) {
                                    return true;
                                }

                                return privileges.includes(identifier);
                            },
                        },
                        userService: {
                            getUser: () =>
                                Promise.resolve({
                                    data: { username: 'admin' },
                                }),
                        },
                        snippetSetService: {
                            getAuthors: () => {
                                return Promise.resolve({ data: [] });
                            },
                            getCustomList,
                        },
                        snippetService: {
                            save: () => Promise.resolve(),
                            delete: () => Promise.resolve(),
                            getFilter: () => Promise.resolve({ data: [] }),
                        },
                        searchRankingService: {},
                    },
                    mocks: {
                        $route: {
                            meta: {
                                $module: {
                                    icon: 'test',
                                },
                            },
                            query: {
                                ids: 'a2f95068665e4498ae98a2318a7963df',
                                ...query,
                            },
                        },
                    },
                    stubs: {
                        'ct-page': {
                            template: `
                    <div class="ct-page">
                        <div class="smart-bar__actions">
                            <slot name="smart-bar-actions"></slot>
                        </div>
                        <slot name="content"></slot>
                    </div>`,
                        },
                        'mt-data-table': {
                            props: ['dataSource'],
                            template:
                                '<div><div v-for="item in dataSource" :key="item.id"><slot name="column-actions" :data="item" /></div></div>',
                        },
                        'router-link': true,
                        'ct-popover': true,
                        'ct-search-bar': true,
                        'ct-settings-snippet-sidebar': true,
                    },
                },
            },
        );

        return wrapper;
    }

    it('should display translation columns when opening a filtered view through a deep link', async () => {
        const getCustomList = jest.fn(() => Promise.resolve(getSnippets()));
        const wrapper = await createWrapper([], { term: 'account' }, getCustomList);

        await flushPromises();

        expect(getCustomList).toHaveBeenCalledWith(
            expect.any(Number),
            expect.any(Number),
            expect.objectContaining({
                term: 'account',
            }),
            expect.any(Object),
        );

        expect(wrapper.vm.columns).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    property: 'a2f95068665e4498ae98a2318a7963df',
                }),
            ]),
        );
    });

    it.each([
        [
            true,
            'snippet.viewer',
        ],
        [
            true,
            'snippet.viewer, snippet.editor',
        ],
        [
            true,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            false,
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should have a reset button with an disabled state of %p with the roles: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);

        await flushPromises();

        const resetButton = wrapper.find('.ct-settings-snippet-list__delete-action');
        expect(resetButton.attributes('disabled') !== undefined).toBe(state);
    });

    it.each([
        [
            true,
            'snippet.viewer',
        ],
        [
            true,
            'snippet.viewer, snippet.editor',
        ],
        [
            false,
            'snippet.viewer, snippet.editor, snippet.creator',
        ],
        [
            true,
            'snippet.viewer, snippet.editor, snippet.deleter',
        ],
    ])('should have a disabled state of %p on the new snippet button when using role: %s', async (state, role) => {
        const roles = role.split(', ');

        const wrapper = await createWrapper(roles);
        wrapper.vm.isLoading = false;

        await flushPromises();

        const createSnippetButton = wrapper.findByText('button', 'ct-settings-snippet.list.buttonAdd');

        expect(createSnippetButton.attributes('disabled') !== undefined).toBe(state);
    });
});

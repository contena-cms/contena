import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';

function getSnippetSets() {
    return [
        {
            name: 'messages.en-GB',
            iso: 'en-GB',
            path: 'development/platform/src/Core/Framework/Resources/snippet/en_GB/messages.en-GB.base.json',
            author: 'Contena',
            isBase: true,
        },
        {
            name: 'messages.zh',
            iso: 'zh-CN',
            path: 'src/Core/Framework/Resources/snippet/zh_CN/messages.zh.base.json',
            author: 'Contena',
            isBase: true,
        },
    ];
}

function getSnippetSetData() {
    const data = [
        {
            apiAlias: null,
            baseFile: 'messages.zh',
            createdAt: '2020-09-09T07:46:37.407+00:00',
            customFields: null,
            id: 'a2f95068665e4498ae98a2318a7963df',
            iso: 'zh-CN',
            name: 'BASE zh-CN',
            channelDomains: [],
            snippets: [],
            updatedAt: null,
        },
    ];

    data.total = data.length;

    return data;
}

describe('module/ct-settings-snippet/page/ct-settings-snippet-set-list', () => {
    const saveSpy = jest.fn(() => Promise.resolve());
    const createSnippetSetEntity = (overrides = {}) => ({
        ...getSnippetSetData()[0],
        ...overrides,
    });

    async function createWrapper(privileges = []) {
        return mount(
            await wrapTestComponent('ct-settings-snippet-set-list', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    mocks: {
                        $route: {
                            query: 'test',
                        },
                        $t: (key) => key,
                    },
                    provide: {
                        [routerKey]: { push: jest.fn(), replace: jest.fn(), resolve: jest.fn(() => ({ meta: {} })) },
                        acl: {
                            can: (identifier) => {
                                if (!identifier) {
                                    return true;
                                }

                                return privileges.includes(identifier);
                            },
                        },
                        snippetSetService: {
                            getBaseFiles: () => {
                                return Promise.resolve({
                                    items: getSnippetSets(),
                                });
                            },
                        },
                        repositoryFactory: {
                            create: () => ({
                                create: () => createSnippetSetEntity(),
                                search: () => Promise.resolve(getSnippetSetData()),
                                save: saveSpy,
                            }),
                        },
                        searchRankingService: {},
                    },
                    stubs: {
                        'ct-page': {
                            template: `<div class="ct-page">
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                            </div>`,
                        },
                        'mt-data-table': true,
                        'router-link': true,
                    },
                },
            },
        );
    }

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
    ])('should have a create snippet set button with a disabled state of %p when having role: %s', async (state, role) => {
        const roles = role.split(', ');
        const wrapper = await createWrapper(roles);

        await flushPromises();

        const createSetButton = wrapper.find('.ct-settings-snippet-set-list__action-add');

        expect(createSetButton.attributes('disabled') !== undefined).toBe(state);
    });

    it('should add a new snippet set', async () => {
        const wrapper = await createWrapper(['snippet.creator']);
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ name: 'ct-settings-snippet.setList.newSnippetName' }),
        );

        const createSetButton = wrapper.findByText('button', 'ct-settings-snippet.setList.buttonAddSet');
        await createSetButton.trigger('click');

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ name: 'ct-settings-snippet.setList.newSnippetName' }),
            Contena.Context.api,
        );
    });

    it('should add a new snippet set twice with unique names', async () => {
        const wrapper = await createWrapper(['snippet.creator']);
        await flushPromises();

        wrapper.vm.snippetSets = [
            ...wrapper.vm.snippetSets,
            {
                name: 'ct-settings-snippet.setList.newSnippetName',
                iso: 'zh-CN',
                path: 'src/Core/Framework/Resources/snippet/zh_CN/messages.zh.base.json',
                author: 'Contena',
                isBase: true,
            },
        ];

        const createSetButton = wrapper.findByText('button', 'ct-settings-snippet.setList.buttonAddSet');
        await createSetButton.trigger('click');

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ name: `ct-settings-snippet.setList.newSnippetName (2)` }),
            Contena.Context.api,
        );
    });

    it('should activate inline edit after creating a snippet set', async () => {
        const wrapper = await createWrapper([
            'snippet.creator',
            'snippet.editor',
        ]);
        await flushPromises();

        const createSetButton = wrapper.findByText('button', 'ct-settings-snippet.setList.buttonAddSet');
        await createSetButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.editingId).toBe(getSnippetSetData()[0].id);
    });
});

/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

function collection<T>(items: T[]) {
    return Object.assign(items, {
        first: () => items[0] ?? null,
        total: items.length,
    });
}

async function createWrapper(options: { createMode?: boolean; privileges?: string[] } = {}) {
    const persistedPosition = {
        id: 'position-id',
        name: 'General Manager',
        translated: { name: 'General Manager' },
        code: 'general_manager',
        description: 'Leads the company',
        position: 10,
        active: true,
        isNew: () => false,
    };
    const newPosition = {
        id: 'new-position-id',
        name: '',
        translated: {},
        code: '',
        description: '',
        position: 0,
        active: false,
        isNew: () => true,
    };
    const positionRepository = {
        get: jest.fn(() => Promise.resolve({ ...persistedPosition })),
        create: jest.fn(() => newPosition),
        search: jest.fn(() => Promise.resolve(collection([{ position: 20 }]))),
        save: jest.fn(() => Promise.resolve()),
    };
    const router = { push: jest.fn(), replace: jest.fn() };
    const customFieldDataProviderService = {
        getCustomFieldSets: jest.fn(() => Promise.resolve([{ id: 'position-fields' }])),
    };
    const route = {
        name: options.createMode ? 'ct.settings.position.create' : 'ct.settings.position.detail',
        params: options.createMode ? {} : { id: 'position-id' },
        query: {},
        meta: { $module: { title: 'ct-settings-position.general.mainMenuItemGeneral' } },
    };
    const wrapper = mount(await wrapTestComponent('ct-settings-position-detail', { sync: true }), {
        props: { createMode: options.createMode ?? false },
        global: {
            provide: {
                [routeLocationKey]: route,
                [routerKey]: router,
                repositoryFactory: { create: () => positionRepository },
                acl: { can: (privilege: string) => (options.privileges ?? []).includes(privilege) },
                customFieldDataProviderService,
            },
            mocks: { $t: (key: string) => key },
            stubs: {
                'ct-page': {
                    template:
                        '<div><slot name="smart-bar-back" /><slot name="smart-bar-header" /><slot name="language-switch" /><slot name="smart-bar-actions" /><slot name="content" /><slot /></div>',
                },
                'ct-card-view': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div><slot /></div>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'ct-button-process': {
                    props: [
                        'disabled',
                        'isLoading',
                        'processSuccess',
                    ],
                    emits: ['update:processSuccess'],
                    template: '<button class="process-button" :disabled="disabled"><slot /></button>',
                },
                'mt-position-form': true,
                'ct-language-switch': true,
                'ct-language-info': true,
                'mt-icon': true,
            },
        },
    });

    await flushPromises();
    return { wrapper: wrapper as any, positionRepository, router, newPosition, customFieldDataProviderService };
}

describe('module/ct-settings-position/page/ct-settings-position-detail', () => {
    it('loads a translated Position and its custom fields for editing', async () => {
        const { wrapper, positionRepository, customFieldDataProviderService } = await createWrapper({
            privileges: ['position.editor'],
        });

        expect(positionRepository.get).toHaveBeenCalledWith('position-id', Contena.Context.api);
        expect(customFieldDataProviderService.getCustomFieldSets).toHaveBeenCalledWith('position');
        expect(wrapper.vm.position.name).toBe('General Manager');
        expect(wrapper.vm.customFieldSets).toEqual([{ id: 'position-fields' }]);
        expect(wrapper.vm.allowSave).toBe(true);
    });

    it('updates, saves and reloads an existing Position', async () => {
        const { wrapper, positionRepository } = await createWrapper({ privileges: ['position.editor'] });

        wrapper.vm.onUpdatePosition('name', 'Chief Executive Officer');
        await wrapper.vm.onSave();

        expect(positionRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({ name: 'Chief Executive Officer' }),
            Contena.Context.api,
        );
        expect(positionRepository.get).toHaveBeenCalledTimes(2);
    });

    it('creates an active Position in the system language with the next sort value', async () => {
        const previousLanguageId = Contena.Context.api.languageId;
        const previousSystemLanguageId = Contena.Context.api.systemLanguageId;
        const languageChange = jest.spyOn(Contena.Utils.EventBus, 'emit');

        try {
            Contena.Context.api.languageId = 'secondary-language-id';
            Contena.Context.api.systemLanguageId = 'system-language-id';
            const { wrapper, positionRepository, router, newPosition } = await createWrapper({
                createMode: true,
                privileges: ['position.creator'],
            });

            expect(newPosition).toMatchObject({ active: true, position: 30 });
            expect(languageChange).toHaveBeenCalledWith('on-change-language-clicked', 'system-language-id');

            wrapper.vm.onUpdatePosition('name', 'Director');
            wrapper.vm.onUpdatePosition('code', 'director');
            await wrapper.vm.onSave();

            expect(positionRepository.save).toHaveBeenCalledWith(newPosition, Contena.Context.api);
            expect(router.replace).toHaveBeenCalledWith({
                name: 'ct.settings.position.detail',
                params: { id: 'new-position-id' },
            });
        } finally {
            languageChange.mockRestore();
            Contena.Context.api.languageId = previousLanguageId;
            Contena.Context.api.systemLanguageId = previousSystemLanguageId;
        }
    });

    it('keeps editing and saving behind Position privileges', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.allowSave).toBe(false);
        expect(wrapper.find('.process-button').attributes('disabled')).toBeDefined();
    });
});

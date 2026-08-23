import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';

const { Context, Data } = Contena;

type ModalVm = {
    selectedChannel: {
        homeName: string | null;
    };
    selectedContentLayoutId: string | null;
    hasNotAppliedChanges: () => boolean;
    applyChanges: () => Promise<void>;
    onLayoutReset: () => void;
    createInExperienceStudio: () => void;
};

describe('module/ct-category/component/ct-category-entry-point-modal', () => {
    let wrapper: VueWrapper;
    let channelCollection: EntityCollection<'channel'>;
    let assignmentSearch: jest.Mock;
    let assignmentSave: jest.Mock;
    let routerPush: jest.Mock;

    beforeEach(async () => {
        channelCollection = new Data.EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: 'web-channel',
                name: 'Web',
                homeEnabled: true,
                homeName: undefined,
                homeMetaTitle: undefined,
                homeMetaDescription: undefined,
                homeKeywords: undefined,
                translated: {
                    name: 'Web',
                    homeName: undefined,
                    homeMetaTitle: undefined,
                    homeMetaDescription: undefined,
                    homeKeywords: undefined,
                },
            } as unknown as Entity<'channel'>,
        ]);
        assignmentSearch = jest.fn().mockResolvedValue([]);
        assignmentSave = jest.fn().mockResolvedValue(undefined);
        routerPush = jest.fn().mockResolvedValue(undefined);

        const assignmentRepository = {
            search: assignmentSearch,
            create: jest.fn().mockReturnValue({ id: 'assignment-1' }),
            save: assignmentSave,
            delete: jest.fn().mockResolvedValue(undefined),
        };

        wrapper = shallowMount(component, {
            props: {
                categoryId: 'category-1',
                channelCollection,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: jest.fn().mockReturnValue(assignmentRepository),
                    },
                    acl: {
                        can: jest.fn().mockReturnValue(true),
                    },
                    [routeLocationKey]: {
                        name: 'ct.category.detail.base',
                        params: { id: 'category-1' },
                        query: {},
                    },
                    [routerKey]: {
                        push: routerPush,
                    },
                },
                stubs: {
                    'mt-modal-root': {
                        template: '<div><slot /></div>',
                    },
                    'mt-modal': {
                        template: '<div><slot /><slot name="footer" /></div>',
                    },
                    'ct-single-select': true,
                    'ct-entity-single-select': true,
                    'ct-discard-changes-modal': true,
                },
            },
        });

        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('keeps upstream home page edits local until they are applied', async () => {
        const vm = wrapper.vm as unknown as ModalVm;

        vm.selectedChannel.homeName = 'Home';

        expect(channelCollection.get('web-channel')?.homeName).toBeUndefined();
        expect(vm.hasNotAppliedChanges()).toBe(true);

        await vm.applyChanges();

        expect(channelCollection.get('web-channel')?.homeName).toBe('Home');
    });

    it('persists the Category ContentLayout assignment for the selected Channel', async () => {
        const vm = wrapper.vm as unknown as ModalVm;
        vm.selectedContentLayoutId = 'layout-1';

        await vm.applyChanges();

        expect(assignmentSave).toHaveBeenCalledWith(
            expect.objectContaining({
                categoryId: 'category-1',
                channelId: 'web-channel',
                contentLayoutId: 'layout-1',
            }),
            Context.api,
        );
    });

    it('clears the selected ContentLayout with the upstream reset action', () => {
        const vm = wrapper.vm as unknown as ModalVm;
        vm.selectedContentLayoutId = 'layout-1';

        vm.onLayoutReset();

        expect(vm.selectedContentLayoutId).toBeNull();
        expect(vm.hasNotAppliedChanges()).toBe(false);
    });

    it('opens Experience Studio with the upstream home page context', () => {
        const vm = wrapper.vm as unknown as ModalVm;

        vm.createInExperienceStudio();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.experience.studio.create',
            query: {
                rootSource: 'category',
                entityId: 'category-1',
                channelId: 'web-channel',
            },
        });
    });
});

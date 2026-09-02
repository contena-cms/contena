import { mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

interface BlogVisibilitySelectVm {
    channelCriteria: InstanceType<typeof Contena.Data.Criteria>;
    addItem: (channel: Entity<'channel'>) => void;
    removeItem: (channel: Entity<'channel'>) => void;
}

function getVisibilityCollection(entries: EntitySchema.blog_visibility[] = []): EntityCollection<'blog_visibility'> {
    return new EntityCollection(
        '/blog-visibility',
        'blog_visibility',
        Contena.Context.api,
        null,
        entries as Entity<'blog_visibility'>[],
        entries.length,
        null,
    );
}

function createWrapper(entries: EntitySchema.blog_visibility[] = [], disabled = false) {
    const store = Contena.Store.get('ctBlogDetail');
    store.$reset();
    store.blog = {
        id: 'blog-1',
        versionId: 'version-1',
        getEntityName: () => 'blog',
    } as unknown as Entity<'blog'> & { isNew: () => boolean };
    const entityCollection = getVisibilityCollection(entries);

    const wrapper = mount(component, {
        props: { entityCollection, disabled },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ id: 'visibility-new' }),
                    }),
                },
            },
            stubs: {
                'mt-entity-select': {
                    name: 'mt-entity-select',
                    props: [
                        'modelValue',
                        'entity',
                        'labelProperty',
                        'criteria',
                        'disabled',
                    ],
                    emits: [
                        'item-add',
                        'item-remove',
                        'update:modelValue',
                    ],
                    template: '<div />',
                },
            },
        },
    }) as unknown as VueWrapper<BlogVisibilitySelectVm>;

    return { wrapper, entityCollection };
}

describe('module/ct-blog/component/ct-blog-visibility-select', () => {
    it('selects Channels with the upstream name sorting', () => {
        const { wrapper } = createWrapper();
        const select = wrapper.findComponent({ name: 'mt-entity-select' });

        expect(select.props('entity')).toBe('channel');
        expect(select.props('labelProperty')).toBe('name');
        expect(wrapper.vm.channelCriteria.sortings).toContainEqual(expect.objectContaining({ field: 'name', order: 'ASC' }));
    });

    it('creates and removes Blog visibility associations', async () => {
        const firstChannel = { id: 'channel-1', name: 'Web' } as Entity<'channel'>;
        const secondChannel = { id: 'channel-2', name: 'API' } as Entity<'channel'>;
        const firstVisibility = {
            id: 'visibility-1',
            blogId: 'blog-1',
            blogVersionId: 'version-1',
            channelId: firstChannel.id,
            visibility: 30,
            channel: firstChannel,
        } as EntitySchema.blog_visibility;
        const { wrapper, entityCollection } = createWrapper([firstVisibility]);
        wrapper.vm.addItem(secondChannel);
        await wrapper.vm.$nextTick();

        expect(entityCollection).toHaveLength(2);
        expect(entityCollection.last()).toEqual(
            expect.objectContaining({
                blogId: 'blog-1',
                blogVersionId: 'version-1',
                channelId: 'channel-2',
                visibility: 30,
            }),
        );

        wrapper.vm.removeItem(firstChannel);
        await wrapper.vm.$nextTick();

        expect([...entityCollection.getIds()]).toEqual(['visibility-new']);
    });

    it('supports read-only embedding', () => {
        const { wrapper } = createWrapper([], true);

        expect(wrapper.findComponent({ name: 'mt-entity-select' }).props('disabled')).toBe(true);
    });
});

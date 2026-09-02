import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/ct-blog/page/ct-blog-detail/store';

interface BlogDetailBaseVm {
    onOpenMediaModal: () => void;
    onCloseMediaModal: () => void;
    onAddMedia: (media: Entity<'media'>[] | null) => void;
}

type BlogWithMedia = Entity<'blog'> & {
    media: EntityCollection<'blog_media'>;
};

function getMediaCollection(entries: EntitySchema.blog_media[] = []): EntityCollection<'blog_media'> {
    return new EntityCollection(
        '/blog-media',
        'blog_media',
        Contena.Context.api,
        null,
        entries as Entity<'blog_media'>[],
        entries.length,
        null,
    );
}

async function createWrapper(entries: EntitySchema.blog_media[] = []) {
    const store = Contena.Store.get('ctBlogDetail');
    store.$reset();
    const blog = {
        id: 'blog-1',
        versionId: 'version-1',
        media: getMediaCollection(entries),
        getEntityName: () => 'blog',
    } as unknown as BlogWithMedia;
    store.blog = blog;
    const create = jest.fn(() => ({ id: 'blog-media-new' }));

    const wrapper = mount(await wrapTestComponent('ct-blog-detail-base', { sync: true }), {
        global: {
            provide: {
                acl: { can: () => true },
                repositoryFactory: {
                    create: (entity: string) => ({
                        create,
                        search: () => Promise.resolve({ first: () => ({ folder: { id: 'folder-1' } }) }),
                        entity,
                    }),
                },
            },
            stubs: {
                'ct-blog-basic-form': true,
                'ct-blog-category-form': true,
                'ct-blog-media-form': true,
                'ct-media-modal-v2': true,
                'ct-skeleton': true,
                'mt-card': true,
            },
        },
    }) as unknown as VueWrapper<BlogDetailBaseVm>;
    await flushPromises();

    return { wrapper, blog };
}

describe('module/ct-blog/view/ct-blog-detail-base', () => {
    it('opens and closes the media modal', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.onOpenMediaModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent('ct-media-modal-v2-stub').exists()).toBe(true);
        expect(wrapper.findComponent('ct-media-modal-v2-stub').attributes('entity-context')).toBe('blog');

        wrapper.vm.onCloseMediaModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent('ct-media-modal-v2-stub').exists()).toBe(false);
    });

    it('ignores an empty media selection', async () => {
        const { wrapper, blog } = await createWrapper();

        wrapper.vm.onAddMedia(null);

        expect(blog.media).toHaveLength(0);
    });

    it('adds selected media and uses the first image as cover', async () => {
        const { wrapper, blog } = await createWrapper();
        const media = { id: 'media-1', fileName: 'image', url: 'https://example.com/image.jpg' } as Entity<'media'>;

        wrapper.vm.onAddMedia([media]);
        await flushPromises();

        expect(blog.media).toHaveLength(1);
        expect(blog.media.first()).toEqual(
            expect.objectContaining({ id: 'blog-media-new', mediaId: 'media-1', position: 0 }),
        );
        expect(blog.coverId).toBe('blog-media-new');
    });

    it('does not use spatial media as cover', async () => {
        const { wrapper, blog } = await createWrapper();
        const media = {
            id: 'media-1',
            fileName: 'model',
            fileExtension: 'glb',
            url: 'https://example.com/model.glb',
        } as Entity<'media'>;

        wrapper.vm.onAddMedia([media]);
        await flushPromises();

        expect(blog.media).toHaveLength(1);
        expect(blog.coverId).toBeUndefined();
    });

    it('rejects duplicate media with the upstream notification', async () => {
        const existing = {
            id: 'blog-media-1',
            mediaId: 'media-1',
            media: { id: 'media-1' },
        } as EntitySchema.blog_media;
        const { wrapper, blog } = await createWrapper([existing]);
        const media = { id: 'media-1', fileName: 'image' } as Entity<'media'>;

        wrapper.vm.onAddMedia([media]);
        await flushPromises();

        expect(blog.media).toHaveLength(1);
    });
});

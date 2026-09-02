import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import component from './index';

interface BlogSeoFormVm {
    openGraphMediaItem: Entity<'media'> | null;
    showOgMediaModal: boolean;
    onOpenOgMediaModal: () => void;
    onRemoveOgMedia: () => void;
    onOgMediaUploadFinish: (payload: { targetId: string }) => Promise<void>;
    onOgMediaSelectionChange: (selection: Entity<'media'>[]) => void;
}

async function createWrapper(openGraphMedia: Entity<'media'> | null = null) {
    const store = Contena.Store.get('ctBlogDetail');
    store.$reset();
    store.blog = {
        id: 'blog-1',
        openGraphMediaId: openGraphMedia?.id ?? null,
        openGraphMedia,
        getEntityName: () => 'blog',
    } as unknown as Entity<'blog'> & { isNew: () => boolean };
    const mediaGet = jest.fn((id: string) => Promise.resolve({ id, url: `https://example.com/${id}.jpg` }));

    const wrapper = mount(component, {
        props: { allowEdit: true },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ get: mediaGet }),
                },
            },
            stubs: {
                'mt-text-field': true,
                'mt-textarea': true,
                'ct-media-upload-v2': true,
                'ct-upload-listener': true,
                'ct-media-modal-v2': true,
            },
        },
    }) as unknown as VueWrapper<BlogSeoFormVm>;
    await flushPromises();

    return { wrapper, store, mediaGet };
}

describe('module/ct-blog/component/ct-blog-seo-form', () => {
    it('uses the Blog Open Graph media already loaded by the detail page', async () => {
        const media = { id: 'media-1', url: 'https://example.com/media-1.jpg' } as Entity<'media'>;
        const { wrapper, mediaGet } = await createWrapper(media);

        expect(wrapper.vm.openGraphMediaItem).toEqual(media);
        expect(mediaGet).not.toHaveBeenCalled();
    });

    it('updates Open Graph media from the media library selection', async () => {
        const { wrapper, store } = await createWrapper();
        const media = { id: 'media-2', url: 'https://example.com/media-2.jpg' } as Entity<'media'>;

        wrapper.vm.onOpenOgMediaModal();
        wrapper.vm.onOgMediaSelectionChange([media]);

        expect(store.blog.openGraphMediaId).toBe('media-2');
        expect(store.blog.openGraphMedia).toEqual(media);
        expect(wrapper.vm.showOgMediaModal).toBe(false);
    });

    it('loads uploaded Open Graph media and can remove it', async () => {
        const { wrapper, store, mediaGet } = await createWrapper();

        await wrapper.vm.onOgMediaUploadFinish({ targetId: 'media-3' });

        expect(mediaGet).toHaveBeenCalledWith('media-3', Contena.Context.api);
        expect(store.blog.openGraphMediaId).toBe('media-3');

        wrapper.vm.onRemoveOgMedia();

        expect(store.blog.openGraphMediaId).toBeNull();
        expect(store.blog.openGraphMedia).toBeNull();
    });
});

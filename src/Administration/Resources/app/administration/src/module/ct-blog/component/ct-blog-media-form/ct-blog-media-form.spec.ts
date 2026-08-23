import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/ct-blog/page/ct-blog-detail/store';
import blogImageComponent from 'src/app/component/base/ct-blog-image/ct-blog-image.vue';
import component from './index';

type BlogMedia = Entity<'blog_media'> & { isPlaceholder?: boolean };
type BlogWithMedia = Entity<'blog'> & {
    media: EntityCollection<'blog_media'>;
};

interface BlogMediaFormVm {
    blog: BlogWithMedia;
    mediaItems: BlogMedia[];
    onOpenMedia: () => void;
    markMediaAsCover: (media: BlogMedia) => void;
    onMediaItemDragSort: (dragData: BlogMedia, dropData: BlogMedia, validDrop: boolean) => void;
    isCover: (media: BlogMedia) => boolean;
    isSpatial: (media: BlogMedia) => boolean;
    isArReady: (media: BlogMedia) => boolean;
    successfulUpload: (payload: { targetId: string }) => Promise<void>;
}

function getMediaCollection(collection: EntitySchema.blog_media[] = []): EntityCollection<'blog_media'> {
    const entries = collection.map((item) => structuredClone(item) as Entity<'blog_media'>);

    return new EntityCollection('/blog-media', 'blog_media', Contena.Context.api, null, entries, entries.length, null);
}

const media = [
    {
        id: 'blogMedia1',
        blogId: 'blog1',
        blogVersionId: 'version1',
        mediaId: 'media1',
        position: 0,
        media: { id: 'media1' },
    },
    {
        id: 'blogMedia2',
        blogId: 'blog1',
        blogVersionId: 'version1',
        mediaId: 'media2',
        position: 1,
        media: { id: 'media2' },
    },
] as EntitySchema.blog_media[];

function createWrapper(canEdit = true) {
    const store = Contena.Store.get('swBlogDetail');
    store.$reset();
    store.blog = {
        id: 'blog1',
        versionId: 'version1',
        coverId: 'blogMedia1',
        cover: media[0],
        media: getMediaCollection(media),
        getEntityName: () => 'blog',
    } as unknown as BlogWithMedia;

    return mount(component, {
        global: {
            directives: {
                draggable: {},
                droppable: {},
            },
            provide: {
                acl: { can: () => canEdit },
                systemConfigApiService: {
                    getValues: () => Promise.resolve({ 'core.media.defaultEnableAugmentedReality': true }),
                },
                repositoryFactory: {
                    create: (entity: string) => ({
                        create: () => ({ id: 'blogMediaNew' }),
                        get: () =>
                            Promise.resolve({
                                id: 'media1',
                                url: 'https://example.com/media1-new-url.jpg',
                            }),
                        entity,
                    }),
                },
            },
            stubs: {
                'ct-upload-listener': true,
                'ct-blog-image': blogImageComponent,
                'ct-media-upload-v2': true,
                'ct-media-preview-v2': true,
                'ct-label': true,
                'ct-context-menu': true,
                'ct-context-menu-item': true,
                'ct-context-button': true,
                'ct-loader': true,
            },
        },
    }) as unknown as VueWrapper<BlogMediaFormVm>;
}

describe('module/ct-blog/component/ct-blog-media-form', () => {
    it('should show the media upload for editors', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('ct-media-upload-v2-stub').exists()).toBe(true);
    });

    it('should hide the media upload without edit permission', () => {
        const wrapper = createWrapper(false);

        expect(wrapper.find('ct-media-upload-v2-stub').exists()).toBe(false);
    });

    it('should only show one cover', () => {
        const wrapper = createWrapper();
        const coverCount = wrapper.vm.mediaItems.filter((item: Entity<'blog_media'>) => wrapper.vm.isCover(item)).length;

        expect(coverCount).toBe(1);
    });

    it('should emit an event when the media library is opened', () => {
        const wrapper = createWrapper();

        wrapper.vm.onOpenMedia();

        expect(wrapper.emitted('media-open')).toHaveLength(1);
    });

    it('should move media to the first position when marked as cover', () => {
        const wrapper = createWrapper();

        wrapper.vm.markMediaAsCover(wrapper.vm.blog.media[1]);

        expect(wrapper.vm.blog.coverId).toBe('blogMedia2');
        expect(wrapper.vm.blog.media[0].id).toBe('blogMedia2');
        expect(wrapper.vm.blog.media[0].position).toBe(0);
    });

    it('should keep the cover at the first position while sorting', () => {
        const wrapper = createWrapper();

        wrapper.vm.onMediaItemDragSort(wrapper.vm.blog.media[0], wrapper.vm.blog.media[1], true);

        expect(wrapper.vm.blog.media[0].id).toBe('blogMedia1');
    });

    it('should detect spatial and AR-ready media', async () => {
        const wrapper = createWrapper();
        await flushPromises();
        const spatialMedia = {
            media: {
                fileExtension: 'glb',
            },
        } as Entity<'blog_media'>;

        expect(wrapper.vm.isSpatial(spatialMedia)).toBe(true);
        expect(wrapper.vm.isArReady(spatialMedia)).toBe(true);
    });

    it('should replace an existing media association after upload', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.successfulUpload({ targetId: 'media1' });

        expect(wrapper.vm.blog.media).toHaveLength(2);
        const uploadedMedia = wrapper.vm.blog.media.find((item) => item.mediaId === 'media1');
        expect(uploadedMedia?.media?.url).toBe('https://example.com/media1-new-url.jpg');
    });
});

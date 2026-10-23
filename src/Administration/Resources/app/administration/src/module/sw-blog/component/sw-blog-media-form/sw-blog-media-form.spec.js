/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-blog-media-form', { sync: true }), {
        attachTo: document.body,
        global: {
            directives: {
                draggable: {},
                droppable: {},
                popover: {},
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        create: () => {
                            if (entity === 'blog_media') {
                                return {
                                    id: 'blogMedia1',
                                    mediaId: 'media1',
                                };
                            }

                            return undefined;
                        },
                        get: () => {
                            if (entity === 'media') {
                                return Promise.resolve({
                                    id: 'media1',
                                    url: 'http://contena.test/media1-new-url.jpg',
                                });
                            }

                            return Promise.resolve(undefined);
                        },
                    }),
                },
                systemConfigApiService: {
                    getValues: () =>
                        Promise.resolve({
                            'core.store.media.defaultEnableAugmentedReality': 'false',
                        }),
                },
            },
            stubs: {
                'sw-upload-listener': true,
                'sw-blog-image': await wrapTestComponent('sw-blog-image'),
                'sw-media-upload-v2': true,
                'sw-media-preview-v2': true,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'mt-floating-ui': {
                    template: '<div><slot /></div>',
                },
                'sw-label': true,
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-loader': true,
                'router-link': true,
            },
        },
    });
}

const media = [
    {
        mediaId: 'media1',
        position: 0,
        id: 'blogMedia1',
        media: {
            id: 'media1',
        },
    },
    {
        mediaId: 'media2',
        position: 1,
        id: 'blogMedia2',
        media: {
            id: 'media2',
        },
    },
];

function getMediaCollection(collection = []) {
    return new EntityCollection('/media', 'media', null, { isContenaContext: true }, collection, collection.length, null);
}

describe('module/sw-blog/component/sw-blog-media-form', () => {
    beforeEach(() => {
        const blog = {
            cover: {
                mediaId: 'media1',
                position: 1,
                id: 'blogMedia1',
                media: {
                    id: 'media1',
                },
            },
            coverId: 'blogMedia1',
            media: getMediaCollection(media.map((item) => ({ ...item, media: { ...item.media } }))),
        };
        blog.getEntityName = () => 'T-Shirt';

        Contena.Store.get('swProductDetail').blog = blog;
    });

    it('should show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = ['blog.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should only show 1 cover', async () => {
        global.activeAclRoles = ['blog.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        let coverCount = 0;
        wrapper.vm.mediaItems.forEach((mediaItem) => {
            if (wrapper.vm.isCover(mediaItem)) {
                coverCount += 1;
            }
        });

        expect(coverCount).toBe(1);
    });

    it('should emit an event when onOpenMedia() function is called', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents).toHaveLength(1);
    });

    it('should can show cover when `showCoverLabel` is true', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeTruthy();
    });

    it('should not show cover when `showCoverLabel` is false', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            showCoverLabel: false,
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-blog-media-form__previews').find('.sw-blog-image__context-button').trigger('click');
        await flushPromises();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons).toHaveLength(1);
        expect(buttons.at(0).text()).toContain('global.default.remove');
    });

    it('should move media to first position when it is marked as cover', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        let blogMediaItems = wrapper.findAll('.sw-blog-image');

        expect(blogMediaItems[0].classes()).toContain('is--cover');
        expect(blogMediaItems[0].find('sw-media-preview-v2-stub').attributes('source')).toEqual(media[0].mediaId);
        expect(blogMediaItems[1].classes()).not.toContain('is--cover');
        expect(blogMediaItems[1].find('sw-media-preview-v2-stub').attributes('source')).toEqual(media[1].mediaId);

        const contextButton = blogMediaItems[1].find('.sw-blog-image__context-button');
        await contextButton.trigger('click');
        await flushPromises();

        const buttonCover = contextButton.find('.sw-blog-image__button-cover');
        expect(buttonCover.exists()).toBeTruthy();

        // Media will be move to first position after clicking on Use as cover button
        await buttonCover.trigger('click');

        blogMediaItems = wrapper.findAll('.sw-blog-image');
        expect(blogMediaItems[0].classes()).toContain('is--cover');
        expect(blogMediaItems[0].find('sw-media-preview-v2-stub').attributes('source')).toEqual(media[1].mediaId);

        expect(blogMediaItems[1].classes()).not.toContain('is--cover');
        expect(blogMediaItems[1].find('sw-media-preview-v2-stub').attributes('source')).toEqual(media[0].mediaId);
    });

    it('should remove previous mediaItem if it already exists after upload', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        // Check if previous mediaItem exists
        expect(wrapper.vm.blog.media).toHaveLength(2);
        expect(wrapper.vm.blog.media[0].mediaId).toBe('media1');

        // Simulate successful upload for existing media
        await wrapper.vm.successfulUpload({ targetId: 'media1' });

        // Check if previous mediaItem is removed and new mediaItem is added
        expect(wrapper.vm.blog.media).toHaveLength(2);
        expect(wrapper.vm.blog.media[1].mediaId).toBe('media1');
        // Check if new mediaItem has new url
        expect(wrapper.vm.blog.media[1].media.url).toBe('http://contena.test/media1-new-url.jpg');
    });

    it('should move a dragged media item to the position of the drop target', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        const [cover, detail] = wrapper.vm.blog.media;
        const otherMedia = { id: 'blogMedia3', mediaId: 'media3', position: 2, media: { id: 'media3' } };
        wrapper.vm.blog.media.add(otherMedia);

        wrapper.vm.onMediaItemDragSort(otherMedia, detail, true);

        expect([...wrapper.vm.blog.media].map(({ id, position }) => `${position}:${id}`)).toEqual([
            `0:${cover.id}`,
            '1:blogMedia3',
            `2:${detail.id}`,
        ]);
    });

    describe('when an extension adds items to the grid', () => {
        const extensionItem = { id: 'extensionItem', position: 1 };
        const moveGalleryItem = jest.fn();

        beforeEach(() => {
            Contena.Component.override('sw-blog-media-form', {
                template: `
                    {% block sw_product_media_form_grid_item %}
                    <div v-if="mediaItem.id === 'extensionItem'" class="extension-item"></div>
                    <template v-else>{% parent %}</template>
                    {% endblock %}
                `,
                computed: {
                    galleryItems() {
                        const items = this.$super('galleryItems');
                        items.splice(1, 0, extensionItem);

                        return items;
                    },
                },
                methods: {
                    moveGalleryItem,
                },
            });
        });

        afterEach(() => {
            Contena.Component.getOverrideRegistry().delete('sw-blog-media-form');
            moveGalleryItem.mockClear();
        });

        it('should render the extension items between the blog media', async () => {
            global.activeAclRoles = [];
            const wrapper = await createWrapper();
            await flushPromises();

            const items = wrapper.findAll('.sw-blog-media-form__grid > *');

            expect(items[0].classes()).toContain('is--cover');
            expect(items[1].classes()).toContain('extension-item');
            expect(items[2].find('sw-media-preview-v2-stub').attributes('source')).toBe('media2');
            expect(wrapper.vm.getPlaceholderCount(5)).toBe(7);
        });

        it('should let the extension move the grid items', async () => {
            global.activeAclRoles = [];
            const wrapper = await createWrapper();
            await flushPromises();

            const detail = wrapper.vm.blog.media[1];

            wrapper.vm.onMediaItemDragSort(extensionItem, detail, true);
            wrapper.vm.markMediaAsCover(detail);

            expect(moveGalleryItem.mock.calls).toEqual([
                [extensionItem, detail.position],
                [detail, 0],
            ]);
        });
    });

    describe('when the blog has not been loaded yet', () => {
        let loadedProduct;

        beforeEach(() => {
            loadedProduct = Contena.Store.get('swProductDetail').blog;
            Contena.Store.get('swProductDetail').blog = {};
        });

        afterEach(() => {
            Contena.Store.get('swProductDetail').blog = loadedProduct;
        });

        it('should render without a media association', async () => {
            global.activeAclRoles = ['blog.editor'];
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.cover).toBeNull();
            expect(wrapper.vm.blogMedia).toEqual([]);
            expect(wrapper.find('.sw-blog-media-form').exists()).toBeTruthy();
            expect(wrapper.find('.sw-blog-media-form__cover-image.is--placeholder').exists()).toBeTruthy();
        });
    });
});

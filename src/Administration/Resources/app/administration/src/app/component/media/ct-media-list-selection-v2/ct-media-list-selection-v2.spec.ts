import { mount, type VueWrapper } from '@vue/test-utils';
import { reactive, type ComponentPublicInstance } from 'vue';

type MediaListItem = {
    id: string;
    url: string;
    position: number;
};

type MediaListSelection = ComponentPublicInstance & {
    mediaItems: MediaListItem[];
    onMediaItemDragSort: (dragData?: MediaListItem, dropData?: MediaListItem, validDrop?: boolean) => void;
};

const entityMediaItems: MediaListItem[] = [
    {
        id: '1',
        url: 'http://contena.cn/image1.jpg',
        position: 3,
    },
    {
        id: '2',
        url: 'http://contena.cn/image2.jpg',
        position: 1,
    },
    {
        id: '3',
        url: 'http://contena.cn/image3.jpg',
        position: 2,
    },
];

async function createWrapper(): Promise<VueWrapper> {
    return mount(await wrapTestComponent('ct-media-list-selection-v2', { sync: true }), {
        props: {
            entity: {
                id: 'media-list',
                isLoading: false,
                getEntityName: () => 'media',
            },
            entityMediaItems: reactive([...entityMediaItems]),
        },
        global: {
            provide: {
                mediaService: {},
            },
            stubs: {
                'ct-media-upload-v2': true,
                'ct-media-list-selection-item-v2': await wrapTestComponent('ct-media-list-selection-item-v2'),
                'ct-media-preview-v2': {
                    props: ['source'],
                    template: '<div class="ct-media-preview-v2">{{ source }}</div>',
                },
                'ct-context-button': true,
                'ct-context-menu-item': true,
                'ct-loader': true,
            },
        },
    });
}

describe('components/media/ct-media-list-selection-v2', () => {
    let wrapper: VueWrapper | undefined;

    beforeAll(() => {
        (globalThis as unknown as { swDefinePublic: (bindings: unknown) => void }).swDefinePublic = () => undefined;
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    afterAll(() => {
        Reflect.deleteProperty(globalThis, 'swDefinePublic');
    });

    it('should set the position property for each item by index in computed', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const vm = wrapper.vm as unknown as MediaListSelection;
        vm.mediaItems.forEach((item, index) => {
            expect(item.position).toBe(index);
        });
    });

    it('should emit item-sort event when drag and drop item valid', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const vm = wrapper.vm as unknown as MediaListSelection;
        vm.onMediaItemDragSort(
            { id: '2', url: 'http://contena.cn/image2.jpg', position: 1 },
            { id: '1', url: 'http://contena.cn/image1.jpg', position: 2 },
            true,
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('item-sort')).toEqual([
            [
                { id: '2', url: 'http://contena.cn/image2.jpg', position: 1 },
                { id: '1', url: 'http://contena.cn/image1.jpg', position: 2 },
            ],
        ]);
    });

    it('should not emit item-sort event when drag and drop item is invalid', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const vm = wrapper.vm as unknown as MediaListSelection;
        vm.onMediaItemDragSort();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('item-sort')).toBeUndefined();
    });

    it('should update the ct-media-list-selection-item-v2 when URL in the mediaItem changes', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.ct-media-list-selection-item-v2').text()).toContain('1');

        await wrapper.setProps({
            entityMediaItems: [
                {
                    id: 'newId',
                    url: 'http://contena.cn/image1-updated.jpg',
                    position: 3,
                },
                ...entityMediaItems.slice(1),
            ],
        });
        await flushPromises();

        expect(wrapper.find('.ct-media-list-selection-item-v2').text()).toContain('newId');
    });
});

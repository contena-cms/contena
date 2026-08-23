import { mount } from '@vue/test-utils';
import uuid from 'test/_helper_/uuid';

async function createWrapper(items, mediaRepositoryFunctions = {}) {
    return mount(await wrapTestComponent('ct-media-sidebar', { sync: true }), {
        global: {
            stubs: {
                'ct-media-quickinfo': {
                    template: `
                    <button class='ct-media-quickinfo' @click="modifyItem"></button>`,
                    props: {
                        item: {
                            required: true,
                            type: Object,
                            default: {},
                        },
                    },

                    methods: {
                        modifyItem() {
                            const { item } = this;
                            item.fileName = 'a-new-name.glb';

                            this.$emit('update:item', item);
                        },
                    },
                },
                'ct-media-folder-info': true,
                'ct-media-quickinfo-multiple': true,
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        save: () => Promise.resolve(true),
                        ...mediaRepositoryFunctions,
                    }),
                },
            },
        },

        props: {
            items: items,
        },
    });
}

const defaultNames = [
    't-shirt.png',
    'flask.jpg',
    'router.glb',
];
const createItems = (itemNames = defaultNames) => {
    return itemNames.map((name) => {
        return {
            getEntityName: () => {
                return 'media';
            },
            id: uuid.get(name),
            fileName: name,
            avatarUsers: [],
            categories: [],
            productManufacturers: [],
            productMedia: [],
            mailTemplateMedia: [],
            documentBaseConfigs: [],
            paymentMethods: [],
            shippingMethods: [],
        };
    });
};

describe('module/ct-media/component/sidebar/ct-media-sidebar', () => {
    it('emits a close event from the details header', async () => {
        const wrapper = await createWrapper(
            createItems([
                'router.glb',
                'flask.jpg',
            ]),
        );

        await wrapper.get('.ct-media-sidebar__close').trigger('click');

        expect(wrapper.emitted('media-sidebar-close')).toHaveLength(1);
    });

    it('should save item data when receiving item:update event', async () => {
        const mediaItems = createItems(['router.glb']);
        const mediaSaveMock = jest.fn();
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };

        const wrapper = await createWrapper(mediaItems, mediaRepositoryFunctions);
        await wrapper.vm.$nextTick();
        const mediaQuickInfo = wrapper.findComponent('.ct-media-quickinfo');
        expect(mediaQuickInfo.exists()).toBe(true);

        await mediaQuickInfo.trigger('click');
        await flushPromises();
        expect(mediaQuickInfo.emitted('update:item')).toBeTruthy();

        expect(mediaSaveMock).toHaveBeenCalled();
        expect(mediaSaveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                id: uuid.get('router.glb'),
                fileName: 'a-new-name.glb',
            }),
            expect.any(Object),
        );
    });
});

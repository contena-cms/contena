import { mount } from '@vue/test-utils';

const setup = async (itemChanges = {}, componentProps = {}) => {
    const propsData = {
        item: {
            fileName: 'example',
            fileExtension: 'jpg',
            isLoading: false,
        },
    };
    propsData.item = { ...propsData.item, ...itemChanges };

    return mount(await wrapTestComponent('ct-media-base-item', { sync: true }), {
        global: {
            stubs: {
                'ct-context-button': true,
                'ct-label': await wrapTestComponent('ct-label', {
                    sync: true,
                }),
                'ct-color-badge': true,
                'mt-checkbox': true,
            },
            provide: {
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({});
                    },
                },
            },
        },
        propsData: {
            ...propsData,
            ...componentProps,
        },
    });
};

describe('src/app/asyncComponent/media/ct-media-base-item', () => {
    it('should show icon--regular-AR if spatial objet is AR ready', async () => {
        const wrapper = await setup({
            fileExtension: 'glb',
            config: {
                spatial: {
                    arReady: true,
                },
            },
        });
        expect(wrapper.find('.icon--regular-AR').exists()).toBeTruthy();
    });

    it('should show icon--regular-3d if the spatial object is not ready to use in AR', async () => {
        const wrapper = await setup({
            fileExtension: 'glb',
            config: {
                spatial: {
                    arReady: false,
                },
            },
        });

        expect(wrapper.find('.icon--regular-AR').exists()).toBe(false);
        expect(wrapper.find('.icon--regular-3d').exists()).toBe(true);
    });

    it('should check item.url if item.fileExtension is not defined', async () => {
        const wrapper = await setup({
            fileExtension: undefined,
            config: {
                spatial: {
                    arReady: false,
                },
            },
            url: 'http://test/example.glb',
        });

        expect(wrapper.find('.icon--regular-3d').exists()).toBe(true);
    });

    it('should not show any icon if item is not a spatial object', async () => {
        const wrapper = await setup();

        expect(wrapper.find('.icon--regular-AR').exists()).toBe(false);
        expect(wrapper.find('.icon--regular-3d').exists()).toBe(false);
    });

    it('renders metadata below grid cards when requested', async () => {
        const wrapper = await setup({}, { showGridMetadata: true });

        expect(wrapper.find('.ct-media-base-item__metadata-container').exists()).toBe(true);
    });
});

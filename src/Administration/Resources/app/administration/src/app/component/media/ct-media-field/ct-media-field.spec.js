import { mount } from '@vue/test-utils';

describe('src/app/component/media/ct-media-field', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('ct-media-field', { sync: true }), {
            props: {
                fileAccept: '*/*',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'ct-media-media-item': true,
                    'mt-floating-ui': {
                        template: `
                        <div>
                            <slot />
                        </div>
                    `,
                    },
                    'ct-media-upload-v2': true,
                    'ct-upload-listener': true,
                    'ct-simple-search-field': true,
                    'ct-loader': true,
                    'ct-pagination': true,
                },
                mocks: {
                    $route: {
                        query: '',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return Promise.resolve();
                            },
                            get: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve();
                            },
                        }),
                    },
                },
            },
        });
    }

    it('should contain the default folder in criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            defaultFolder: 'product',
        });
        const criteria = wrapper.vm.suggestionCriteria;
        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'mediaFolder.defaultFolder.entity',
            value: 'product',
        });

        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(5);
    });

    it('should contain a property props fileAccept', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$props.fileAccept).toBe('*/*');
    });

    it('should stop propagation when floating content is clicked', async () => {
        const wrapper = await createWrapper();

        Object.assign(wrapper.vm, {
            showPicker: true,
        });
        await wrapper.vm.$nextTick();

        const stopPropagation = jest.fn();
        await wrapper.find('.ct-media-field__actions_bar').trigger('click', {
            stopPropagation,
        });

        expect(stopPropagation).toHaveBeenCalled();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.total).toBe(0);
    });

    it('should be able to change search term', async () => {
        const wrapper = await createWrapper();
        const searchSpy = jest.spyOn(wrapper.vm.mediaRepository, 'search');

        wrapper.vm.onSearchTermChange('test');
        await flushPromises();

        expect(wrapper.vm.searchTerm).toBe('test');
        expect(wrapper.vm.page).toBe(1);
        expect(searchSpy).toHaveBeenCalled();
    });

    it('should be able to change page', async () => {
        const wrapper = await createWrapper();
        const searchSpy = jest.spyOn(wrapper.vm.mediaRepository, 'search');

        wrapper.vm.onPageChange({
            page: 2,
            limit: 5,
        });
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(searchSpy).toHaveBeenCalled();
    });

    it('anchors the floating content to the media toggle button', async () => {
        const wrapper = await createWrapper();
        Object.assign(wrapper.vm, { showPicker: true });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.popoverAnchorElement).toBe(wrapper.vm.$refs.mediaToggleButton.$el);
    });

    it('should render ct-upload-listener with correct upload tag and auto-upload', async () => {
        const wrapper = await createWrapper();
        Object.assign(wrapper.vm, { showPicker: true });
        await wrapper.vm.$nextTick();

        const uploadListener = wrapper.find('ct-upload-listener-stub');

        expect(uploadListener.exists()).toBe(true);
        expect(uploadListener.attributes('upload-tag')).toBe(wrapper.vm.uploadTag);
        expect(uploadListener.attributes()).toHaveProperty('auto-upload');
    });

    it('should set media id and close picker when upload finishes', async () => {
        const wrapper = await createWrapper();
        Object.assign(wrapper.vm, { showPicker: true, showUploadField: true });
        await wrapper.vm.$nextTick();

        const targetId = 'new-media-id-123';
        wrapper.vm.exposeNewId({ targetId });

        expect(wrapper.emitted('update:value')).toEqual([[targetId]]);
        expect(wrapper.vm.showUploadField).toBe(false);
        expect(wrapper.vm.showPicker).toBe(false);
    });
});

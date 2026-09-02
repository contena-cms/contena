import { shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

type MediaCollectionField = {
    onOpenMediaModal: () => void;
    onCloseMediaModal: () => void;
    onMediaSelectionChange: (selection: Array<{ id: string }>) => void;
    onUploadFinish: (upload: { targetId: string }) => void;
    onItemRemove: (media: { mediaId: string; url: string; position: number }) => void;
    onItemSort: (
        dragData: { mediaId: string; url: string; position: number },
        dropData: { mediaId: string; url: string; position: number },
    ) => void;
    showMediaModal: boolean;
};

describe('module/ct-experience-studio/component/ct-experience-studio-media-collection-field', () => {
    let wrapper: VueWrapper | undefined;

    beforeAll(() => {
        (globalThis as unknown as { ctDefinePublic: (bindings: unknown) => void }).ctDefinePublic = () => undefined;
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    afterAll(() => {
        Reflect.deleteProperty(globalThis, 'ctDefinePublic');
    });

    function createWrapper(value: string[] | null = null, disabled = false): VueWrapper {
        return shallowMount(component, {
            props: { value, disabled },
            global: {
                stubs: {
                    'ct-media-list-selection-v2': true,
                    'ct-upload-listener': true,
                    'ct-media-modal-v2': true,
                },
            },
        });
    }

    it('opens and closes the media modal unless disabled', () => {
        wrapper = createWrapper();
        const vm = wrapper.vm as unknown as MediaCollectionField;

        vm.onOpenMediaModal();
        expect(vm.showMediaModal).toBe(true);

        vm.onCloseMediaModal();
        expect(vm.showMediaModal).toBe(false);

        wrapper.unmount();
        wrapper = createWrapper(null, true);
        const disabledVm = wrapper.vm as unknown as MediaCollectionField;
        disabledVm.onOpenMediaModal();

        expect(disabledVm.showMediaModal).toBe(false);
    });

    it('merges selected and uploaded media without duplicates', () => {
        wrapper = createWrapper(['media-1']);
        const vm = wrapper.vm as unknown as MediaCollectionField;

        vm.onMediaSelectionChange([
            { id: 'media-1' },
            { id: 'media-2' },
        ]);
        vm.onUploadFinish({ targetId: 'media-1' });
        vm.onUploadFinish({ targetId: 'media-3' });

        expect(wrapper.emitted('update:value')).toEqual([
            [
                [
                    'media-1',
                    'media-2',
                ],
            ],
            [
                [
                    'media-1',
                    'media-3',
                ],
            ],
        ]);
    });

    it('removes and sorts media IDs using the upstream value contract', () => {
        wrapper = createWrapper([
            'media-1',
            'media-2',
            'media-3',
        ]);
        const vm = wrapper.vm as unknown as MediaCollectionField;

        vm.onItemRemove({ mediaId: 'media-2', url: 'media-2', position: 1 });
        vm.onItemSort(
            { mediaId: 'media-3', url: 'media-3', position: 2 },
            { mediaId: 'media-1', url: 'media-1', position: 0 },
        );

        expect(wrapper.emitted('update:value')).toEqual([
            [
                [
                    'media-1',
                    'media-3',
                ],
            ],
            [
                [
                    'media-3',
                    'media-1',
                    'media-2',
                ],
            ],
        ]);
    });

    it('emits null after removing the final media item', () => {
        wrapper = createWrapper(['media-1']);
        const vm = wrapper.vm as unknown as MediaCollectionField;

        vm.onItemRemove({ mediaId: 'media-1', url: 'media-1', position: 0 });

        expect(wrapper.emitted('update:value')).toEqual([[null]]);
    });
});

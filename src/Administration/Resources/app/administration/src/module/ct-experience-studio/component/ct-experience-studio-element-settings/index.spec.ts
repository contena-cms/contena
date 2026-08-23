/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import elementSettingsComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-element-settings', () => {
    const imageType = {
        properties: {
            media: {},
        },
        bindingSpecifications: {
            'core:CT:Media:Image': {
                default: true,
                resolves: {
                    media: {
                        loader: 'entity',
                        config: {
                            entity: 'media',
                            property: 'mediaId',
                        },
                    },
                },
            },
        },
    };

    it('reads resolved fields from their storage key', () => {
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: { properties: { mediaId: 'media-id' } },
                selectedElementType: imageType,
            },
        });

        expect(wrapper.vm.elementPropertyValues).toEqual({
            media: 'media-id',
            mediaId: 'media-id',
        });
    });

    it('emits resolved fields using their storage key', () => {
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: { id: 'image-element' },
                selectedElementType: imageType,
                allowEdit: true,
            },
        });

        wrapper.vm.onUpdateElementField({ key: 'media', value: 'media-id' });

        expect(wrapper.emitted('update-properties')).toEqual([
            [
                {
                    elementId: 'image-element',
                    properties: { mediaId: 'media-id' },
                },
            ],
        ]);
    });

    it('keeps breakpoint-aware box spacing properties in the element settings', () => {
        const property = {
            type: [
                'string',
                'object',
            ],
            adminUI: {
                component: 'box-spacing',
                breakpointAware: true,
            },
        };
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: { properties: {} },
                selectedElementType: { properties: { padding: property } },
            },
        });

        expect(wrapper.vm.elementFields).toEqual([
            {
                key: 'padding',
                property,
                breakpointAware: true,
            },
        ]);
    });
});

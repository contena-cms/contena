/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import elementSettingsComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-element-settings', () => {
    const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
    const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
    const imageType = {
        properties: {
            media: {},
        },
        bindingSpecifications: {
            'core:Ct:Media:Image': {
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
                    propertyKey: 'mediaId',
                    value: 'media-id',
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

    const textType = {
        properties: {
            text: {
                type: 'string',
                translatable: true,
                default: 'Placeholder',
            },
        },
        bindingSpecifications: {},
    };

    it('presents the anchor chain entry of a translatable property to the field controls', () => {
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: {
                    properties: {
                        text: {
                            [GERMAN_LANGUAGE_ID]: 'Hallo',
                            [ANCHOR_LANGUAGE_ID]: 'Hello',
                        },
                    },
                },
                selectedElementType: textType,
            },
        });

        expect(wrapper.vm.elementPropertyValues).toEqual({ text: 'Hello' });
    });

    it('leaves a translatable property without an anchor chain entry absent so its declared default applies', () => {
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: {
                    properties: {
                        text: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                    },
                },
                selectedElementType: textType,
            },
        });

        expect(wrapper.vm.elementPropertyValues).toEqual({});
        expect(Object.prototype.hasOwnProperty.call(wrapper.vm.elementPropertyValues, 'text')).toBe(false);
    });

    it('emits the raw control value of a translatable property under its own key', () => {
        const wrapper = shallowMount(elementSettingsComponent, {
            props: {
                selectedElement: {
                    id: 'text-element',
                    properties: {
                        text: {
                            [ANCHOR_LANGUAGE_ID]: 'Hello',
                            [GERMAN_LANGUAGE_ID]: 'Hallo',
                        },
                    },
                },
                selectedElementType: textType,
                allowEdit: true,
            },
        });

        wrapper.vm.onUpdateElementField({ key: 'text', value: 'Hello again' });

        expect(wrapper.emitted('update-properties')).toEqual([
            [
                {
                    elementId: 'text-element',
                    propertyKey: 'text',
                    value: 'Hello again',
                },
            ],
        ]);
    });
});

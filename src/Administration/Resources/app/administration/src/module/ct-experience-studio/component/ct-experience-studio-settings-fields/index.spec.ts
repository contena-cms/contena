/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import settingsFieldsComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-settings-fields', () => {
    beforeAll(() => {
        (globalThis as unknown as { ctDefinePublic: (bindings: unknown) => void }).ctDefinePublic = () => undefined;
    });

    afterAll(() => {
        Reflect.deleteProperty(globalThis, 'ctDefinePublic');
    });
    const createWrapper = (props: Record<string, unknown> = {}) =>
        shallowMount(settingsFieldsComponent, {
            props: {
                fields: [],
                values: {},
                ...props,
            },
            global: {
                stubs: {
                    'mt-collapsible': defineComponent({
                        template: '<div><slot :open="true" /></div>',
                    }),
                    'ct-media-field': defineComponent({
                        name: 'ct-media-field',
                        props: [
                            'value',
                            'label',
                            'disabled',
                        ],
                        emits: ['update:value'],
                        template: '<button class="ct-media-field-stub" @click="$emit(\'update:value\', \'media-2\')" />',
                    }),
                    'ct-experience-studio-media-collection-field': defineComponent({
                        name: 'ct-experience-studio-media-collection-field',
                        props: [
                            'value',
                            'label',
                            'disabled',
                        ],
                        emits: ['update:value'],
                        template:
                            '<button class="ct-media-collection-field-stub" @click="$emit(\'update:value\', [\'media-2\'])" />',
                    }),
                },
            },
        });

    it('groups fields by panel while preserving their order', () => {
        const wrapper = createWrapper({
            showPanels: true,
            fields: [
                { key: 'mode', property: { adminUI: { panel: 'general' } } },
                { key: 'padding', property: { adminUI: { panel: 'spacing' } } },
                { key: 'columns', property: { adminUI: { panel: 'general' } } },
                { key: 'custom', property: {} },
            ],
        });
        const panels = wrapper.vm.fieldPanels as Array<{
            technicalName: string | null;
            fields: Array<{ key: string }>;
        }>;

        expect(
            panels.map((panel) => ({
                technicalName: panel.technicalName,
                fields: panel.fields.map((field) => field.key),
            })),
        ).toEqual([
            {
                technicalName: 'general',
                fields: [
                    'mode',
                    'columns',
                ],
            },
            { technicalName: 'spacing', fields: ['padding'] },
            { technicalName: null, fields: ['custom'] },
        ]);
    });

    it('keeps style option fields in one plain group', () => {
        const fields = [
            { key: 'display', property: {} },
            { key: 'margin', property: { adminUI: { panel: 'spacing' } } },
        ];
        const wrapper = createWrapper({ fields, showPanels: false });

        expect(wrapper.vm.fieldPanels).toEqual([
            {
                key: '__default__',
                technicalName: null,
                fields,
            },
        ]);
    });

    it('builds element-specific and default panel snippet keys', () => {
        const wrapper = createWrapper({ selectedElementType: { name: 'CT:Grid:Container' } });

        expect(wrapper.vm.getPanelSnippetKey({ technicalName: 'spacing' })).toBe(
            'ct-experience-studio.elements.ct-grid-container.panels.spacing',
        );

        const defaultWrapper = createWrapper();
        expect(defaultWrapper.vm.getPanelSnippetKey({ technicalName: null })).toBe(
            'ct-experience-studio.detail.elementSettings.panelGeneral',
        );
    });

    it('translates the generated panel snippet key', () => {
        const wrapper = createWrapper({ selectedElementType: { name: 'CT:Grid:Container' } });

        expect(wrapper.vm.getPanelTitle({ technicalName: 'spacing' })).toBe(
            'ct-experience-studio.elements.ct-grid-container.panels.spacing',
        );
    });

    it('expands only the general panel by default', () => {
        const wrapper = createWrapper({ showPanels: true });

        expect(wrapper.vm.isPanelExpandedByDefault({ technicalName: 'general' })).toBe(true);
        expect(wrapper.vm.isPanelExpandedByDefault({ technicalName: 'spacing' })).toBe(false);
        expect(wrapper.vm.isPanelExpandedByDefault({ technicalName: null })).toBe(true);

        const plainWrapper = createWrapper({ showPanels: false });
        expect(plainWrapper.vm.isPanelExpandedByDefault({ technicalName: 'spacing' })).toBe(true);
    });

    it('maps corner radius previews from radio panel options', () => {
        const wrapper = createWrapper();

        expect(
            wrapper.vm.getRadioPanelOptions({
                adminUI: {
                    props: {
                        options: [{ value: '8px', label: 'Medium', cornerRadius: '8px' }],
                    },
                },
            }),
        ).toEqual([
            {
                value: '8px',
                label: 'Medium',
                cornerRadius: '8px',
                icon: undefined,
                description: undefined,
                disabled: false,
            },
        ]);
    });

    it('uses a shared structured default for breakpoint-aware box spacing', () => {
        const wrapper = createWrapper();

        expect(
            wrapper.vm.getResponsiveFallbackValue({
                type: [
                    'string',
                    'object',
                ],
                default: null,
                adminUI: { component: 'box-spacing' },
                properties: {
                    xs: { default: '0 20px 0 20px' },
                    sm: { default: '0 20px 0 20px' },
                },
            }),
        ).toBe('0 20px 0 20px');
    });

    it('uses the upstream media field value contract for media controls', async () => {
        const wrapper = createWrapper({
            fields: [
                {
                    key: 'media',
                    property: {
                        title: 'Media',
                        type: [
                            'string',
                            'null',
                        ],
                        adminUI: { component: 'ct-media-field' },
                    },
                },
            ],
            values: { media: 'media-1' },
            allowEdit: true,
        });
        const mediaField = wrapper.findComponent({ name: 'ct-media-field' });

        expect(mediaField.props('value')).toBe('media-1');
        expect(mediaField.props('label')).toBe('Media');

        const mediaFieldVm = mediaField.vm as { $emit: (event: string, value: string) => void };
        mediaFieldVm.$emit('update:value', 'media-2');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update-field')).toEqual([
            [
                {
                    key: 'media',
                    value: 'media-2',
                },
            ],
        ]);
    });

    it('uses the media collection value contract for gallery controls', async () => {
        const wrapper = createWrapper({
            fields: [
                {
                    key: 'mediaItems',
                    property: {
                        title: 'Media',
                        type: 'Contena\\Core\\Content\\Media\\MediaCollection',
                        adminUI: { component: 'media-collection' },
                    },
                },
            ],
            values: { mediaItems: ['media-1'] },
            allowEdit: true,
        });
        const mediaCollectionField = wrapper.findComponent({ name: 'ct-experience-studio-media-collection-field' });

        expect(mediaCollectionField.props('value')).toEqual(['media-1']);
        expect(mediaCollectionField.props('label')).toBe('Media');

        const mediaCollectionFieldVm = mediaCollectionField.vm as {
            $emit: (event: string, value: string[]) => void;
        };
        mediaCollectionFieldVm.$emit('update:value', ['media-2']);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update-field')).toEqual([
            [
                {
                    key: 'mediaItems',
                    value: ['media-2'],
                },
            ],
        ]);
    });
});

/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import pickerComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-element-picker', () => {
    const createWrapper = (props: Record<string, unknown> = {}) =>
        shallowMount(pickerComponent, {
            props: {
                open: true,
                title: 'Elements',
                ...props,
            },
        });

    it('normalizes unknown and invalid categories to other', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.normalizeCategoryKey(null)).toBe('other');
        expect(wrapper.vm.normalizeCategoryKey('***')).toBe('other');
    });

    it('groups elements and keeps category order with others last', () => {
        const wrapper = createWrapper({
            elements: [
                { name: 'type-1', label: 'Image', icon: null, category: 'media' },
                { name: 'type-2', label: 'Text', icon: null, category: 'content' },
                { name: 'type-3', label: 'Grid', icon: null, category: 'layout' },
                { name: 'type-4', label: 'Gallery', icon: null, category: 'media' },
                { name: 'type-5', label: 'Text 2', icon: null, category: 'Content' },
                { name: 'type-7', label: 'Text', icon: null, category: 'content' },
                {
                    name: 'core.text-block',
                    label: 'Text block',
                    icon: null,
                    category: 'presets',
                    kind: 'preset',
                    id: 'core.text-block',
                },
                { name: 'type-6', label: 'Unknown', icon: null, category: null },
            ],
        });

        const groups = wrapper.vm.groupedElements as Array<{
            key: string;
            headlineSnippetKey: string;
            elements: Array<{ name: string }>;
        }>;

        expect(groups).toHaveLength(5);
        expect(groups[0].key).toBe('layout');
        expect(groups[1].key).toBe('content');
        expect(groups[1].headlineSnippetKey).toBe('ct-experience-studio.detail.elementPicker.categoryHeadlines.content');
        expect(groups[1].elements.map((element) => element.name)).toEqual([
            'type-2',
            'type-5',
            'type-7',
        ]);
        expect(groups[2].key).toBe('media');
        expect(groups[3].key).toBe('presets');
        expect(groups[4].key).toBe('other');
    });

    it('emits select-preset for a preset item and select for an element item', () => {
        const wrapper = createWrapper();

        wrapper.vm.onSelect({
            name: 'core.text-block',
            label: 'Text block',
            icon: null,
            kind: 'preset',
            id: 'core.text-block',
        });
        wrapper.vm.onSelect({ name: 'CT:Content:Text', label: 'Text', icon: null, kind: 'element' });

        expect(wrapper.emitted('select-preset')).toEqual([['core.text-block']]);
        expect(wrapper.emitted('select')).toEqual([['CT:Content:Text']]);
    });

    it('renders a plain label tooltip for elements and a bold name plus description for presets', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.itemTooltip({ name: 'CT:Content:Text', label: 'Text', icon: null, kind: 'element' })).toEqual({
            message: 'Text',
        });
        expect(
            wrapper.vm.itemTooltip({
                name: 'core.text-block',
                label: 'Text block',
                icon: null,
                kind: 'preset',
                id: 'core.text-block',
                description: 'A single rich-text element.',
            }),
        ).toEqual({
            message: '<strong>Text block</strong><br>A single rich-text element.',
        });
        expect(
            wrapper.vm.itemTooltip({
                name: 'core.empty',
                label: 'Empty',
                icon: null,
                kind: 'preset',
                id: 'core.empty',
                description: null,
            }),
        ).toEqual({
            message: '<strong>Empty</strong>',
        });
    });
});

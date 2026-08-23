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
                { name: 'type-6', label: 'Unknown', icon: null, category: null },
            ],
        });

        const groups = wrapper.vm.groupedElements as Array<{
            key: string;
            headlineSnippetKey: string;
            elements: Array<{ name: string }>;
        }>;

        expect(groups).toHaveLength(4);
        expect(groups[0].key).toBe('layout');
        expect(groups[1].key).toBe('content');
        expect(groups[1].headlineSnippetKey).toBe('ct-experience-studio.detail.elementPicker.categoryHeadlines.content');
        expect(groups[1].elements.map((element) => element.name)).toEqual([
            'type-2',
            'type-5',
            'type-7',
        ]);
        expect(groups[2].key).toBe('media');
        expect(groups[3].key).toBe('other');
    });
});

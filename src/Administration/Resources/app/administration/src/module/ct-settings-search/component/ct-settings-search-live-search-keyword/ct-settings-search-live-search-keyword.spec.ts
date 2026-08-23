import { mount } from '@vue/test-utils';
import component from './ct-settings-search-live-search-keyword.vue';

const defaultHighlightClass = '.ct-settings-search-live-search-keyword__highlight';

function createWrapper(searchTerm = '', text = '', highlightClass?: string) {
    return mount(component, {
        props: { searchTerm, text, highlightClass },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('ct-settings-search-live-search-keyword', () => {
    it('renders no highlight for a missing term', () => {
        const wrapper = createWrapper('made', 'Rustic Granite Blog');

        expect(wrapper.find(defaultHighlightClass).exists()).toBe(false);
    });

    it('highlights one keyword case-insensitively', () => {
        const wrapper = createWrapper('iron', 'Durable Iron Article');

        expect(wrapper.find(defaultHighlightClass).text()).toBe('Iron');
    });

    it('uses a custom highlight class', () => {
        const wrapper = createWrapper('iron', 'Durable Iron Article', 'foo-blue-keyword');

        expect(wrapper.find('.foo-blue-keyword').exists()).toBe(true);
    });

    it('highlights each entered keyword', () => {
        const wrapper = createWrapper('awesome wo qlear', 'Awesome Wooden Crystal Qlear');

        expect(wrapper.findAll(defaultHighlightClass)).toHaveLength(3);
    });

    it('escapes regular expression characters in the search term', () => {
        const wrapper = createWrapper('C++', 'A C++ article');

        expect(wrapper.find(defaultHighlightClass).text()).toBe('C++');
    });

    it('retains markup already highlighted by an extension', () => {
        const wrapper = createWrapper(
            'article',
            '<span class="ct-settings-search-live-search-keyword__highlight">Article</span>',
        );

        expect(wrapper.find(defaultHighlightClass).text()).toBe('Article');
    });
});

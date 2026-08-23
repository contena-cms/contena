import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('ct-highlight-text', { sync: true }), {
        props,
    });
}

describe('src/app/component/base/ct-highlight-text', () => {
    it('renders html-like input as text when no search term is provided', async () => {
        const wrapper = await createWrapper({
            text: '<article>example</article>',
            searchTerm: null,
        });

        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.text()).toContain('<article>example</article>');
    });

    it('keeps html-like input as text while applying highlighting', async () => {
        const wrapper = await createWrapper({
            text: '<article>example</article>',
            searchTerm: 'example',
        });

        expect(wrapper.find('article').exists()).toBe(false);
        expect(wrapper.find('.ct-highlight-text__highlight').exists()).toBe(true);
        expect(wrapper.find('.ct-highlight-text__highlight').text()).toBe('example');
        expect(wrapper.text()).toContain('<article>example</article>');
    });

    it('highlights text without a search-engine-specific query syntax', async () => {
        const wrapper = await createWrapper({
            text: 'This is a test. Testing, one, two, three.',
            searchTerm: 'test',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(2);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('test');
        expect(wrapper.findAll('.ct-highlight-text__highlight')[1].text()).toBe('Test');
    });

    it('treats special characters in a search term as literal text', async () => {
        const wrapper = await createWrapper({
            text: 'This is a test for .*special characters~"',
            searchTerm: '.*special characters~"',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('.*special characters~"');
    });

    it('highlights a search term that only contains a regex special character', async () => {
        const wrapper = await createWrapper({
            text: 'This is a test for *special* characters',
            searchTerm: '*',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(2);
        expect(wrapper.text()).toBe('This is a test for *special* characters');
    });

    it('preserves duplicate spaces in a literal search term', async () => {
        const wrapper = await createWrapper({
            text: 'This is a test for duplicate   spaces.',
            searchTerm: 'duplicate   spaces',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('duplicate   spaces');
    });

    it('treats boolean operator words as literal text', async () => {
        const wrapper = await createWrapper({
            text: 'This is a test for AND and OR operators.',
            searchTerm: 'AND',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(2);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('AND');
        expect(wrapper.findAll('.ct-highlight-text__highlight')[1].text()).toBe('and');
    });

    it('treats plus and minus signs as literal text', async () => {
        let wrapper = await createWrapper({
            text: 'This is a test for +plus at the start.',
            searchTerm: '+plus',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('+plus');

        wrapper = await createWrapper({
            text: 'This is a test for plus+ at the end.',
            searchTerm: 'plus+',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('plus+');

        wrapper = await createWrapper({
            text: 'This is a test for -minus at the start.',
            searchTerm: '-minus',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('-minus');

        wrapper = await createWrapper({
            text: 'This is a test for minus- at the end.',
            searchTerm: 'minus-',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('minus-');

        wrapper = await createWrapper({
            text: 'This is a test for plus and minus in a word. e.g. for order-number.',
            searchTerm: 'order-number',
        });

        expect(wrapper.findAll('.ct-highlight-text__highlight')).toHaveLength(1);
        expect(wrapper.findAll('.ct-highlight-text__highlight')[0].text()).toBe('order-number');
    });
});

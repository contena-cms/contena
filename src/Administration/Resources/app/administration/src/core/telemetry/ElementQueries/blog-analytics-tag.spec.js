import BlogAnalyticsTag from './blog-analytics-tag';

describe('src/core/telemetry/ElementQueries/blog-analytics-tag.ts', () => {
    it('recognizes added elements with blog analytics tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = BlogAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const taggedElement = document.createElement('div');
        taggedElement.setAttribute('data-blog-analytics', true);

        document.body.append(taggedElement);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements[0]).toBe(taggedElement);

        mo.disconnect();
    });

    it('recognizes nested elements with blog analytics tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = BlogAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('span');
        first.setAttribute('data-blog-analytics', true);
        const second = document.createElement('button');
        second.setAttribute('data-blog-analytics', true);

        div.appendChild(first);
        div.appendChild(second);

        document.body.append(div);

        await flushPromises();

        expect(observedElements).toHaveLength(2);
        expect(observedElements).toEqual([first, second]);

        mo.disconnect();
    });

    it('does not emit buttons without data attribute', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = BlogAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('button');
        first.setAttribute('data-blog-analytics', true);
        const second = document.createElement('button');

        div.appendChild(first);
        div.appendChild(second);

        document.body.append(div);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements).toEqual([first]);

        mo.disconnect();
    });
});

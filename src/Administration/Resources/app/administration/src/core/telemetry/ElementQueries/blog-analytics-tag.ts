/**
 * @sw-package framework
 */
import type { ElementQuery } from '../types';

const BlogAnalyticsTag: ElementQuery = (mutations: MutationRecord[]): Element[] => {
    const taggedElements: Element[] = [];

    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) {
                return;
            }

            if (node.hasAttribute('data-blog-analytics')) {
                taggedElements.push(node);
            }

            node.querySelectorAll('[data-blog-analytics]').forEach((anchor) => {
                taggedElements.push(anchor);
            });
        });
    });

    return taggedElements;
};

/**
 * @private
 */
export default BlogAnalyticsTag;

/**
 *
 * Overview
 * This custom ESLint rule enforces that the v-slot attribute is moved to the <ct-block> element when it wraps a single
 * child.
 *
 * How It Works
 * The rule identifies <ct-block> elements with a single child and checks if the child element has a v-slot attribute.
 * If it finds the v-slot attribute, it reports an error and suggests moving the attribute to the <ct-block> element.
 *
 * Example
 * ```
 * // before
 * <ct-block>
 *     <template v-slot:default="{slotProp}">
 *         <span>Title</span>
 *     </template>
 * </ct-block>
 * <ct-block>
 *     <template #content="{slotProp}">
 *         <p>Content</p>
 *     </template>
 * </ct-block>
 *
 * // after
 * <template v-slot:default="{slotProp}">
 *     <ct-block>
 *         <span>Title</span>
 *     </ct-block>
 * </template>
 * <div #content="{slotProp}">
 *     <ct-block>
 *         <p>Content</p>
 *     </ct-block>
 * </template>
 * ```
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: '<ct-block> with single child should move v-slot attribute to the <ct-block> element',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [], // No options needed
    },
    create(context) {
        const sourceCode = context.getSourceCode();

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.rawName !== 'ct-block') {
                    return;
                }
                const childrenElements = node.children.filter(child => child.type === 'VElement');

                if (childrenElements.length !== 1) {
                    return;
                }

                if (node.startTag.attributes.find(attr => attr.directive &&
                    attr.key.name.name === 'slot')) {
                    return;
                }

                const child = childrenElements[0];
                const childAttributes = child.startTag.attributes;
                const slotAttribute = childAttributes.find(attr => attr.directive && attr.key.name.name === 'slot');

                if (slotAttribute) {
                    context.report({
                        node,
                        message: '<ct-block> with single child should move v-slot attribute to the <ct-block> element',
                        fix(fixer) {
                            const parentStart = node.startTag.range;
                            const parentEnd = node.endTag.range;
                            const childStart = child.startTag.range;
                            const childEnd = child.endTag?.range;

                            return [
                                fixer.replaceTextRange(parentStart, context.getSourceCode().getText(child.startTag)),
                                fixer.replaceTextRange(parentEnd, context.getSourceCode().getText(child.endTag)),
                                fixer.replaceTextRange(childStart, context.getSourceCode().getText(node.startTag)),
                                fixer.replaceTextRange(childEnd, context.getSourceCode().getText(node.endTag)),
                            ];
                        },
                    });
                }
            },
        });
    },
};

